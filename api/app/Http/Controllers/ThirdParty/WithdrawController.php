<?php

namespace App\Http\Controllers\ThirdParty;

use App\Exceptions\RaceConditionException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ThirdParty\Withdraw;
use App\Models\FeatureToggle;
use App\Models\Transaction;
use App\Models\User;
use App\Models\BannedRealname;
use App\Models\Bank;
use App\Repository\FeatureToggleRepository;
use App\Utils\AmountDisplayTransformer;
use App\Utils\BankCardTransferObject;
use App\Utils\BCMathUtil;
use App\Utils\FloatUtil;
use App\Utils\ThirdPartyResponseUtil;
use App\Utils\TransactionFactory;
use App\Utils\WalletUtil;
use App\Utils\WhitelistedIpManager;
use App\Utils\UsdtUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

//四方
use App\Models\ThirdChannel;
use App\Models\MerchantThirdChannel;
use App\Utils\TransactionUtil;
use App\Models\Channel;

class WithdrawController extends Controller
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
        UsdtUtil                $usdtUtil,
        TransactionUtil         $transactionUtil
    ) {
        abort_if($request->hasHeader('X-Token') && $request->header('X-Token') != config('app.x_token'), Response::HTTP_BAD_REQUEST);

        $requiredAttributes = [
            'username',
            'amount',
            'bank_card_number',
            'bank_name',
            'order_number',
            'sign'
        ];

        abort_if(BannedRealname::where(['realname' => $request->bank_card_holder_name, 'type' => BannedRealname::TYPE_WITHDRAW])->exists(), Response::HTTP_FORBIDDEN, __('common.Card holder access forbidden'));

        foreach ($requiredAttributes as $requiredAttribute) {
            if (empty($request->$requiredAttribute)) {
                return response()->json([
                    'http_status_code' => Response::HTTP_BAD_REQUEST,
                    'error_code' => ThirdPartyResponseUtil::ERROR_CODE_BAD_REQUEST,
                    'message' => __('common.Information is incorrect: :attribute', ['attribute' => $requiredAttribute]),
                ]);
            }
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

        if (!$merchant->withdraw_enable) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code' => ThirdPartyResponseUtil::ERROR_CODE_WITHDRAW_DISABLED,
                'message' => __('user.Withdraw disabled')
            ]);
        }

        $bank = Bank::where('name', $request->input('bank_name'))->orWhere('code', $request->input('bank_name'))->first();
        $daifuBanks = Channel::where('type', Channel::TYPE_DEPOSIT_WITHDRAW)->get()->map(function ($channel) {
            return $channel->deposit_account_fields['merchant_can_withdraw_banks'] ?? [];
        })->flatten();

        if ($daifuBanks->isEmpty()) {
            $inDaifuBank = $bank;
        } else {
            $inDaifuBank = $daifuBanks->contains($request->input('bank_name'));
        }

        if ($featureToggleRepository->enabled(FeatureToggle::WITHDRAW_BANK_NAME_MAPPING) && !$inDaifuBank) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code' => ThirdPartyResponseUtil::ERROR_CODE_BANK_NOT_FOUND,
                'message' => __('common.Bank not supported')
            ]);
        }

        if (
            $bcMath->gtZero($merchant->wallet->withdraw_min_amount ?? 0)
            && $bcMath->lt($request->input('amount'), $merchant->wallet->withdraw_min_amount)
        ) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code' => ThirdPartyResponseUtil::ERROR_CODE_INVALID_MIN_AMOUNT,
                'message' => __('common.Amount below minimum: :amount', ['amount' => $merchant->wallet->withdraw_min_amount])
            ]);
        }

        if (
            $bcMath->gtZero($merchant->wallet->withdraw_max_amount ?? 0)
            && $bcMath->gt($request->input('amount'), $merchant->wallet->withdraw_max_amount)
        ) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code' => ThirdPartyResponseUtil::ERROR_CODE_INVALID_MAX_AMOUNT,
                'message' => __('common.Amount above maximum: :amount', ['amount' => $merchant->wallet->withdraw_max_amount])
            ]);
        }

        $needExtraWithdrawFee = $bank ? $bank->needExtraWithdrawFee : false;
        $totalCost = $merchant->wallet->calculateTotalWithdrawAmount($request->input('amount'), $needExtraWithdrawFee);

        if ($bcMath->lt($merchant->wallet->available_balance, $totalCost)) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code' => ThirdPartyResponseUtil::ERROR_CODE_INSUFFICIENT_BALANCE,
                'message' => __('wallet.InsufficientAvailableBalance')
            ]);
        }

        $paufenWithdrawFeatureEnabled = (
            $featureToggleRepository->enabled(FeatureToggle::FEATURE_PAUFEN_WITHDRAW_MATCHING_TIMED_OUT)
            && $merchant->paufen_withdraw_enable
        );

        try {
            $transaction = DB::transaction(function () use (
                $merchant,
                $request,
                $transactionFactory,
                $wallet,
                $bcMath,
                $featureToggleRepository,
                $paufenWithdrawFeatureEnabled,
                $totalCost,
                $bankCardTransferObject,
                $transactionUtil,
                $usdtUtil
            ) {
                $bankCard = $bankCardTransferObject->plain(
                    $request->bank_name,
                    $request->bank_card_number,
                    $request->bank_card_holder_name ?? '',
                    $request->bank_province ?? '',
                    $request->bank_city ?? ''
                );

                $amount = $request->amount;

                $transactionFactory = $transactionFactory
                    ->bankCard($bankCard)
                    ->orderNumber($request->order_number)
                    ->notifyUrl($request->notify_url)
                    ->amount($amount)
                    ->subType(Transaction::SUB_TYPE_WITHDRAW);

                if ($request->bank_name == Channel::CODE_USDT) {
                    $binanceUsdtRate = $usdtUtil->getRate()['rate'];
                    $usdtRate = $request->input('usdt_rate', $binanceUsdtRate);
                    $transactionFactory = $transactionFactory->usdtRate($usdtRate, $binanceUsdtRate);
                }

                $withdrawMethod = $paufenWithdrawFeatureEnabled ? 'paufenWithdrawFrom' : 'normalWithdrawFrom'; // 如果啟用跑分代付則使用跑分提現，否則一般提現
                if ($merchant->third_channel_enable) {
                    //取得通道列表，之後需要根據 channel code 找到代付通道
                    $channelList = MerchantThirdChannel::where('owner_id', $merchant->id)
                        ->where('daifu_min', '<=', $amount)
                        ->where('daifu_max', '>=', $amount)
                        ->whereHas('thirdChannel', function (Builder $query) use ($amount) {
                            $query->where('status', ThirdChannel::STATUS_ENABLE)
                                ->where('auto_daifu_threshold_min', '<=', $amount)
                                ->where('auto_daifu_threshold', '>=', $amount)
                                ->where('type', '!=', ThirdChannel::TYPE_DEPOSIT_ONLY);
                        })
                        ->with('thirdChannel')
                        ->get();

                    $failIfThirdFail = $featureToggleRepository->enabled(FeatureToggle::IF_THIRDCHANNEL_DAIFU_FIAL_THAN_ORDER_FAIL);
                    $tryOnce = $featureToggleRepository->enabled(FeatureToggle::TRY_NEXT_IF_THIRDCHANNEL_DAIFU_FAIL);
                    $message = '';

                    if ($channelList->count() > 0) {
                        $channelList = $channelList->shuffle(); //打亂排序z
                        if (!$tryOnce) {
                            $channelList = $channelList->take(1);
                        }
                        $lastKey = $channelList->keys()->last();

                        foreach ($channelList as $key => $channel) {
                            \Log::debug($request->order_number . ' 请求 ' . $channel->thirdChannel->class . '(' . $channel->thirdChannel->merchant_id . ')');

                            $path = "App\ThirdChannel\\" . $channel->thirdChannel->class;
                            $api = new $path();

                            preg_match("/^(?:https?:\/\/)?(?:[^@\n]+@)?(?:www\.)?([^:\/\n?]+)/", $api->daifuUrl, $url);

                            $data = [
                                'url' => preg_replace("/{$url[1]}/", $channel->thirdChannel->custom_url, $api->daifuUrl),
                                'queryDaifuUrl' => preg_replace("/{$url[1]}/", $channel->thirdChannel->custom_url, $api->queryDaifuUrl),
                                'queryBalanceUrl' => preg_replace("/{$url[1]}/", $channel->thirdChannel->custom_url, $api->queryBalanceUrl),
                                'callback_url' => config('app.url') . '/api/v1/callback/' . $request->order_number,
                                'merchant' => $channel->thirdChannel->merchant_id,
                                'key' => $channel->thirdChannel->key,
                                'key2' => $channel->thirdChannel->key2,
                                'key3' => $channel->thirdChannel->key3,
                                "key4" => $channel->thirdChannel->key4,
                                'proxy' => $channel->thirdChannel->proxy,
                                'request' => $request,
                                'thirdchannelId' => $channel->thirdChannel->id,
                                'system_order_number' => $request->order_number,
                            ];

                            if (property_exists($api, "alipayDaifuUrl")) {
                                $data["alipayDaifuUrl"] = preg_replace("/{$url[1]}/", $channel->thirdChannel->custom_url, $api->alipayDaifuUrl);
                            }


                            $balance = $api->queryBalance($data);
                            if ($balance > $amount) {
                                $return_data = $api->sendDaifu($data);
                                $message = $return_data['msg'] ?? '';

                                if (!$return_data['success']) {
                                    $query = $api->queryDaifu($data);
                                    if (isset($query['success']) && $query['success']) { // 查詢訂單後如果三方有建單，不論成功/失敗/付款中，都先變成三方處理中
                                        $transaction = $transactionFactory->thirdchannelWithdrawFrom($merchant, false, null, $channel->thirdChannel->id);
                                        break;
                                    }

                                    if (isset($query['timeout']) && $query['timeout']) { // 查詢訂單後如果三方超時，則視為該三方處理中
                                        $transaction = $transactionFactory->thirdchannelWithdrawFrom($merchant, false, null, $channel->thirdChannel->id);
                                        break;
                                    }
                                } else {
                                    $transaction = $transactionFactory->thirdchannelWithdrawFrom($merchant, false, null, $channel->thirdChannel->id);
                                    break;
                                }
                            } else {
                                \Log::debug($request->order_number . ' 请求 ' . $channel->thirdChannel->class . '(' . $channel->thirdChannel->merchant_id . ') 余额不足');
                                $message = '三方余额不足';
                            }

                            if ($key == $lastKey) { // 如果所有三方都试完了且订单未成功，则留在原站
                                $transaction = $transactionFactory->$withdrawMethod($merchant);
                                if ($failIfThirdFail) { // 三方代付失败则失败
                                    $transactionUtil->markAsFailed($transaction, null, $message ?? null, false);
                                }
                            }
                        }
                    } else {
                        $transaction = $transactionFactory->$withdrawMethod($merchant);

                        if ($failIfThirdFail) { // 有开启三方代付，但是没代付通道则失败
                            $transactionUtil->markAsFailed($transaction, null, '无自动推送门槛内的三方可用，请手动推送', false);
                        }
                    }
                } else {
                    $transaction = $transactionFactory->$withdrawMethod($merchant);
                }

                $wallet->withdraw($merchant->wallet, $totalCost, $transaction->order_number, $transactionType = 'withdraw');

                return $transaction;
            });
        } catch (RaceConditionException $raceConditionException) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code' => ThirdPartyResponseUtil::ERROR_CODE_RACE_CONDITION,
                'message' => __('common.Conflict! Please try again later')
            ]);
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
}
