<?php

namespace App\Http\Controllers\ThirdParty;

use App\Exceptions\RaceConditionException;
use App\Notifications\AgencyWithdrawFailed;
use App\Notifications\AgencyWithdrawInsufficientAvailableBalance;
use App\Utils\InsufficientAvailableBalance;
use App\Http\Controllers\Controller;
use App\Http\Resources\ThirdParty\Withdraw;
use App\Models\FeatureToggle;
use App\Models\Transaction;
use App\Models\TransactionNote;
use App\Models\User;
use App\Models\Bank;
use App\Models\Wallet;
use App\Models\BannedRealname;
use App\Repository\FeatureToggleRepository;
use App\Utils\BankCardTransferObject;
use App\Utils\BCMathUtil;
use App\Utils\FloatUtil;
use App\Utils\ThirdPartyResponseUtil;
use App\Utils\TransactionFactory;
use App\Utils\WalletUtil;
use App\Utils\WhitelistedIpManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;
use App\Utils\UsdtUtil;

use App\Models\ThirdChannel;
use App\Models\MerchantThirdChannel;
use App\Utils\TransactionUtil;
use App\Models\Channel;
use Illuminate\Support\Facades\Notification;

class AgencyWithdrawController extends Controller
{

    public function store(
        Request                 $request,
        TransactionFactory      $transactionFactory,
        WalletUtil              $wallet,
        BCMathUtil              $bcMath,
        FeatureToggleRepository $featureToggleRepository,
        BankCardTransferObject  $bankCardTransferObject,
        WhitelistedIpManager    $whitelistedIpManager,
        FloatUtil               $floatUtil,
        TransactionUtil         $transactionUtil,
        UsdtUtil                $usdtUtil
    )
    {
        abort_if($request->hasHeader('X-Token') && $request->header('X-Token') != config('app.x_token'), Response::HTTP_BAD_REQUEST);

        $requiredAttributes = [
            'username',
            'amount',
            'bank_card_number',
            'bank_name',
            'order_number',
            'sign'
        ];

        if (BannedRealname::where(['realname' => $request->bank_card_holder_name, 'type' => BannedRealname::TYPE_WITHDRAW])->exists()) {
            return response()->json([
                'http_status_code' => Response::HTTP_FORBIDDEN,
                'error_code' => ThirdPartyResponseUtil::ERROR_CODE_FORBIDDEN_NAME,
                'message' => __('common.Card holder access forbidden')
            ]);
        }

        if ($bcMath->lt($request->input('amount', 0), 1)) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code' => ThirdPartyResponseUtil::ERROR_CODE_INVALID_MIN_AMOUNT,
                'message' => __('common.Amount below minimum: :amount', ['amount' => 1])
            ]);
        }

        if (
            $featureToggleRepository->enabled(FeatureToggle::NO_FLOAT_IN_WITHDRAWS)
            && $floatUtil->numberHasFloat($request->input('amount'))
        ) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code' => ThirdPartyResponseUtil::ERROR_CODE_INVALID_MIN_AMOUNT,
                'message' => __('common.Decimal amount not allowed')
            ]);
        }

        foreach ($requiredAttributes as $requiredAttribute) {
            if (empty($request->$requiredAttribute)) {
                return response()->json([
                    'http_status_code' => Response::HTTP_BAD_REQUEST,
                    'error_code' => ThirdPartyResponseUtil::ERROR_CODE_BAD_REQUEST,
                    'message' => __('common.Information is incorrect: :attribute', ['attribute' => $requiredAttribute]),
                ]);
            }
        }

        /** @var User|null $merchant */
        $merchant = User::where([
            ['username', $request->username],
            ['role', User::ROLE_MERCHANT]
        ])->first();

        if (!$merchant) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code' => ThirdPartyResponseUtil::ERROR_CODE_USER_NOT_FOUND,
                'message' => __('common.User not found'),
            ]);
        }

        $parameters = $request->except('sign');

        ksort($parameters);

        $sign = md5(urldecode(http_build_query($parameters) . '&secret_key=' . $merchant->secret_key));

        if (strcasecmp($sign, $request->sign)) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code' => ThirdPartyResponseUtil::ERROR_CODE_INVALID_SIGN,
                'message' => __('common.Signature error'),
            ]);
        }

        if ($whitelistedIpManager->isNotAllowedToUseThirdPartyApi($merchant, $request)) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code' => ThirdPartyResponseUtil::ERROR_CODE_INVALID_IP,
                'message' => __('common.Please contact admin to add IP to whitelist'),
            ]);
        }

        $duplicates = Transaction::whereIn('type', [
            Transaction::TYPE_PAUFEN_WITHDRAW,
            Transaction::TYPE_NORMAL_WITHDRAW
        ])
            ->where(['from_id' => $merchant->getKey(), 'order_number' => $request->input('order_number')])
            ->exists();

        if ($duplicates) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code' => ThirdPartyResponseUtil::ERROR_CODE_DUPLICATE_ORDER_NUMBER,
                'message' => __('common.Duplicate number'),
            ]);
        }

        if (
            !$featureToggleRepository->enabled(FeatureToggle::ENABLE_AGENCY_WITHDRAW)
            || !$merchant->agency_withdraw_enable
        ) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code' => ThirdPartyResponseUtil::ERROR_CODE_AGENCY_WITHDRAW_DISABLED,
                'message' => __('user.Agency withdraw disabled')
            ]);
        }

        $bank = Bank::where('name', $request->input('bank_name'))->orWhere('code', $request->input('bank_name'))->first();
        $daifuBanks = Channel::where('type', Channel::TYPE_DEPOSIT_WITHDRAW)->get()->map(function ($channel) {
            return $channel->deposit_account_fields['merchant_can_withdraw_banks'] ?? [];
        })->flatten();

        if ($daifuBanks->isEmpty()) {
            $inDaifuBank = $bank;
        } else {
            $inDaifuBank = $daifuBanks->map(function ($bank) {
                return strtoupper($bank);
            })->contains(strtoupper($request->input('bank_name')));
        }

        if ($featureToggleRepository->enabled(FeatureToggle::WITHDRAW_BANK_NAME_MAPPING) && !$inDaifuBank) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code' => ThirdPartyResponseUtil::ERROR_CODE_BANK_NOT_FOUND,
                'message' => __('common.Bank not supported')
            ]);
        }

        if (
            $bcMath->gtZero($merchant->wallet->agency_withdraw_min_amount ?? 0)
            && $bcMath->lt($request->input('amount'), $merchant->wallet->agency_withdraw_min_amount)
        ) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code' => ThirdPartyResponseUtil::ERROR_CODE_INVALID_MIN_AMOUNT,
                'message' => __('common.Amount below minimum: :amount', ['amount' => $merchant->wallet->agency_withdraw_min_amount])
            ]);
        }

        if (
            $bcMath->gtZero($merchant->wallet->agency_withdraw_max_amount ?? 0)
            && $bcMath->gt($request->input('amount'), $merchant->wallet->agency_withdraw_max_amount)
        ) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code' => ThirdPartyResponseUtil::ERROR_CODE_INVALID_MAX_AMOUNT,
                'message' => __('common.Amount above maximum: :amount', ['amount' => $merchant->wallet->agency_withdraw_max_amount])
            ]);
        }

        $needExtraWithdrawFee = $bank ? $bank->needExtraWithdrawFee : false;
        $totalCost = $merchant->wallet->calculateTotalAgencyWithdrawAmount($request->input('amount'), $needExtraWithdrawFee);

        if ($bcMath->lt($merchant->wallet->available_balance, $totalCost)) {
            return response()->json([
                "http_status_code" => Response::HTTP_BAD_REQUEST,
                "error_code" =>
                    ThirdPartyResponseUtil::ERROR_CODE_INSUFFICIENT_BALANCE,
                "message" => __("wallet.InsufficientAvailableBalance"),
            ]);
        }

        $paufenAgencyWithdrawFeatureEnabled = ($featureToggleRepository->enabled(FeatureToggle::FEATURE_PAUFEN_WITHDRAW_MATCHING_TIMED_OUT)
            && $merchant->paufen_agency_withdraw_enable
        );

        $bankCard = $bankCardTransferObject->plain(
            $request->input("bank_name"),
            $request->input("bank_card_number"),
            $request->input("bank_card_holder_name") ?? "",
            $request->input("bank_province") ?? "",
            $request->input("bank_city") ?? ""
        );

        $amount = $request->amount;

        $transactionFactory = $transactionFactory
            ->bankCard($bankCard)
            ->orderNumber($request->input("order_number"))
            ->notifyUrl($request->input("notify_url"))
            ->amount($request->input("amount"))
            ->subType(Transaction::SUB_TYPE_AGENCY_WITHDRAW);

        $withdrawMethod = $paufenAgencyWithdrawFeatureEnabled
            ? "paufenWithdrawFrom"
            : "normalWithdrawFrom"; // 如果啟用跑分代付則使用跑分提現，否則一般提現

        try {
            $transaction = DB::transaction(function () use ($transactionFactory, $merchant, $wallet, $totalCost, $withdrawMethod) {
                if (!$merchant->third_channel_enable) {
                    $transaction = $transactionFactory->$withdrawMethod(
                        $merchant,
                        true
                    );
                    $wallet->withdraw(
                        $merchant->wallet,
                        $totalCost,
                        $transaction->order_number,
                        "withdraw"
                    );
                } else {
                    $transaction = $transactionFactory->createThirdchannel($merchant);
                    $wallet->withdraw(
                        $merchant->wallet,
                        $totalCost,
                        $transaction->order_number,
                        "withdraw"
                    );
                    $transactionFactory->createWithdrawMerchantFees($transaction, $merchant, true);
                }
                $transaction->refresh()->load(['from', 'transactionFees', "fromChannelAccount"]);
                return $transaction;
            });
        } catch (RaceConditionException $raceConditionException) {
            Log::error($request->order_number . " 代付錢包扣款衝突");
            Notification::route(
                "telegram",
                config("services.telegram-bot-api.system-admin-group-id")
            )->notify(
                new AgencyWithdrawFailed(
                    $request->input("order_number"),
                    $merchant->name,
                    $request->amount
                )
            );
            return response()->json([
                "http_status_code" => Response::HTTP_BAD_REQUEST,
                "error_code" =>
                    ThirdPartyResponseUtil::ERROR_CODE_RACE_CONDITION,
                "message" => __("common.Conflict! Please try again later"),
            ]);
        } catch (InsufficientAvailableBalance $raceConditionException) {
            Log::error($request->order_number . " 代付錢包扣款後餘額不足衝突");
            Notification::route(
                "telegram",
                config("services.telegram-bot-api.system-admin-group-id")
            )->notify(
                new AgencyWithdrawInsufficientAvailableBalance(
                    $request->input("order_number"),
                    $merchant->name,
                    $request->amount
                )
            );
            return response()->json([
                "http_status_code" => Response::HTTP_BAD_REQUEST,
                "error_code" =>
                    ThirdPartyResponseUtil::ERROR_CODE_INSUFFICIENT_BALANCE,
                "message" => __("wallet.InsufficientAvailableBalance"),
            ]);
        }

        if ($merchant->third_channel_enable) {
            //取得通道列表，之後需要根據 channel code 找到代付通道
            $channelList = MerchantThirdChannel::where(
                "owner_id",
                $merchant->id
            )
                ->where("daifu_min", "<=", $amount)
                ->where("daifu_max", ">=", $amount)
                ->whereHas("thirdChannel", function (Builder $query) use ($amount) {
                    $query
                        ->where("status", ThirdChannel::STATUS_ENABLE)
                        ->where(
                            "type",
                            "!=",
                            ThirdChannel::TYPE_DEPOSIT_ONLY
                        );
                })
                ->with("thirdChannel")
                ->get();

            $failIfThirdFail = $featureToggleRepository->enabled(
                FeatureToggle::IF_THIRDCHANNEL_DAIFU_FIAL_THAN_ORDER_FAIL
            );
            $tryOnce = $featureToggleRepository->enabled(
                FeatureToggle::TRY_NEXT_IF_THIRDCHANNEL_DAIFU_FAIL
            );

            if ($channelList->count() > 0) {
                $channelList = $channelList->filter(function ($channel) use ($amount) {
                    return $amount >= $channel->thirdchannel->auto_daifu_threshold_min
                        && $amount <= $channel->thirdchannel->auto_daifu_threshold;
                })->shuffle();

                if ($channelList->count() === 0) {
                    $this->changeWithdrawMethod($paufenAgencyWithdrawFeatureEnabled, $transactionFactory, $transaction, $merchant);
                    TransactionNote::create([
                        "user_id" => 0,
                        "transaction_id" => $transaction->id,
                        "note" => "无自动推送门槛内的三方可用，请手动推送"
                    ]);
                    if ($failIfThirdFail) {
                        // 有开启三方代付，但是没代付通道则失败
                        $transactionUtil->markAsFailed($transaction, null, "无自动推送门槛内的三方可用，请手动推送", false);
                    }
                } else {
                    if (!$tryOnce) {
                        $channelList = $channelList->take(1);
                    }
                    $lastKey = $channelList->keys()->last();

                    foreach ($channelList as $key => $channel) {
                        Log::debug("{$request->order_number} 请求 {$channel->thirdChannel->class}({$channel->thirdChannel->merchant_id})");

                        $path = "App\ThirdChannel\\" . $channel->thirdChannel->class;
                        $api = new $path();

                        preg_match("/^(?:https?:\/\/)?(?:[^@\n]+@)?(?:www\.)?([^:\/\n?]+)/", $api->daifuUrl, $url);

                        $orderNumber = $channel->thirdChannel->enable_system_order_number ? $transaction->system_order_number : $request->order_number;

                        $data = [
                            "url" => preg_replace("/{$url[1]}/", $channel->thirdChannel->custom_url, $api->daifuUrl),
                            "queryDaifuUrl" => preg_replace("/{$url[1]}/", $channel->thirdChannel->custom_url, $api->queryDaifuUrl),
                            "queryBalanceUrl" => preg_replace("/{$url[1]}/", $channel->thirdChannel->custom_url, $api->queryBalanceUrl),
                            "callback_url" => config("app.url") . "/api/v1/callback/" . $request->order_number,
                            "merchant" => $channel->thirdChannel->merchant_id,
                            "key" => $channel->thirdChannel->key,
                            "key2" => $channel->thirdChannel->key2,
                            "key3" => $channel->thirdChannel->key3,
                            "key4" => $channel->thirdChannel->key4,
                            "key5" => $channel->thirdChannel->key5,
                            "proxy" => $channel->thirdChannel->proxy,
                            "request" => $request,
                            "thirdchannelId" => $channel->thirdChannel->id,
                            'order_number' => $orderNumber,
                            'system_order_number' => $request->order_number,
                        ];

                        if (property_exists($api, "alipayDaifuUrl")) {
                            $data["alipayDaifuUrl"] = preg_replace("/{$url[1]}/", $channel->thirdChannel->custom_url, $api->alipayDaifuUrl);
                        }

                        $balance = $api->queryBalance($data);
                        if ($balance > $amount) {
                            $return_data = $api->sendDaifu($data);
                            $message = $return_data["msg"] ?? "";

                            if ($message) {
                                TransactionNote::create([
                                    "user_id" => 0,
                                    "transaction_id" => $transaction->id,
                                    "note" => "{$channel->thirdChannel->name}: {$return_data['msg']}"
                                ]);
                            }

                            $shouldAssignThirdChannel = $return_data["success"];

                            if (!$shouldAssignThirdChannel) {
                                $query = $api->queryDaifu($data);
                                $shouldAssignThirdChannel = (isset($query["success"]) && $query["success"]) ||
                                    (isset($query["timeout"]) && $query["timeout"]);
                            }

                            if ($shouldAssignThirdChannel) {
                                // 查詢訂單後如果三方有建單，不論成功/失敗/付款中，都先變成三方處理中
                                try {
                                    DB::transaction(function () use ($transactionFactory, $transaction, $channel) {
                                        $transactionFactory->assignThirdChannelV2($transaction, $channel->thirdChannel->id);
                                    }, 5);
                                } catch (\Throwable $th) {
                                    TransactionNote::create([
                                        "user_id" => 0,
                                        "transaction_id" => $transaction->id,
                                        "note" => "{$channel->thirdChannel->name}: 指派三方異常"
                                    ]);
                                    $transaction = $transactionFactory->changeToThirdChannelPending($transaction);
                                    break;
                                }
                                try {
                                    DB::transaction(function () use ($transactionFactory, $transaction, $merchant) {
                                        $transactionFactory->createWithdrawThirdFees($transaction, $merchant, true);
                                    }, 5);
                                } catch (\Throwable $th) {
                                    TransactionNote::create([
                                        "user_id" => 0,
                                        "transaction_id" => $transaction->id,
                                        "note" => "{$channel->thirdChannel->name}: 訂單手續費異常"
                                    ]);
                                    $transaction = $transactionFactory->changeToThirdChannelPending($transaction);
                                }
                                break;
                            }
                        } else {
                            Log::debug("{$request->order_number} 请求 {$channel->thirdChannel->class}({$channel->thirdChannel->merchant_id}) 余额不足");
                            $message = "三方余额不足";

                            TransactionNote::create([
                                "user_id" => 0,
                                "transaction_id" => $transaction->id,
                                "note" => "{$channel->thirdChannel->name}: $message"
                            ]);
                        }

                        if ($key == $lastKey) {
                            // 如果所有三方都试完了且订单未成功，则留在原站
                            $this->changeWithdrawMethod($paufenAgencyWithdrawFeatureEnabled, $transactionFactory, $transaction, $merchant);

                            TransactionNote::create([
                                "user_id" => 0,
                                "transaction_id" => $transaction->id,
                                "note" => "无自动推送门槛内的三方可用，请手动推送"
                            ]);

                            if ($failIfThirdFail) {
                                // 三方代付失败则失败
                                $transactionUtil->markAsFailed($transaction, null, $message ?? null, false);
                            }
                        }
                    }
                }
            } else {
                $this->changeWithdrawMethod($paufenAgencyWithdrawFeatureEnabled, $transactionFactory, $transaction, $merchant);
                TransactionNote::create([
                    "user_id" => 0,
                    "transaction_id" => $transaction->id,
                    "note" => "无符合当前代付金额的三方可用，请调整限额设定"
                ]);
                if ($failIfThirdFail) {
                    // 有开启三方代付，但是没代付通道则失败
                    $transactionUtil->markAsFailed($transaction, null, "无符合当前代付金额的三方可用，请调整限额设定", false);
                }
            }
        }

        Cache::put('admin_withdraws_added_at', now(), now()->addSeconds(60));

        return Withdraw::make($transaction)
            ->additional([
                'http_status_code' => 201,
                'message' => __('common.Submit successful'),
            ])
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    private function changeWithdrawMethod($paufenEnabled, $factory, $transaction, $merchant)
    {
        if ($paufenEnabled) {
            $factory->changeToPaufenWithdraw($transaction, $merchant);
        } else {
            $factory->changeToNormalWithdraw($transaction, $merchant);
        }
    }
}
