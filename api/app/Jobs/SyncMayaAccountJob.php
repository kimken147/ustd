<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use App\Services\Maya\MayaLoginService;
use App\Services\Maya\PayMayaApiService;
use App\Utils\GcashService;
use App\Models\MemberDevice;
use App\Models\UserChannelAccount;
use App\Services\Maya\MayaService;
use App\Utils\BCMathUtil;
use Exception;

class SyncMayaAccountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $accountId;
    private string $currentStatus;
    private $data;

    public $tries = 1;

    public function __construct(
        string $accountId,
        string $currentStatus,
        array $data = null
    ) {
        $this->accountId = $accountId;
        $this->currentStatus = $currentStatus;
        $this->data = $data;
        $this->queue = config("queue.queue-priority.high");
    }

    public function handle(
        MayaLoginService $ms,
        PayMayaApiService $mayaApiService,
        MayaService $mayaService
    ) {
        if ($this->attempts() > 1) {
            return false;
        }

        $account = UserChannelAccount::find($this->accountId);
        $detail = $account->detail;
        // $mobile = Str::padLeft($account["bank_card_number"], 11, 0);

        if ($this->currentStatus == "init") {
            try {
                $result = $ms->apiLogin($account["account"], $detail["mpin"]);
                $detail["sync_status"] = "need_otp";
            } catch (\Throwable $th) {
                $detail["sync_status"] = "mpin_fail";
                $account->update(["detail" => $detail]);
            }
            $detail["expiresChallengeId"] = $result["expiresChallengeId"];
            $detail["accessToken"] = $result["accessToken"];
            $detail["sync_at"] = now();
            $account->update(["detail" => $detail]);
        } elseif ($this->currentStatus == "need_otp") {
            if (
                isset($detail["expiresChallengeId"]) &&
                $this->data["otp"] &&
                isset($detail["accessToken"])
            ) {
                $expiresChallengeId = $detail["expiresChallengeId"];
                $otp = $this->data["otp"];
                $accessToken = $detail["accessToken"];

                try {
                    $profile = $ms->apiLoginNext(
                        $expiresChallengeId,
                        $otp,
                        $accessToken
                    );
                    $detail["account_status"] = "pass";
                    $account->update([
                        "detail" => $detail,
                    ]);
                } catch (Exception $e) {
                    $errorData = json_decode($e->getMessage());
                    if (
                        $errorData->data->account_status == "LIMITED" ||
                        $errorData->data->kyc_level == 0
                    ) {
                        $detail["sync_status"] = "account_limited";
                    } else {
                        $detail["sync_status"] = "otp_fail";
                    }
                    $detail["account_status"] = "fail";
                    $account->update([
                        "detail" => $detail,
                    ]);
                    return;
                }
                $mayaService->syncAccount(
                    $account->id,
                    $accessToken,
                    $profile["token"]
                );
            }
        }

        // if ($this->currentStatus == 'init') {
        //     try {
        //         $result = $gs->handshake($device->data);
        //         $device->refresh();

        //         $status = 'need_mpin';
        //     } catch (\Exception $e) {
        //         Log::error(__METHOD__, compact('account', 'e'));
        //         $status = 'handshake_fail';
        //     }

        //     $detail['sync_at'] = now();
        //     $detail['sync_status'] = $status;
        //     $account->update(['detail' => $detail]);
        // }

        // if ($this->status == 'mpin' && isset($detail['mpin'])) {
        //     $result = $gs->mpinLogin($device->data, $detail['mpin']);
        //     $device->refresh();

        //     if ($result['status'] == 1) { // mpin 成功
        //         $result = $gs->getDetails($device->data);

        //         $verified = data_get($result, 'res.data.kyc_level', 0) >= 2;

        //         $device->refresh();
        //         sleep(1);
        //         $balanceData = $gs->getBalance($device->data);
        //         sleep(1);
        //         $limitData = $gs->getLimit($device->data);

        //         $balance = data_get($balanceData, 'detail.balance');

        //         $math = app(BCMathUtil::class);
        //         $detail['balance_diff'] = $math->sub($balance, $account->balance, 2);

        //         $update = [];

        //         if ($balance !== null) {
        //             $account->updateBalanceByUser($balance);
        //         }

        //         if ($limitData['resultStatus'] == 1000) {
        //             $bcMath = app(BCMathUtil::class);
        //             $profileLimit = data_get($limitData, 'result.profileLimitCheckResponse')[0];
        //             $monthlyIncomingRemaining = data_get($profileLimit, 'limit.remaining.incoming.monthly.amount');
        //             $dailyOutgoingRemaining = data_get($profileLimit, 'limit.remaining.outgoing.daily.amount');

        //             $dailyIncomeLimit = data_get($limitData, 'result.upperLimit.incoming.daily.amount');
        //             $dailyOutgoingLimit = data_get($limitData, 'result.upperLimit.outgoing.daily.amount');
        //             $monthlyIncomeLimit = data_get($limitData, 'result.upperLimit.incoming.monthly.amount');
        //             $monthlyOutgoingLimit = data_get($limitData, 'result.upperLimit.outgoing.monthly.amount');

        //             if ($dailyIncomeLimit != 0) {
        //                 $update['daily_limit'] = $dailyIncomeLimit;
        //             }
        //             if ($dailyOutgoingLimit != 0) {
        //                 $update['withdraw_daily_limit'] = $dailyOutgoingLimit;
        //                 $update['withdraw_daily_total'] = $bcMath->sub((string)$dailyOutgoingLimit, (string)$dailyOutgoingRemaining);
        //             }
        //             if ($monthlyIncomeLimit != 0) {
        //                 $update['monthly_limit'] = $monthlyIncomeLimit;
        //                 $update['monthly_total'] = $bcMath->sub((string)$monthlyIncomeLimit, (string)$monthlyIncomingRemaining);
        //             }
        //             if ($monthlyOutgoingLimit != 0) {
        //                 $update['withdraw_monthly_limit'] = $monthlyOutgoingLimit;
        //             }
        //         }

        //         if (isset($detail['new_mpin']) && strlen($detail['new_mpin']) == 4) {
        //             $result = $gs->mpinChange($device->data, $detail['mpin'], $detail['new_mpin']);
        //             if ($result['status']) {
        //                 Log::info("{$mobile} change mpin: {$detail['mpin']} to {$detail['new_mpin']}");
        //                 $detail['mpin'] = $detail['new_mpin'];
        //                 unset($detail['new_mpin']);
        //             }
        //         }

        //         $account->update($update);

        //         unset($detail['sync_status']);
        //         $detail['account_status'] = $verified ? 'pass' : 'unverified';
        //         $detail['sync_status'] = $verified ? 'success' : 'account_unverified';
        //         $detail['sync_at'] = now();
        //     } else if ($result['status'] == 2) { // mpin 驗證失敗
        //         if (isset($result['message']['response']['body']['code']) && Str::startsWith($result['message']['response']['body']['code'], 'GE1519999330201')) {
        //             // 帳號失效
        //             unset($detail['sync_status']);
        //             $detail['account_status'] = 'fail';
        //         } else {
        //             $detail['sync_status'] = 'mpin_fail';
        //         }
        //     } else if ($result['status'] == 3) { // 因裝置變更或失效需要回到輸入 OTP
        //         $gs->makeOTP($device->data); // 基本上都會成功
        //         $detail['sync_status'] = 'need_otp';
        //     } else if ($result['status'] == 4) {
        //         $detail['sync_status'] = 'device_fail';
        //         MemberDevice::where('device', $mobile)->delete();
        //     } else {
        //         $detail['sync_status'] = 'mpin_fail';
        //     }

        //     if (isset($detail['sync_after_create']) && isset($detail['account_status'])) {
        //         unset($detail['sync_after_create']);
        //         if ($detail['account_status'] == 'unverified') {
        //             $account->note = 'KYC未认证';
        //         }
        //         if ($detail['account_status'] == 'fail') {
        //             $account->note = '风控';
        //         }
        //         if ($detail['account_status'] == 'pass') {
        //             $account->status = UserChannelAccount::STATUS_ONLINE;
        //         }
        //         $account->save();
        //     }

        //     $detail['sync_at'] = now();
        //     $account->update(['detail' => $detail]);
        // }

        // if ($this->status == 'otp') {
        //     $result = $gs->checkOTP($device->data, $this->data);

        //     if (!$result['status']) {
        //         $detail['sync_status'] = 'otp_fail';
        //     } else {
        //         $detail['sync_status'] = 'need_mpin';
        //     }

        //     $detail['sync_at'] = now();
        //     $account->update(['detail' => $detail]);
        // }
    }
}
