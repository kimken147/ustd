<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Model\FeatureToggle;
use App\Model\Transaction;
use App\Model\TransactionNote;
use App\Model\Wallet;
use App\Model\Bank;
use App\Model\BannedRealname;
use App\Repository\FeatureToggleRepository;
use App\Utils\BankCardTransferObject;
use App\Utils\BCMathUtil;
use App\Utils\FloatUtil;
use App\Utils\ThirdPartyResponseUtil;
use App\Utils\TransactionFactory;
use App\Utils\WalletUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PragmaRX\Google2FALaravel\Support\Authenticator;
use App\Utils\UsdtUtil;

//四方
use App\Model\ThirdChannel;
use App\Model\MerchantThirdChannel;
use App\Utils\TransactionUtil;
use App\Model\Channel;
use App\Model\User;

class AgencyWithdrawController extends Controller
{

    public function store(
        Request                 $request,
        BCMathUtil              $bcMath,
        FeatureToggleRepository $featureToggleRepository,
        TransactionFactory      $transactionFactory,
        BankCardTransferObject  $bankCardTransferObject,
        WalletUtil              $walletUtil,
        FloatUtil               $floatUtil,
        TransactionUtil         $transactionUtil,
        UsdtUtil                $usdtUtil
    )
    {
        abort_if($request->hasHeader('X-Token') && $request->header('X-Token') != config('app.x_token'), Response::HTTP_BAD_REQUEST);

        abort_if(auth()->user()->realUser()->role !== User::ROLE_MERCHANT, Response::HTTP_FORBIDDEN, __('permission.Denied'));

        abort_if(
            !$featureToggleRepository->enabled(FeatureToggle::ENABLE_AGENCY_WITHDRAW)
            || !auth()->user()->agency_withdraw_enable,
            Response::HTTP_BAD_REQUEST,
            __('user.Agency withdraw disabled')
        );

        $this->validate($request, [
            'bank_card_number' => 'required|max:50',
            'bank_card_holder_name' => 'max:50',
            'bank_name' => 'required|max:50',
            'amount' => 'required|numeric|min:1',
        ]);

        if (auth()->user()->withdraw_google2fa_enable) {
            $this->validate($request, [
                config('google2fa.otp_input') => 'required|string',
            ]);

            /** @var Authenticator $authenticator */
            $authenticator = app(Authenticator::class)->bootStateless($request);

            abort_if(
                !$authenticator->isAuthenticated(),
                Response::HTTP_BAD_REQUEST,
                __('google2fa.Invalid OTP')
            );
        }

        $merchant = auth()->user();
        $wallet = $merchant->wallet;

        // 過濾掉已存在的 order_id

        abort_if($request->order_id && Transaction::where('order_number', $request->order_id)->exists(), Response::HTTP_BAD_REQUEST, '订单号：' . $request->order_id . '已存在');

        abort_if(
            $bcMath->gtZero($wallet->agency_withdraw_min_amount ?? 0)
            && $bcMath->lt($request->amount, $wallet->agency_withdraw_min_amount),
            Response::HTTP_BAD_REQUEST,
            '金额低于下限：' . $wallet->agency_withdraw_min_amount
        );

        abort_if(
            $bcMath->gtZero($wallet->agency_withdraw_max_amount ?? 0)
            && $bcMath->gt($request->amount, $wallet->agency_withdraw_max_amount),
            Response::HTTP_BAD_REQUEST,
            '金额高于上限：' . $wallet->agency_withdraw_max_amount
        );

        abort_if(
            $featureToggleRepository->enabled(FeatureToggle::NO_FLOAT_IN_WITHDRAWS)
            && $floatUtil->numberHasFloat($request->amount),
            Response::HTTP_BAD_REQUEST,
            '禁止提交小数点金额'
        );

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
                'message' => '不支援此银行'
            ]);
        }

        $needExtraWithdrawFee = $bank ? $bank->needExtraWithdrawFee : false;
        $totalCost = $merchant->wallet->calculateTotalAgencyWithdrawAmount($request->input('amount'), $needExtraWithdrawFee);

        abort_if(
            $bcMath->lt($wallet->available_balance, $totalCost),
            Response::HTTP_BAD_REQUEST,
            __('wallet.InsufficientAvailableBalance')
        );

        $paufenAgencyWithdrawFeatureEnabled = ($featureToggleRepository->enabled(FeatureToggle::FEATURE_PAUFEN_WITHDRAW_MATCHING_TIMED_OUT)
            && auth()->user()->paufen_agency_withdraw_enable
        );

        try {
            $transaction = DB::transaction(function () use (
                $merchant,
                $wallet,
                $walletUtil,
                $transactionFactory,
                $featureToggleRepository,
                $paufenAgencyWithdrawFeatureEnabled,
                $bankCardTransferObject,
                $request,
                $totalCost,
                $transactionUtil,
                $usdtUtil
            ) {
                abort_if(
                    BannedRealname::where(['realname' => $request['bank_card_holder_name'], 'type' => BannedRealname::TYPE_WITHDRAW])->exists(),
                    Response::HTTP_BAD_REQUEST,
                    '该持卡人禁止访问'
                );

                $amount = $request['amount'];
                $orderNumber = $request['order_id'] ?? chr(mt_rand(65, 90)) . chr(mt_rand(65, 90)) . chr(mt_rand(65, 90)) . date('YmdHis') . rand(100, 999);
                $transactionFactory = $transactionFactory
                    ->bankCard($bankCardTransferObject->plain(
                        $request['bank_name'],
                        $request['bank_card_number'],
                        $request['bank_card_holder_name'] ?? '',
                        $request['bank_province'] ?? '',
                        $request['bank_city'] ?? ''
                    ))
                    ->orderNumber($orderNumber)  //自动产生单号
                    ->amount($amount)
                    ->subType(Transaction::SUB_TYPE_AGENCY_WITHDRAW);

                if ($request['bank_name'] == Channel::CODE_USDT) {
                    $binanceUsdtRate = $usdtUtil->getRate()['rate'];
                    $usdtRate = $request->input('usdt_rate', $binanceUsdtRate);
                    $transactionFactory = $transactionFactory->usdtRate($usdtRate, $binanceUsdtRate);
                }

                $withdrawMethod = $paufenAgencyWithdrawFeatureEnabled ? 'paufenWithdrawFrom' : 'normalWithdrawFrom'; // 如果啟用跑分代付則使用跑分提現，否則一般提現

                if ($merchant->third_channel_enable) {
                    //取得通道列表，之後需要根據 channel code 找到代付通道
                    $channelList = MerchantThirdChannel::with('thirdChannel')->where('owner_id', $merchant->id)
                        ->where('daifu_min', '<=', $amount)
                        ->where('daifu_max', '>=', $amount)
                        ->whereHas('thirdChannel', function ($query) use ($amount) {
                            $query->where('status', ThirdChannel::STATUS_ENABLE)
                                ->where('type', '!=', ThirdChannel::TYPE_DEPOSIT_ONLY);
                        })
                        ->get();

                    $failIfThirdFail = $featureToggleRepository->enabled(FeatureToggle::IF_THIRDCHANNEL_DAIFU_FIAL_THAN_ORDER_FAIL);
                    $tryOnce = $featureToggleRepository->enabled(FeatureToggle::TRY_NEXT_IF_THIRDCHANNEL_DAIFU_FAIL);
                    $messages = [];

                    if ($channelList->count() > 0) {
                        $channelList = $channelList->filter(function ($channel) use ($amount) {
                            return $amount >= $channel->thirdchannel->auto_daifu_threshold_min
                                && $amount <= $channel->thirdchannel->auto_daifu_threshold;
                        })->shuffle();

                        if ($channelList->count() === 0) {
                            $transaction = $transactionFactory->$withdrawMethod($merchant, true);
                            TransactionNote::create([
                                'user_id' => 0,
                                'transaction_id' => $transaction->id,
                                'note' => '无自动推送门槛内的三方可用，请手动推送'
                            ]);
                            if ($failIfThirdFail) { // 有开启三方代付，但是没代付通道则失败
                                $transactionUtil->markAsFailed($transaction, null, '', false);
                            }
                        }
                        else {
                            if (!$tryOnce) {
                                $channelList = $channelList->take(1);
                            }
                            $lastKey = $channelList->keys()->last();

                            foreach ($channelList as $key => $channel) {
                                Log::debug($orderNumber . ' 请求 ' . $channel->thirdChannel->class . '(' . $channel->thirdChannel->merchant_id . ')');
                                $path = "App\ThirdChannel\\" . $channel->thirdChannel->class;
                                $api = new $path();

                                preg_match("/^(?:https?:\/\/)?(?:[^@\n]+@)?(?:www\.)?([^:\/\n?]+)/", $api->daifuUrl, $url);


                                $new_data = new \stdClass();

                                $new_data->bank_card_holder_name = $request['bank_card_holder_name'];
                                $new_data->bank_card_number = $request['bank_card_number'];
                                $new_data->bank_name = $request['bank_name'];
                                $new_data->bank_province = $request['bank_province'] ?? '';
                                $new_data->bank_city = $request['bank_city'] ?? '';
                                $new_data->amount = $request['amount'];
                                $new_data->order_number = $orderNumber;

                                $data = [
                                    'url' => preg_replace("/{$url[1]}/", $channel->thirdChannel->custom_url, $api->daifuUrl),
                                    'queryDaifuUrl' => preg_replace("/{$url[1]}/", $channel->thirdChannel->custom_url, $api->queryDaifuUrl),
                                    'queryBalanceUrl' => preg_replace("/{$url[1]}/", $channel->thirdChannel->custom_url, $api->queryBalanceUrl),
                                    'callback_url' => config('app.url') . '/api/v1/callback/' . $orderNumber,
                                    'merchant' => $channel->thirdChannel->merchant_id,
                                    'key' => $channel->thirdChannel->key,
                                    'key2' => $channel->thirdChannel->key2,
                                    'key3' => $channel->thirdChannel->key3,
                                    'proxy' => $channel->thirdChannel->proxy,
                                    'request' => $new_data,
                                    'thirdchannelId' => $channel->thirdChannel->id,
                                    'system_order_number' => $orderNumber,
                                ];

                                if (property_exists($api, "alipayDaifuUrl")) {
                                    $data["alipayDaifuUrl"] = preg_replace("/{$url[1]}/", $channel->thirdChannel->custom_url, $api->alipayDaifuUrl);
                                }

                                $balance = $api->queryBalance($data);
                                if ($balance > $amount) {
                                    $return_data = $api->sendDaifu($data);
                                    $message = $return_data['msg'] ?? '';
                                    if (!empty($message)) {
                                        $messages[] = "{$channel->thirdChannel->name}: $message";
                                    }

                                    $createTransaction = function () use ($transactionFactory, $merchant, $channel) {
                                        return $transactionFactory->thirdchannelWithdrawFrom($merchant, true, null, $channel->thirdChannel->id);
                                    };

                                    if (!$return_data['success']) {
                                        Log::debug("三方代付錯誤:{$channel->thirdChannel->name}", [
                                            'name' => $channel->thirdChannel->name,
                                            'message' => $message
                                        ]);

                                        $query = $api->queryDaifu($data);
                                        $isSuccessOrTimeout = (isset($query['success']) && $query['success']) || (isset($query['timeout']) && $query['timeout']);

                                        if ($isSuccessOrTimeout) {
                                            $transaction = $createTransaction();
                                            foreach ($messages as $msg) {
                                                TransactionNote::create([
                                                    'user_id' => 0,
                                                    'transaction_id' => $transaction->id,
                                                    'note' => $msg
                                                ]);
                                            }
                                            break;
                                        }
                                    } else {
                                        $transaction = $createTransaction();
                                        foreach ($messages as $msg) {
                                            TransactionNote::create([
                                                'user_id' => 0,
                                                'transaction_id' => $transaction->id,
                                                'note' => $msg
                                            ]);
                                        }
                                        break;
                                    }
                                } else {
                                    Log::debug($orderNumber . ' 请求 ' . $channel->thirdChannel->class . '(' . $channel->thirdChannel->merchant_id . ') 余额不足');
                                    $messages[] = $channel->thirdChannel->name . ': 三方余额不足';
                                }

                                if ($key == $lastKey) { // 如果所有三方都试完了且订单未成功，则留在原站
                                    $transaction = $transactionFactory->$withdrawMethod($merchant, true);
                                    $messages[] = '无自动推送门槛内的三方可用，请手动推送';
                                    foreach ($messages as $msg) {
                                        TransactionNote::create([
                                            'user_id' => 0,
                                            'transaction_id' => $transaction->id,
                                            'note' => $msg
                                        ]);
                                    }

                                    if ($failIfThirdFail) { // 三方代付失败则失败
                                        $transactionUtil->markAsFailed($transaction, null, $message ?? null, false);
                                    }
                                }
                            }
                        }

                    } else {
                        $transaction = $transactionFactory->$withdrawMethod($merchant, true);
                        TransactionNote::create([
                            'user_id' => 0,
                            'transaction_id' => $transaction->id,
                            'note' => '无符合当前代付金额的三方可用，请调整限额设定'
                        ]);

                        if ($failIfThirdFail) { // 有开启三方代付，但是没代付通道则失败
                            $transactionUtil->markAsFailed($transaction, null, '', false);
                        }
                    }
                } else {
                    $transaction = $transactionFactory->$withdrawMethod($merchant, true);
                }

                abort_if(!$transaction, Response::HTTP_BAD_REQUEST, '建立代付失败');

                $walletUtil->withdraw(
                    $wallet,
                    $totalCost,
                    $transaction->order_number,
                    $transactionType = 'withdraw'
                );

                $wallet->refresh();

                return $transaction;
            });
        } catch (\Exception $e) {
            Log::error('代付失敗', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        Cache::put('admin_withdraws_added_at', now(), now()->addSeconds(60));

        return response()->noContent(Response::HTTP_CREATED);
    }
}
