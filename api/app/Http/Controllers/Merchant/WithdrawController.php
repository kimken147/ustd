<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Merchant\Withdraw;
use App\Http\Resources\Merchant\WithdrawCollection;
use App\Jobs\NotifyTransaction;
use App\Model\BankCard;
use App\Model\FeatureToggle;
use App\Model\Transaction;
use App\Model\TransactionNote;
use App\Model\TransactionFee;
use App\Model\BannedRealname;
use App\Model\Bank;
use App\Repository\FeatureToggleRepository;
use App\Utils\AmountDisplayTransformer;
use App\Utils\BankCardTransferObject;
use App\Utils\BCMathUtil;
use App\Utils\FloatUtil;
use App\Utils\TransactionFactory;
use App\Utils\TransactionUtil;
use App\Utils\WalletUtil;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PragmaRX\Google2FALaravel\Support\Authenticator;
use App\Utils\GuzzleHttpClientTrait;
use App\Utils\UsdtUtil;

//四方
use App\Model\ThirdChannel;
use App\Model\MerchantThirdChannel;
use App\Model\Channel;
use App\Model\User;

class WithdrawController extends Controller
{
    use GuzzleHttpClientTrait;

    public function exportCsv(Request $request)
    {
        $this->validate($request, [
            'started_at' => ['nullable', 'date_format:' . DateTimeInterface::ATOM],
            'ended_at' => ['nullable', 'date_format:' . DateTimeInterface::ATOM],
            'status' => ['nullable', 'array'],
            'notify_status' => ['nullable', 'array'],
        ]);

        $startedAt = optional(Carbon::make($request->started_at))->tz(config('app.timezone'));
        $endedAt = $request->ended_at ? Carbon::make($request->ended_at)->tz(config('app.timezone')) : now();
        $lang = $request->input('lang', 'zh_CN');

        abort_if(
            !$startedAt
            || $startedAt->diffInDays($endedAt) > 31,
            Response::HTTP_BAD_REQUEST,
            __('withdraw.timeIntervalError', [], $lang)
        );

        $withdraws = Transaction::whereIn(
            'type',
            [Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_WITHDRAW]
        )
            ->addSelect([
                'current_user_fee' => TransactionFee::select('fee')
                    ->whereColumn('transaction_id', 'transactions.id')
                    ->where('user_id', auth()->user()->getKey())
                    ->limit(1)
            ])
            ->whereNull('parent_id')
            ->whereIn('from_id', auth()->user()->getDescendantsId());

        $withdraws->when($request->started_at, function ($builder, $startedAt) {
            $builder->where('created_at', '>=', Carbon::make($startedAt)->tz(config('app.timezone')));
        });

        $withdraws->when($request->ended_at, function ($builder, $endedAt) {
            $builder->where('created_at', '<=', Carbon::make($endedAt)->tz(config('app.timezone')));
        });

        $withdraws->when(
            $request->status,
            function ($builder, $status) {
                if ($status == Transaction::STATUS_MATCHING) {
                    $status = [
                        Transaction::STATUS_MATCHING,
                        Transaction::STATUS_PAYING,
                        Transaction::STATUS_THIRD_PAYING
                    ];
                }
                $builder->whereIn('status', $status);
            }
        );

        $withdraws->when(
            $request->notify_status,
            function ($builder, $notifyStatus) {
                $builder->whereIn('notify_status', $notifyStatus);
            }
        );

        $statusTextMap = [
            1 => __('withdraw.established', [], $lang),
            __('withdraw.paying', [], $lang),
            __('withdraw.paying', [], $lang),
            __('withdraw.success', [], $lang),
            __('withdraw.success', [], $lang),
            __('withdraw.fail', [], $lang),
            __('withdraw.fail', [], $lang),
            __('withdraw.fail', [], $lang),
        ];

        $notifyStatusTextMap = [
            __('withdraw.notNotified', [], $lang),
            __('withdraw.waitForSending', [], $lang),
            __('withdraw.sending', [], $lang),
            __('withdraw.success', [], $lang),
            __('withdraw.fail', [], $lang),
        ];

        return response()->streamDownload(
            function () use ($withdraws, $statusTextMap, $notifyStatusTextMap, $lang) {
                $handle = fopen('php://output', 'w');
                fputs($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // for UTF-8 BOM

                fputcsv($handle, [
                    __('withdraw.systemNumber', [], $lang),
                    __('withdraw.merchantNumber', [], $lang),
                    __('withdraw.amount', [], $lang),
                    __('withdraw.fee', [], $lang),
                    __('withdraw.status', [], $lang),
                    __('withdraw.accountOwner', [], $lang),
                    __('withdraw.bankName', [], $lang),
                    __('withdraw.bankAccount', [], $lang),
                    __('withdraw.createdAt', [], $lang),
                    __('withdraw.completedAt', [], $lang),
                    __('withdraw.notifiedAt', [], $lang),
                    __('withdraw.callbackStatus', [], $lang),
                ]);

                $withdraws->chunkById(
                    300,
                    function ($chunk) use ($handle, $statusTextMap, $notifyStatusTextMap, $lang) {
                        foreach ($chunk as $withdraw) {
                            fputcsv($handle, [
                                $withdraw->system_order_number,
                                $withdraw->order_number ?? __('withdraw.none', [], $lang),
                                $withdraw->amount,
                                $withdraw->current_user_fee,
                                data_get($statusTextMap, $withdraw->status, __('withdraw.none', [], $lang)),
                                data_get($withdraw->from_channel_account, 'bank_card_holder_name'),
                                data_get($withdraw->from_channel_account, 'bank_name'),
                                data_get($withdraw->from_channel_account, 'bank_card_number'),
                                $withdraw->created_at->toIso8601String(),
                                optional($withdraw->confirmed_at)->toIso8601String(),
                                data_get($notifyStatusTextMap, $withdraw->notify_status, __('withdraw.none', [], $lang)),
                                optional($withdraw->notified_at)->toIso8601String(),
                            ]);
                        }
                    }
                );

                fclose($handle);
            },
            __('withdraw.report', [], $lang) . now()->format('Ymd') . '.csv'
        );
    }

    public function index(Request $request, FeatureToggleRepository $featureToggleRepository, BCMathUtil $bcMath)
    {
        $this->validate($request, [
            'started_at' => ['nullable', 'date_format:' . DateTimeInterface::ATOM],
            'ended_at' => ['nullable', 'date_format:' . DateTimeInterface::ATOM],
            'status' => ['nullable', 'array'],
            'notify_status' => ['nullable', 'array'],
        ]);

        $startedAt = optional(Carbon::make($request->started_at))->tz(config('app.timezone'));
        $endedAt = $request->ended_at ? Carbon::make($request->ended_at)->tz(config('app.timezone')) : now();
        $confirmed = $request->confirmed;

        abort_if(
            now()->diffInMonths($startedAt) > 2,
            Response::HTTP_BAD_REQUEST,
            '查无资料'
        );

        abort_if(
            !$startedAt
            || $startedAt->diffInDays($endedAt) > 31,
            Response::HTTP_BAD_REQUEST,
            '时间区间最多一次筛选一个月，请重新调整时间'
        );

        $withdraws = Transaction::whereIn(
            'type',
            [Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_WITHDRAW]
        )
            ->whereNull('parent_id')
            ->whereIn('from_id', auth()->user()->getDescendantsId())
            ->latest()
            ->with(['from', 'transactionFees.user', 'transactionNotes' => function ($query) {
                $query->where('user_id', 0);
            }]);

        $withdraws->when($request->started_at, function ($builder, $startedAt) use ($confirmed) {
            if ($confirmed === 'confirmed') {
                $builder->where('confirmed_at', '>=', Carbon::make($startedAt)->tz(config('app.timezone')));
            } else {
                $builder->where('created_at', '>=', Carbon::make($startedAt)->tz(config('app.timezone')));
            }
        });

        $withdraws->when($request->ended_at, function ($builder, $endedAt) use ($confirmed) {
            if ($confirmed === 'confirmed') {
                $builder->where('confirmed_at', '<=', Carbon::make($endedAt)->tz(config('app.timezone')));
            } else {
                $builder->where('created_at', '<=', Carbon::make($endedAt)->tz(config('app.timezone')));
            }
        });

        $withdraws->when($request->has('bank_card_q'), function (Builder $withdraws) use ($request) {
            $withdraws->where(function (Builder $withdraws) use ($request) {
                $bankCardQ = $request->bank_card_q;

                $withdraws->where('from_channel_account->bank_card_holder_name', 'like', "%$bankCardQ%")
                    ->orWhere('from_channel_account->bank_card_number', $bankCardQ)
                    ->orWhere('from_channel_account->bank_name', 'like', "%$bankCardQ%");
            });
        });

        $withdraws->when(
            $request->order_number_or_system_order_number,
            function ($builder, $orderNumberOrSystemOrderNumber) {
                $builder->where(function (Builder $inner) use ($orderNumberOrSystemOrderNumber) {
                    $inner->where('order_number', 'like', "%$orderNumberOrSystemOrderNumber%")
                        ->orWhere('system_order_number', 'like', "%$orderNumberOrSystemOrderNumber%");
                });
            }
        );

        $withdraws->when(
            $request->status,
            function ($builder, $status) {
                if ($status == Transaction::STATUS_MATCHING) {
                    $status = [
                        Transaction::STATUS_MATCHING,
                        Transaction::STATUS_PAYING,
                        Transaction::STATUS_THIRD_PAYING
                    ];
                }
                $builder->whereIn('status', $status);
            }
        );

        $withdraws->when(
            $request->notify_status,
            function ($builder, $notifyStatus) {
                $builder->whereIn('notify_status', $notifyStatus);
            }
        );

        $stats = (clone $withdraws)
            ->first(
                [
                    DB::raw(
                        'SUM(amount) AS total_amount'
                    ),
                ]
            );

        $transactionFeeStats = TransactionFee::whereIn('transaction_id', (clone $withdraws)->select(['id']))
            ->where('user_id', auth()->user()->getKey())
            ->first([DB::raw('SUM(fee) AS total_fee')]);

        $wallet = auth()->user()->wallet;
        $meta = [
            'balance' => $bcMath->sub($wallet->balance, $wallet->frozen_balance),
            'total_amount' => $stats->total_amount ?? '0.00',
            'total_fee' => $transactionFeeStats->total_fee ?? '0.00',
        ];

        if ($featureToggleRepository->enabled(FeatureToggle::SHOW_THIRDCHANNEL_BALANCE_FOR_MERCHANT)) {
            $total = ThirdChannel::where('status', ThirdChannel::STATUS_ENABLE)->sum('balance');
            $meta['thirdchannel_balance'] = $total;
        }

        return WithdrawCollection::make($withdraws->paginate(20))
            ->additional(compact('meta'));
    }

    public function show(Transaction $withdraw)
    {
        return Withdraw::make($withdraw->load('from', 'transactionFees.user'));
    }

    public function store(
        Request                 $request,
        BCMathUtil              $bcMath,
        WalletUtil              $wallet,
        TransactionFactory      $transactionFactory,
        FeatureToggleRepository $featureToggleRepository,
        BankCardTransferObject  $bankCardTransferObject,
        FloatUtil               $floatUtil,
        UsdtUtil                $usdtUtil,
        TransactionUtil         $transactionUtil
    )
    {
        abort_if($request->hasHeader('X-Token') && $request->header('X-Token') != config('app.x_token'), Response::HTTP_BAD_REQUEST);

        abort_if(auth()->user()->realUser()->role !== User::ROLE_MERCHANT, Response::HTTP_FORBIDDEN, __('permission.Denied'));

        abort_if(
            !auth()->user()->withdraw_enable,
            Response::HTTP_BAD_REQUEST,
            __('user.Withdraw disabled')
        );

        $this->validate($request, [
            'bank_card_id' => 'required',
            'amount' => 'required|numeric|min:1',
        ]);

        abort_if(
            $featureToggleRepository->enabled(FeatureToggle::NO_FLOAT_IN_WITHDRAWS)
            && $floatUtil->numberHasFloat($request->amount),
            Response::HTTP_BAD_REQUEST,
            '禁止提交小数点金额'
        );

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

        $bankCard = BankCard::where('user_id', auth()->user()->getKey())
            ->find($request->bank_card_id);

        abort_if(
            !$bankCard,
            Response::HTTP_BAD_REQUEST,
            __('bank-card.Not owner')
        );

        abort_if(
            $bankCard->status !== BankCard::STATUS_REVIEW_PASSED,
            Response::HTTP_BAD_REQUEST,
            __('bank-card.Not reviewing passed')
        );

        abort_if(
            BannedRealname::where(['realname' => $bankCard->bank_card_holder_name, 'type' => BannedRealname::TYPE_WITHDRAW])->exists(),
            Response::HTTP_BAD_REQUEST,
            '该持卡人禁止访问'
        );

        abort_if(
            $bcMath->gtZero(auth()->user()->wallet->withdraw_min_amount ?? 0)
            && $bcMath->lt($request->input('amount'), auth()->user()->wallet->withdraw_min_amount),
            Response::HTTP_BAD_REQUEST,
            '金额低于下限：' . auth()->user()->wallet->withdraw_min_amount
        );

        abort_if(
            $bcMath->gtZero(auth()->user()->wallet->withdraw_max_amount ?? 0)
            && $bcMath->gt($request->input('amount'), auth()->user()->wallet->withdraw_max_amount),
            Response::HTTP_BAD_REQUEST,
            '金额高于上限：' . auth()->user()->wallet->withdraw_max_amount
        );

        $bank = Bank::firstWhere('name', $bankCard->bank_name);

        $needExtraWithdrawFee = $bank ? $bank->needExtraWithdrawFee : false;
        $totalCost = auth()->user()->wallet->calculateTotalWithdrawAmount($request->input('amount'), $needExtraWithdrawFee);

        abort_if(
            $bcMath->lt(auth()->user()->wallet->available_balance, $totalCost),
            Response::HTTP_BAD_REQUEST,
            __('wallet.InsufficientAvailableBalance')
        );

        $paufenWithdrawFeatureEnabled = (
            $featureToggleRepository->enabled(FeatureToggle::FEATURE_PAUFEN_WITHDRAW_MATCHING_TIMED_OUT)
            && auth()->user()->paufen_withdraw_enable
        );

        $merchant = auth()->user();

        $withdraw = DB::transaction(function () use (
            $merchant,
            $bankCard,
            $request,
            $wallet,
            $totalCost,
            $transactionFactory,
            $featureToggleRepository,
            $paufenWithdrawFeatureEnabled,
            $bankCardTransferObject,
            $transactionUtil,
            $usdtUtil
        ) {
            $randOrderNumber = chr(mt_rand(65, 90)) . chr(mt_rand(65, 90)) . chr(mt_rand(65, 90)) . date('YmdHis') . rand(100, 999);
            $transactionFactory = $transactionFactory
                ->bankCard($bankCardTransferObject->model($bankCard))
                ->orderNumber($randOrderNumber)  //自动产生单号
                ->amount($request->amount)
                ->subType(Transaction::SUB_TYPE_WITHDRAW);

            if ($bankCard->bank_name == Channel::CODE_USDT) {
                $binanceUsdtRate = $usdtUtil->getRate()['rate'];
                $usdtRate = $request->input('usdt_rate', $binanceUsdtRate);
                $transactionFactory = $transactionFactory->usdtRate($usdtRate, $binanceUsdtRate);
            }

            $withdrawMethod = $paufenWithdrawFeatureEnabled ? 'paufenWithdrawFrom' : 'normalWithdrawFrom'; // 如果啟用跑分代付則使用跑分提現，否則一般提現

            if ($merchant->third_channel_enable) {
                //取得通道列表，之後需要根據 channel code 找到代付通道
                $channelList = MerchantThirdChannel::where('owner_id', $merchant->id)
                    ->where('daifu_min', '<=', $request->amount)
                    ->where('daifu_max', '>=', $request->amount)
                    ->whereHas('thirdChannel', function (Builder $query) use ($request) {
                        $query->where('status', ThirdChannel::STATUS_ENABLE)
                            ->where('type', '!=', ThirdChannel::TYPE_DEPOSIT_ONLY);
                    })
                    ->with('thirdChannel')
                    ->get();

                $failIfThirdFail = $featureToggleRepository->enabled(FeatureToggle::IF_THIRDCHANNEL_DAIFU_FIAL_THAN_ORDER_FAIL);
                $tryOnce = $featureToggleRepository->enabled(FeatureToggle::TRY_NEXT_IF_THIRDCHANNEL_DAIFU_FAIL);
                $messages = [];

                $createTransactionNote = function ($transaction, $messages)  {
                    foreach ($messages as $msg) {
                        TransactionNote::create([
                            'user_id' => 0,
                            'transaction_id' => $transaction->id,
                            'note' => $msg
                        ]);
                    }
                };

                if ($channelList->count() > 0) {
                    $channelList = $channelList->filter(function ($channel) use ($request) {
                        return $request->amount >= $channel->thirdchannel->auto_daifu_threshold_min
                            && $request->amount <= $channel->thirdchannel->auto_daifu_threshold;
                    })->shuffle();

                    if ($channelList->count() === 0) {
                        $transaction = $transactionFactory->$withdrawMethod($merchant);
                        $messages[] = '无自动推送门槛内的三方可用，请手动推送';
                        $createTransactionNote($transaction, $messages);
                        if ($failIfThirdFail) { // 三方代付失败则失败
                            $transactionUtil->markAsFailed($transaction, null, $message ?? null, false);
                        }
                    }
                    else {
                        if (!$tryOnce) {
                            $channelList = $channelList->take(1);
                        }
                        $lastKey = $channelList->keys()->last();

                        foreach ($channelList as $key => $channel) {
                            \Log::debug($randOrderNumber . ' 请求 ' . $channel->thirdChannel->class . '(' . $channel->thirdChannel->merchant_id . ')');

                            $path = "App\ThirdChannel\\" . $channel->thirdChannel->class;
                            $api = new $path();

                            preg_match("/^(?:https?:\/\/)?(?:[^@\n]+@)?(?:www\.)?([^:\/\n?]+)/", $api->daifuUrl, $url);

                            $new_data = new \stdClass();

                            $new_data->bank_card_holder_name = $bankCard->bank_card_holder_name;
                            $new_data->bank_card_number = $bankCard->bank_card_number;
                            $new_data->bank_name = $bankCard->bank_name;
                            $new_data->bank_province = $bankCard->bank_province;
                            $new_data->bank_city = $bankCard->bank_city;
                            $new_data->amount = $request->amount;
                            $new_data->order_number = $randOrderNumber;

                            $data = [
                                'url' => preg_replace("/{$url[1]}/", $channel->thirdChannel->custom_url, $api->daifuUrl),
                                'queryDaifuUrl' => preg_replace("/{$url[1]}/", $channel->thirdChannel->custom_url, $api->queryDaifuUrl),
                                'queryBalanceUrl' => preg_replace("/{$url[1]}/", $channel->thirdChannel->custom_url, $api->queryBalanceUrl),
                                'callback_url' => config('app.url') . '/api/v1/callback/' . $randOrderNumber,
                                'merchant' => $channel->thirdChannel->merchant_id,
                                'key' => $channel->thirdChannel->key,
                                'key2' => $channel->thirdChannel->key2,
                                'key3' => $channel->thirdChannel->key3,
                                "key4" => $channel->thirdChannel->key4,
                                'proxy' => $channel->thirdChannel->proxy,
                                'request' => $new_data,
                                'thirdchannelId' => $channel->thirdChannel->id
                            ];

                            if (property_exists($api, "alipayDaifuUrl")) {
                                $data["alipayDaifuUrl"] = preg_replace("/{$url[1]}/", $channel->thirdChannel->custom_url, $api->alipayDaifuUrl);
                            }

                            $balance = $api->queryBalance($data);
                            if ($balance > $request->amount) {
                                $return_data = $api->sendDaifu($data);
                                $message = $return_data['msg'] ?? '';
                                if (!empty($message)) {
                                    $messages[] = "{$channel->thirdChannel->name}: $message";
                                }

                                $createTransaction = function () use ($transactionFactory, $merchant, $channel) {
                                    return $transactionFactory->thirdchannelWithdrawFrom($merchant, false, null, $channel->thirdChannel->id);
                                };


                                if (!$return_data['success']) {
                                    $query = $api->queryDaifu($data);
                                    $isSuccessOrTimeout = (isset($query['success']) && $query['success']) || (isset($query['timeout']) && $query['timeout']);

                                    if ($isSuccessOrTimeout) {
                                        $transaction = $createTransaction();
                                        $createTransactionNote($transaction, $messages);
                                        break;
                                    }
                                } else {
                                    $transaction = $createTransaction();
                                    $createTransactionNote($transaction, $messages);
                                    break;
                                }
                            } else {
                                \Log::debug($randOrderNumber . ' 请求 ' . $channel->thirdChannel->class . '(' . $channel->thirdChannel->merchant_id . ') 余额不足');
                                $messages[] = "{$channel->thirdChannel->name}: 三方余额不足";
                            }

                            if ($key == $lastKey) { // 如果所有三方都试完了且订单未成功，则留在原站
                                $transaction = $transactionFactory->$withdrawMethod($merchant);
                                $messages[] = '无自动推送门槛内的三方可用，请手动推送';
                                $createTransactionNote($transaction, $messages);
                                if ($failIfThirdFail) { // 三方代付失败则失败
                                    $transactionUtil->markAsFailed($transaction, null, $message ?? null, false);
                                }
                            }
                        }
                    }
                } else {
                    $transaction = $transactionFactory->$withdrawMethod($merchant);
                    $messages[] = '无符合当前代付金额的三方可用，请调整限额设定';
                    $createTransactionNote($transaction, $messages);

                    if ($failIfThirdFail) { // 有开启三方代付，但是没代付通道则失败
                        $transactionUtil->markAsFailed($transaction, null, '无符合当前代付金额的三方可用，请调整限额设定', false);
                    }
                }
            } else {
                $transaction = $transactionFactory->$withdrawMethod($merchant);
            }

            $wallet->withdraw(auth()->user()->wallet, $totalCost, $transaction->order_number, $transactionType = 'withdraw');

            return $transaction;
        });

        abort_if(!$withdraw, Response::HTTP_BAD_REQUEST, '代付失败');

        Cache::put('admin_withdraws_added_at', now(), now()->addSeconds(60));

        return Withdraw::make($withdraw->load('from', 'transactionFees.user'));
    }

    public function update(Request $request, Transaction $withdraw, TransactionUtil $transactionUtil)
    {
        abort_if(!$withdraw->from->is(auth()->user()), Response::HTTP_NOT_FOUND);

        $this->validate($request, [
            'status' => ['int', Rule::in(Transaction::STATUS_RECEIVED)],
            'notify_status' => ['int', Rule::in(Transaction::NOTIFY_STATUS_PENDING)],
        ]);

        if (
            in_array(
                $withdraw->notify_status,
                [Transaction::NOTIFY_STATUS_SUCCESS, Transaction::NOTIFY_STATUS_FAILED]
            )
            && $request->notify_status === Transaction::NOTIFY_STATUS_PENDING
        ) {
            abort_if(
                !$withdraw->update(['notify_status' => $request->notify_status]),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );

            NotifyTransaction::dispatch($withdraw);
        }

        if ($request->status === Transaction::STATUS_RECEIVED) {
            $transactionUtil->markAsReceived($withdraw, auth()->user()->realUser());
        }

        return Withdraw::make($withdraw->load('from', 'transactionFees.user'));
    }
}
