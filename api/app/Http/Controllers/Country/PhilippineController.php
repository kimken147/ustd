<?php

namespace App\Http\Controllers\Country;

use Carbon\Carbon;
use App\Http\Controllers\Controller;
use App\Jobs\MayaChangeEmail;
use App\Model\UserChannelAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use App\Model\Transaction;
use App\Model\TransactionNote;
use App\Model\Channel;
use App\Utils\TransactionUtil;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redis;
use App\Jobs\MayaChangePassword;
use App\Jobs\MayaDaifu;
use App\Utils\GcashService;
use App\Model\Bank;
use App\Model\MemberDevice;
use App\Model\Notification as NotificationModal;
use Illuminate\Support\Arr;
use App\Notifications\GcashAccount;
use Illuminate\Support\Facades\Notification;
use App\Jobs\SyncGcashAccount;
use App\Jobs\SyncMayaAccountJob;

class PhilippineController extends Controller
{
    public function storeGcashData(
        Request $request,
        TransactionUtil $transactionUtil
    ) {
        Log::debug(__METHOD__, [$request->all()]);

        $transaction = Transaction::find($request->order_number);
        abort_if(!$transaction, Response::HTTP_NOT_FOUND, "Order not exists");

        $fromChannelAccount = $transaction->from_channel_account;
        $receiverAccount = Str::padLeft(
            $fromChannelAccount["account"] ??
                $fromChannelAccount["bank_card_number"],
            11,
            0
        );
        $isGcashAccount =
            isset($fromChannelAccount["account"]) ||
            in_array($fromChannelAccount["bank_name"], [
                "GCASH",
                "GCash",
                "Gcash",
                "gcash",
            ]);

        $gcashData = $request->only([
            "mobile_number",
            "otp",
            "mpin",
            "click_pay",
            "otp2",
        ]);

        $data = $transaction->to_channel_account;
        $msg = "";
        $notes = [];

        foreach ($gcashData as $key => $value) {
            if ($key == "mobile_number") {
                $value = Str::padLeft($value, 11, 0);
            }
            $data[$key] = $value;
        }

        $gs = new GcashService();

        if ($request->has("mobile_number")) {
            $mobile = Str::padLeft($data["mobile_number"], 11, 0);
        } else {
            $mobile = Str::padLeft(
                $transaction->to_channel_account["mobile_number"],
                11,
                0
            );
        }

        $deviceExists = MemberDevice::where("device", $mobile)->exists();
        $device = $gs->makeDevice($mobile);

        if ($request->has("mobile_number")) {
            try {
                $notes[] = "Enter mobile number";
                $result = $gs->handshake($device->data);
                $device->refresh();

                if (!$deviceExists) {
                    $requestOtpResult = $gs->makeOTP($device->data); // 基本上都會成功
                    $data["status"] = "otp_sending";
                    if (!$requestOtpResult) {
                        $notes[] = "Request OTP fail";
                    } else {
                        $notes[] = "Wait OTP";
                    }
                } else {
                    $data["status"] = "need_mpin";
                }
            } catch (\Exception $e) {
                $notes[] = "Handshake fail";
                Log::error(__METHOD__, compact("transaction", "e"));
                $status = "handshake_fail";
            }
        }

        if ($request->has("resend_otp")) {
            if (in_array($data["status"], ["need_otp", "otp_sending"])) {
                $notes[] = "Resend OTP";
                $result = $gs->makeOTP($device->data);
                $data["status"] = "otp_sending";
            } else {
                $notes[] = "Resend OTP2";
                $result = $gs->pay2($device->data);
                $data["status"] = "otp2_sending";
            }
        }

        if ($request->has("otp")) {
            $notes[] = "Enter OTP";
            unset($data["otp_fail"]);
            $data["status"] = "otp_processing";
            $transaction->update(["to_channel_account" => $data]); // 更新成otp進行中

            $result = $gs->checkOTP($device->data, $request->otp);

            if (!$result["status"]) {
                // otp 錯誤
                unset($data["otp"]);
                $data["otp_fail"] = true;
                $data["status"] = "need_otp";
                $notes[] = "OTP1 fail";
            } else {
                $data["status"] = "need_mpin";
            }
        }

        if ($request->has("mpin")) {
            $notes[] = "Enter MPIN";
            unset($data["mpin_fail"]);
            $data["status"] = "mpin_processing";
            $transaction->update(["to_channel_account" => $data]); // 更新成mpin進行中

            $result = $gs->mpinLogin($device->data, $request->mpin);
            $device->refresh();

            if ($result["status"] == 1) {
                // mpin 成功
                $data["status"] = "check_tx";

                $gs->getDetails($device->data);

                if ($request->has("update_balance")) {
                    $device->refresh();
                    $balance_data = $gs->getBalance($device->data);

                    if ($balance = data_get($balance_data, "detail.balance")) {
                        $transaction->toChannelAccount->updateBalanceByUser(
                            $balance
                        );
                    }
                }
            } elseif ($result["status"] == 2) {
                // mpin 驗證失敗
                unset($data["mpin"]);
                if (
                    isset($result["message"]["response"]["body"]["code"]) &&
                    $result["message"]["response"]["body"]["code"] ==
                        "GE15199993302013"
                ) {
                    // 帳戶無法使用
                    $notes[] =
                        "Your account is unavailable. Please try anther account.";
                } else {
                    $data["mpin_fail"] = true;
                    $data["status"] = "need_mpin";
                    $notes[] = "MPIN fail";
                }
            } elseif ($result["status"] == 3) {
                // 因裝置變更或失效需要回到輸入 OTP
                $gs->makeOTP($device->data); // 基本上都會成功
                unset($data["mpin"]);
                $notes[] = "Wait OTP";
                $data["status"] = "otp_sending";
            } elseif ($result["status"] == 4) {
                MemberDevice::where("device", $mobile)->delete();
                $notes[] = "Device fail";
                $data["status"] = "reset_device";
            }
        }

        $reasons = [
            "Login on your phone" => "搶登或騙分，請關注",
            "do not have enough balance" => "會員餘額不足",
            'recipient\'s monthly incoming limit' => "收款帳號限額已到",
            "daily outgoing transaction" => "會員餘額不足",
            "Unable to get user information" => "收款帳號有誤",
            "available wallet size" => "收款帳號錢包餘額過多",
        ];

        if ($request->has("click_pay")) {
            $notes[] = "Click Pay";
            $data["status"] = "pay_processing";

            $canPay = true;
            if ($request->has("get_user_info")) {
                $info = $gs->getUserInfo($device->data, $receiverAccount);

                $fromChannelAccount["receiver_name"] = Arr::get(
                    $info,
                    "info.name",
                    ""
                );
                $transaction->from_channel_account = $fromChannelAccount;
                $transaction->save();

                $verifyAccount =
                    in_array(
                        "verify_daifu_account",
                        $transaction->from->tags ?? []
                    ) && !Arr::get($data, "igonre_verify");
                if ($verifyAccount) {
                    if (
                        !$info["status"] ||
                        Arr::get($info, "info.kycLevel", 0) != 3
                    ) {
                        $data["status"] = "account_verify_fail";
                        $notes[] = "Account not verified";
                        $canPay = false;
                    }
                }
            }

            if ($canPay) {
                if ($isGcashAccount) {
                    $result = $gs->pay(
                        $device->data,
                        $receiverAccount,
                        $transaction->floating_amount
                    );
                    $device->refresh();
                } else {
                    $bank = Bank::where(
                        "name",
                        $fromChannelAccount["bank_name"]
                    )
                        ->orWhere("code", $fromChannelAccount["bank_name"])
                        ->first();
                    $bankInfo = [
                        "bank_name" => $bank->name,
                        "bank_code" => $bank->code,
                    ];
                    $result = $gs->pay_to_bank(
                        $device->data,
                        $receiverAccount,
                        $transaction->floating_amount,
                        $bankInfo
                    );
                    $device->refresh();
                }

                if (!$result) {
                    return response()->json(["status" => false]);
                }

                Log::debug(
                    "storeGcashData pay result",
                    compact("transaction", "result")
                );
                if ($result["status"] == 1) {
                    // 订单成功

                    $transactionUtil->markAsSuccess(
                        $transaction,
                        null,
                        true,
                        false,
                        false
                    );
                    $transaction->refresh();
                    $notes[] = "Ref No. " . $result["transId"];
                    $transaction->_search1 = $result["transId"];
                } elseif ($result["status"] == 2) {
                    // 需要 otp2
                    if ($isGcashAccount) {
                        $gs->pay2($device->data);
                    } else {
                        $gs->pay2_to_bank($device->data);
                    }
                    $data["status"] = "need_otp2";
                    $notes[] = "Wait OTP2";
                } elseif ($result["status"] == -1) {
                    // msg 是空的，直接失败，避免重复支付
                    $msg = $this->getMessage($result);
                    $data["error"] = $msg;
                    $data["status"] = "pay_fail";
                    $notes[] = $msg;
                } else {
                    // 订单失敗
                    $msg = $this->getMessage($result);
                    $data["error"] = $msg;
                    $data["status"] = "pay_fail";
                    $notes[] = $msg;

                    foreach ($reasons as $eng => $chi) {
                        if (Str::contains($msg, $eng)) {
                            $data["reason"] = $chi;
                            break;
                        }
                    }
                }
            }
        }

        if ($request->has("otp2")) {
            $notes[] = "Enter OTP2";
            unset($data["otp2_fail"]);
            $data["status"] = "otp2_processing";
            $transaction->update(["to_channel_account" => $data]); // 更新成otp2進行中

            if ($isGcashAccount) {
                $result = $gs->pay3(
                    $device->data,
                    $request->otp2,
                    $receiverAccount,
                    $transaction->floating_amount
                );
            } else {
                $bank = Bank::where("name", $fromChannelAccount["bank_name"])
                    ->orWhere("code", $fromChannelAccount["bank_name"])
                    ->first();
                $bankInfo = [
                    "bank_name" => $bank->name,
                    "bank_code" => $bank->code,
                ];
                $result = $gs->pay3_to_bank(
                    $device->data,
                    $request->otp2,
                    $receiverAccount,
                    $transaction->floating_amount,
                    $bankInfo
                );
            }
            Log::debug(
                "storeGcashData pay3 result",
                compact("transaction", "result")
            );

            if ($result["status"] == 1) {
                // 订单成功
                $transactionUtil->markAsSuccess(
                    $transaction,
                    null,
                    true,
                    false,
                    false
                );
                $transaction->refresh();
                $device->refresh();

                $notes[] = "Ref No. " . $device->data->transId;
                $transaction->_search1 = $device->data->transId;
            } elseif ($result["status"] == 2) {
                // otp2 错误
                unset($data["otp2"]);
                $data["otp2_fail"] = true;
                $notes[] = "OTP2 fail";
                $data["status"] = "need_otp2";
            } elseif ($result["status"] == 3) {
                // 订单失敗
                $msg = $this->getMessage($result);
                $data["error"] = $msg;
                $notes[] = $msg;

                foreach ($reasons as $eng => $chi) {
                    if (Str::contains($msg, $eng)) {
                        $data["reason"] = $chi;
                        break;
                    }
                }

                if (isset($msg) && !empty($msg)) {
                    if ($msg == "cheater") {
                        $data["reason"] =
                            "騙分或延遲出款：" . $result["ref"] ?? "";
                    }
                }
            }
        }

        $shouldFail = false;
        if (isset($data["error"]) && !empty($data["error"])) {
            $problem = "";
            if (Str::contains($data["error"], "monthly incoming limit")) {
                if (
                    $transaction->isWithdraw() ||
                    $transaction->isInternalTransfer()
                ) {
                    $shouldFail = true;
                }
                if ($transaction->fromChannelAccount) {
                    $problem = "額度已滿，請換號";
                }
            }
            if (Str::contains($data["error"], "available wallet size")) {
                if (
                    $transaction->isWithdraw() ||
                    $transaction->isInternalTransfer()
                ) {
                    $shouldFail = true;
                }
                if ($transaction->fromChannelAccount) {
                    $problem = "餘額過多，請換號";
                }
            }
            if (Str::contains($data["error"], "Login on your phone")) {
                if ($transaction->fromChannelAccount) {
                    $problem = "設備重複登入";
                }
            }
            if (Str::contains($data["error"], "Please expect an SMS")) {
                if ($transaction->fromChannelAccount) {
                    $key = "gcash:error:notify:{$fromChannelAccount["account"]}";
                    if (Redis::get($key) >= 3) {
                        $problem = "疑似風控，請檢查";
                        Redis::del($key);
                    } else {
                        Redis::set($key, 1, "EX", 60, "NX");
                        Redis::incr($key);
                    }
                }
            }
            if (Str::contains($data["error"], "get user information")) {
                if ($transaction->fromChannelAccount) {
                    unset($data["error"]);
                    $data["status"] = "check_tx";
                }
            }
            if (Str::contains($data["error"], "For your protection")) {
                if ($transaction->fromChannelAccount) {
                    unset($data["error"]);
                    $data["status"] = "check_tx";
                }
            }

            if (!empty($problem)) {
                $account =
                    $transaction->fromChannelAccount->name .
                    " " .
                    $transaction->fromChannelAccount->account;
                Notification::route(
                    "telegram",
                    config("services.telegram-bot-api.system-admin-group-id")
                )->notify(new GcashAccount($account, $problem));
            }
        }

        $transaction->to_channel_account = $data;
        $transaction->save();

        if ($shouldFail) {
            $transactionUtil->markAsFailed($transaction, null, "", false);
        }

        foreach ($notes as $note) {
            TransactionNote::create([
                "transaction_id" => $transaction->id,
                "user_id" => 0,
                "note" => $note,
            ]);
        }

        return response()->json(["status" => true]);
    }

    public function getMessage($result)
    {
        $msg = "";

        if (isset($result["message"]["response"]["body"]["message"])) {
            $msg = $result["message"]["response"]["body"]["message"];
        } elseif (isset($result["response"]["body"]["message"])) {
            $msg = $result["response"]["body"]["message"];
        } elseif (isset($result["message"])) {
            $msg = $result["message"];
        }

        return $msg;
    }

    public function modemSms(Request $request)
    {
        $channelCode = null;
        $mobileNumber = Str::padLeft($request->phoneNumber, 11, 0);
        $msg = mb_convert_encoding(
            base64_decode($request->smsContent),
            "utf8",
            "gb18030"
        );
        Log::debug("ModemSms Phone number: $mobileNumber is received => ", [
            "data" => $request->all(),
        ]);

        NotificationModal::create([
            "device_id" => 0,
            "mobile" => $mobileNumber,
            "notification" => $msg,
        ]);

        // Maya login message
        if (str_contains($msg, "To continue your login, your OTP is")) {
            preg_match(
                "/To continue your login, your OTP is (?<otp>\d{6})/",
                $msg,
                $matches
            );
            $channelCode = Channel::CODE_MAYA;
        } else {
            preg_match(
                "/.*[authentication code|OTP][ ay | is ]+(?<otp>\d{6})/",
                $msg,
                $matches
            );
            $channelCode = Channel::CODE_GCASH;
        }
        // 出款
        $transaction = Transaction::where("status", Transaction::STATUS_PAYING)
            ->whereIn("type", [
                Transaction::TYPE_PAUFEN_WITHDRAW,
                Transaction::TYPE_INTERNAL_TRANSFER,
            ])
            ->where("matched_at", ">=", now()->subMinutes(5))
            ->whereIn("to_channel_account->account", [
                $request->phoneNumber,
                $mobileNumber,
            ])
            ->where("to_channel_account->channel_code", $channelCode)
            ->first();

        if ($transaction && isset($transaction->to_channel_account["status"])) {
            $status = $transaction->to_channel_account["status"];
            if (isset($matches["otp"]) && $status == "need_otp") {
                $otp = $matches["otp"];
                Log::debug(
                    "Maya Account 出款, order number: " .
                        $transaction->order_number,
                    [
                        "otp" => $otp,
                    ]
                );
                MayaDaifu::dispatch($transaction, "need_otp", ["otp" => $otp]);
            }
        } else {
            $account = UserChannelAccount::whereIn("channel_code", [
                $channelCode,
            ])
                ->whereIn("account", [$request->phoneNumber, $mobileNumber])
                ->where("detail->sync_status", "need_otp")
                ->first();
        }

        // if ($transaction && isset($transaction->to_channel_account['status'])) {
        //     Log::info(__METHOD__, compact('mobileNumber', 'msg', 'transaction'));

        //     $status = $transaction->to_channel_account['status'];
        //     $request->request->add(['order_number' => $transaction->id]);

        //     if (isset($matches['otp'])) {
        //         $otp = $matches['otp'];

        //         if (in_array($status, ['need_otp', 'otp_sending'])) {
        //             $request->request->add(['otp' => $otp]);
        //         }
        //         if (in_array($status, ['need_otp2', 'otp2_sending'])) {
        //             $request->request->add(['otp2' => $otp]);
        //         }

        //         $transactionUtil = app(TransactionUtil::class);
        //         $this->storeGcashData($request, $transactionUtil);
        //     }
        // }

        $query = UserChannelAccount::whereIn("account", [
            $request->phoneNumber,
            $mobileNumber,
        ])->where(function ($query) {
            $query
                ->where("detail->sync_status", "need_otp")
                ->orWhere("detail->password_status", "need_otp")
                ->orWhere("detail->email_status", "need_otp");
        });

        if ($channelCode == Channel::CODE_MAYA) {
            $account = $query
                ->where("channel_code", Channel::CODE_MAYA)
                ->first();
            if ($account) {
                if (
                    isset($account->detail["sync_status"]) &&
                    $account->detail["sync_status"] == "need_otp"
                ) {
                    if (isset($matches["otp"])) {
                        SyncMayaAccountJob::dispatch($account->id, "need_otp", [
                            "otp" => $matches["otp"],
                        ]);
                    }
                } elseif (
                    isset($account->detail["password_status"]) &&
                    $account->detail["password_status"] == "need_otp"
                ) {
                    if (isset($matches["otp"])) {
                        MayaChangePassword::dispatch($account, "need_otp", [
                            "otp" => $matches["otp"],
                        ]);
                    }
                } elseif (
                    isset($account->detail["email_status"]) &&
                    $account->detail["email_status"] == "need_otp"
                ) {
                    if (isset($matches["otp"])) {
                        MayaChangeEmail::dispatch($account, "need_otp", [
                            "otp" => $matches["otp"],
                        ]);
                    }
                }
            }
        } else {
            $account = UserChannelAccount::whereIn("channel_code", [
                Channel::CODE_GCASH,
            ])
                ->whereIn("account", [$request->phoneNumber, $mobileNumber])
                ->where("detail->sync_status", "need_otp")
                ->first();
            if ($account && isset($account->detail["sync_status"])) {
                if (isset($matches["otp"])) {
                    SyncGcashAccount::dispatch(
                        $account->id,
                        "otp",
                        $matches["otp"]
                    );
                }
            }
        }

        // 銀行回冲
        // preg_match('/^Upon review.*credited P(?<amount>\d+.\d+).* Ref\. No\. (?<refno>\d+.\d+)\./', $msg, $refundMatches);
        // if (isset($refundMatches['amount']) && isset($refundMatches['refno'])) {
        //     $refundTransaction = Transaction::whereIn('type', [Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_INTERNAL_TRANSFER])
        //         ->where('_search1', $refundMatches['refno'])
        //         ->first();

        //     if (data_get($refundTransaction->to_channel_account, 'account') == $mobileNumber) {
        //         TransactionNote::create([
        //             'transaction_id' => $refundTransaction->id,
        //             'user_id' => 0,
        //             'note' => $msg ?? ''
        //         ]);
        //     }
        // }

        return response('{"nResult":200}');
    }

    public function successPage($id)
    {
        $transaction = Transaction::firstWhere("system_order_number", $id);
        $from = $transaction->from_channel_account;
        $bank = Bank::where("name", $from["bank_name"])
            ->orWhere("code", $from["bank_name"])
            ->first();
        $channel = Channel::where("code", Channel::CODE_GCASH)->first();
        $version = $channel->cashier_version;

        abort_if(
            !$transaction ||
                !$bank ||
                !in_array($transaction->status, [
                    Transaction::STATUS_SUCCESS,
                    Transaction::STATUS_MANUAL_SUCCESS,
                ]),
            Response::HTTP_NOT_FOUND
        );

        if ($version === "v2") {
            $type = $bank->name === "GCash" ? "gcash" : "bank";
            return view(
                "v1.transactions.ph.gcash.{$version}.success-page.{$type}",
                compact("transaction", "bank")
            );
        } else {
            return view(
                "v1.transactions.ph.gcash.{$version}.success-page",
                compact("transaction", "bank")
            );
        }
    }
}
