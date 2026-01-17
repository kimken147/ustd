<?php

namespace App\Jobs;

use App\Exceptions\PaymayaResponseError;
use App\Model\Channel;
use App\Model\Transaction;
use App\Services\Maya\MayaLoginService;
use App\Services\Maya\MayaService;
use App\Services\Maya\PayMayaApiService;
use App\Services\Transaction\DaiFuService;
use App\Services\TransactionNoteService;
use App\Utils\TransactionUtil;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MayaDaifu implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Transaction $transaction;
    private string $status;
    private array $data;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        Transaction $transaction,
        $status = "init",
        $data = []
    ) {
        $this->status = $status;
        $this->transaction = $transaction;
        $this->data = $data;
        $this->queue = config("queue.queue-priority.high");
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(
        DaiFuService $daifuService,
        MayaService $mayaService,
        MayaLoginService $mayaLoginService,
        TransactionNoteService $transactionNoteService,
        TransactionUtil $transactionUtil
    ) {
        if ($this->attempts() > 1) {
            $this->delete();
            return false;
        }
        Log::debug(
            "Maya代付 order number " . $this->transaction->order_number,
            [
                "transaction" => $this->transaction,
                "status" => $this->status,
            ]
        );

        if (!$daifuService->checkAutoIsValid("ph")) {
            return;
        }

        $transaction = $this->transaction->refresh();
        if ($transaction->status != Transaction::STATUS_PAYING) {
            return;
        }

        $account = $transaction->toChannelAccount;
        if (!isset($account["account"]) || !isset($account->detail["mpin"])) {
            return;
        }
        if (!$account->is_auto) {
            return;
        }

        $fromChannelAccount = $transaction->from_channel_account;
        $fromData = $transaction->to_channel_account;
        $toMpin = $account["detail"]["mpin"];
        $bankName = $fromChannelAccount["bank_name"];
        $isMaya =
            isset($fromChannelAccount["account"]) ||
            strtoupper($bankName) == strtoupper(Channel::CODE_MAYA) ||
            strtoupper($bankName) == strtoupper("PayMaya / Maya Wallet");

        if (!$isMaya) {
            $message = "目前不支持maya跨行";
            $transactionNoteService->create(
                $transaction->id,
                "目前不支持maya跨行"
            );
            $transactionUtil->markAsFailed($transaction, null, $message, false);
            return;
        }

        if ($this->status == "init") {
            try {
                $transactionNoteService->create($transaction->id, "Login.");
                $result = $mayaLoginService->apiLogin(
                    $account["account"],
                    $toMpin
                );
                $fromData["status"] = "need_otp";
                $transactionNoteService->create($transaction->id, "Wait OTP.");
            } catch (\Throwable $th) {
                $fromData["status"] = "mpin_fail";
                $transactionNoteService->create(
                    $transaction->id,
                    "Login failed."
                );
            }
            $fromData["expiresChallengeId"] = $result["expiresChallengeId"];
            $fromData["accessToken"] = $result["accessToken"];

            $transaction->to_channel_account = $fromData;
            $transaction->save();
        }

        if ($this->status == "need_otp") {
            if (
                isset($fromData["expiresChallengeId"]) &&
                isset($this->data["otp"]) &&
                isset($fromData["accessToken"])
            ) {
                Log::debug(
                    "Maya: start transfer order number: " .
                        $transaction->order_number
                );
                $expiresChallengeId = $fromData["expiresChallengeId"];
                $otp = $this->data["otp"];
                $accessToken = $fromData["accessToken"];

                $receiver = Str::padLeft(
                    $fromChannelAccount["account"] ??
                        $fromChannelAccount["bank_card_number"],
                    11,
                    0
                );
                try {
                    $transactionNoteService->create(
                        $transaction->id,
                        "OTP login."
                    );
                    $profile = $mayaLoginService->apiLoginNext(
                        $expiresChallengeId,
                        $otp,
                        $accessToken
                    );
                } catch (\Throwable $th) {
                    $fromData["status"] = "otp_fail";
                    $transactionNoteService->create(
                        $transaction->id,
                        "OTP login failed."
                    );
                    $transaction->to_channel_account = $fromData;
                    $transaction->save();
                    return;
                }

                $fromData["status"] = "paying";
                $transaction->to_channel_account = $fromData;
                $transaction->save();

                try {
                    if ($isMaya) {
                        $transactionNoteService->create(
                            $transaction->id,
                            "Tranfer to maya account."
                        );
                        $transferId = $mayaLoginService->createP2pTransfer(
                            $receiver,
                            $transaction->amount,
                            $accessToken,
                            $profile["token"],
                            $profile["profile"]["first_name"] .
                                " " .
                                $profile["profile"]["last_name"]
                        );
                        $result = $mayaLoginService->executeP2pTransfer(
                            $transferId,
                            $accessToken,
                            $profile["token"]
                        );
                        // $mayaService->syncAccount(
                        //     $account->id,
                        //     $accessToken,
                        //     $profile["token"]
                        // );
                        $refNo = $result["request_reference_no"];
                        $transaction->refresh();
                        $transaction->_search1 = $refNo;
                        $transaction->to_channel_account = $fromData;
                        $transactionNoteService->create(
                            $transaction->id,
                            "Ref No. " . $refNo
                        );
                        $transactionUtil->markAsSuccess(
                            $transaction,
                            null,
                            true,
                            false,
                            false
                        );
                    }
                } catch (PaymayaResponseError $e) {
                    $transactionNoteService->create(
                        $transaction->id,
                        "Trasfer failed."
                    );
                    $fromData["status"] = "transfer failed.";
                    $transaction->to_channel_account = $fromData;
                    $message = $mayaService->getMayaServiceExceptionMessage($e);
                    Log::error(
                        "Maya transaction error. order number: " .
                            $transaction->order_number,
                        [
                            "message" => $message,
                        ]
                    );
                    $transactionNoteService->create($transaction->id, $message);
                } catch (Exception $e) {
                    Log::error(
                        "Maya transaction error. order number: " .
                            $transaction->order_number,
                        [
                            "message" => $e->getMessage(),
                        ]
                    );
                    $fromData["status"] = "transfer failed.";
                    $transaction->to_channel_account = $fromData;
                    $transactionNoteService->create(
                        $transaction->id,
                        $e->getMessage()
                    );
                } finally {
                    $transaction->save();
                }
            }
        }
    }
}
