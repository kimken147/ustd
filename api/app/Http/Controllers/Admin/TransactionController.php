<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\TransactionCollection;
use App\Jobs\SettleDelayedProviderCancelOrder;
use App\Jobs\NotifyTransaction;
use App\Models\Channel;
use App\Models\Permission;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserChannelAccount;
use App\Models\UserChannel;
use App\Models\BannedIp;
use App\Models\BannedRealname;
use App\Services\Transaction\CreateTransactionService;
use App\Services\Transaction\DTO\DemoContext;
use App\Services\Transaction\Exceptions\TransactionValidationException;
use App\Utils\AmountDisplayTransformer;
use App\Utils\TransactionUtil;
use App\Utils\TransactionFactory;
use App\Builders\Transaction as TransactionBuilder;
use DateTimeInterface;
use App\Models\FeatureToggle;
use App\Models\TransactionFee;
use App\Repository\FeatureToggleRepository;
use App\Utils\BCMathUtil;
use App\Utils\InsufficientAvailableBalance;
use App\Utils\WalletUtil;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{

    public function __construct()
    {
        $this->middleware(['permission:' . Permission::ADMIN_UPDATE_TRANSACTION])->only('update');
    }

    public function index(Request $request)
    {
        $this->validate($request, [
            'started_at'              => ['nullable', 'date_format:' . DateTimeInterface::ATOM],
            'ended_at'                => ['nullable', 'date_format:' . DateTimeInterface::ATOM],
            'channel_code'            => ['nullable', 'array'],
            'status'                  => ['nullable', 'array'],
            'notify_status'           => ['nullable', 'array'],
            'provider_device_name'    => ['nullable', 'string'],
            'provider_device_hash_id' => ['nullable', 'string'],
            'thirdchannel_id'         => 'nullable',
        ]);

        $startedAt = optional(Carbon::make($request->started_at))->tz(config('app.timezone'));
        $endedAt = $request->ended_at ? Carbon::make($request->ended_at)->tz(config('app.timezone')) : now();

        abort_if(
            now()->diffInMonths($startedAt) > 8,
            Response::HTTP_BAD_REQUEST,
            '查无资料'
        );

        abort_if(
            !$startedAt
                || $startedAt->diffInDays($endedAt) > 91,
            Response::HTTP_BAD_REQUEST,
            '时间区间最多一次筛选三个月，请重新调整时间'
        );

        $builder = new TransactionBuilder;
        $transactions = $builder->transactions($request);

        $transactions->with('from', 'to', 'parent', 'child', 'channel', 'lockedBy', 'transactionFees', 'transactionFees.user', 'transactionNotes', 'transactionNotes.user', 'thirdChannel', 'refundedBy', 'fromChannelAccount', "certificateFiles");

        $perPage = $request->input('per_page', 20);
        return TransactionCollection::make($transactions->paginate($perPage))->additional([
            'meta' => [
                'banned_ips' => BannedIp::where('type', BannedIp::TYPE_TRANSACTION)->get()->pluck('ipv4'),
                'banned_realnames' => BannedRealname::where('type', BannedRealname::TYPE_TRANSACTION)->get()->pluck('realname'),
                'channel_note_enable' => Channel::where('note_enable', 1)->first() ? true : false,
            ]
        ]);
    }

    public function show(Transaction $transaction)
    {
        return \App\Http\Resources\Admin\Transaction::make($transaction->load('from', 'to', 'transactionFees.user', 'channel'));
    }

    public function update(Request $request, Transaction $transaction, TransactionUtil $transactionUtil)
    {
        $this->validate($request, [
            'status'        => ['int', Rule::in(Transaction::STATUS_MANUAL_SUCCESS, Transaction::STATUS_FAILED)],
            'notify_status' => ['int', Rule::in(Transaction::NOTIFY_STATUS_PENDING)],
            'note'          => ['string', 'max:255'],
            'locked'        => ['boolean'],
            'refund'        => ['boolean'],
        ]);

        $transactionUtil->supportLockingLogics($transaction, $request);

        if (in_array($request->status, [Transaction::STATUS_MANUAL_SUCCESS])) {
            if ($request->has('_search1')) {
                $search1 = $request->input('_search1');
                abort_if(Transaction::where('_search1', $search1)->exists(), Response::HTTP_BAD_REQUEST, __('common.Already duplicated'));
                abort_if($transaction->_search1, Response::HTTP_BAD_REQUEST, __('common.Already manually processed'));

                $transaction->update(['_search1' => $request->input('_search1')]);
            }

            $transaction = $transactionUtil->markAsSuccess(
                $transaction,
                auth()->user()->realUser(),
                false,
                $transaction->status === Transaction::STATUS_PAYING_TIMED_OUT
            );
        }

        if (in_array($request->status, [Transaction::STATUS_FAILED])) {
            $transaction = $transactionUtil->markAsFailed(
                $transaction,
                auth()->user()->realUser(),
                null,
                true
            );
        }

        if ($request->has('refund')) {
            abort_if($transaction->refunded_by_id != null, Response::HTTP_BAD_REQUEST, __('common.Transaction already refunded'));

            $transaction->update([
                'should_refund_at' => $request->refund === 1 ? now()->addMinutes($request->delay_settle_minutes) : null,
            ]);

            if ($request->refund) {
                SettleDelayedProviderCancelOrder::dispatch($transaction)->delay($request->delay_settle_minutes * 60);
            }
        }

        if (
            in_array(
                $transaction->notify_status,
                [Transaction::NOTIFY_STATUS_SUCCESS, Transaction::NOTIFY_STATUS_FAILED, Transaction::NOTIFY_STATUS_PENDING]
            )
            && $request->notify_status === Transaction::NOTIFY_STATUS_PENDING
        ) {
            abort_if(
                !$transaction->update(['notify_status' => $request->notify_status]),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );

            NotifyTransaction::dispatch($transaction);
        }

        if ($request->note) {
            $transaction->update(['note' => $request->note]);
        }

        return \App\Http\Resources\Admin\Transaction::make($transaction->load('from', 'to', 'transactionFees.user', 'channel'));
    }

    public function store(
        Transaction $transaction,
        TransactionUtil $transactionUtil,
        TransactionFactory $factory,
        BCMathUtil $bcMath,
        WalletUtil $wallet,
        FeatureToggleRepository $featureToggleRepository,
        Request $request
    ) {
        $this->validate($request, [
            'merchant'     => 'required|numeric',
            'provider'     => 'nullable|numeric',
            'thirdchannel' => 'nullable|numeric',
            'channelGroup' => 'required|numeric',
            'amount'       => 'required|numeric',
            'note'         => 'required|string',
            'account'      => 'nullable|numeric'
        ]);

        $merchantchannelGroup = UserChannel::where('user_id', $request->merchant)->where('channel_group_id', $request->channelGroup)->first();
        abort_if(
            $merchantchannelGroup->status != UserChannel::STATUS_ENABLED,
            Response::HTTP_BAD_REQUEST,
            '商户通道未开启'
        );

        if (!$request->thirdchannel) {

            $providerchannelGroup = UserChannel::where('user_id', $request->provider)->where('channel_group_id', $request->channelGroup)->first();
            if ($providerchannelGroup) {
                abort_if(
                    $providerchannelGroup->status != UserChannel::STATUS_ENABLED,
                    Response::HTTP_BAD_REQUEST,
                    '商户或码商通道未开启'
                );

                abort_if(
                    $merchantchannelGroup->fee_percent < $providerchannelGroup->fee_percent,
                    Response::HTTP_BAD_REQUEST,
                    '码商费率不得大于商户'
                );
            }
        }

        $fillInOrder = DB::transaction(function () use (
            $transaction,
            $transactionUtil,
            $factory,
            $bcMath,
            $wallet,
            $featureToggleRepository,
            $request
        ) {
            $merchant = User::find($request->merchant);
            $merchantUserChannel = UserChannel::where([
                ['user_id', $request->merchant],
                ['channel_group_id', $request->channelGroup],
            ])->first();
            $channelGroup = $merchantUserChannel->channelGroup;

            $factory->amount = $request->amount;
            $factory->clientIpv4 = '0.0.0.0';
            $transaction = $factory->paufenTransactionTo(
                $merchant,
                $channelGroup->channel
            );

            if ($request->has('thirdchannel') && $request->thirdchannel) {
                $transaction->update([
                    'status' => Transaction::STATUS_THIRD_PAYING,
                    'thirdchannel_id' => $request->thirdchannel,
                    'matched_at' => now()
                ]);
                $factory->createPaufenTransactionFees($transaction->refresh(), $merchantUserChannel->channelGroup);
            }

            if ($request->has('account') && $request->account) {
                $userChannelAccount = UserChannelAccount::find($request->account);
                $factory->paufenTransactionFrom($userChannelAccount, $transaction);
            }

            // 非免簽模式，才需要扣碼商錢包餘額
            if (
                !$transaction->thirdchannel_id &&
                !$featureToggleRepository->enabled(
                    FeatureToggle::CANCEL_PAUFEN_MECHANISM
                )
            ) {
                throw_if(
                    $bcMath->lt($transaction->fromWallet->available_balance, $transaction->amount),
                    new InsufficientAvailableBalance()
                );

                $wallet->withdraw(
                    $transaction->fromWallet,
                    $transaction->amount,
                    $transaction->order_number,
                    $transactionType = 'transaction'
                );
            }

            return $transactionUtil->markAsSuccess($transaction);
        });

        return \App\Http\Resources\Admin\Transaction::make($fillInOrder->load('from', 'to'));
    }

    public function exportCsv(Request $request)
    {
        if ($request->status && !is_array($request->status)) {
            $request->merge(['status' => explode(',', $request->status)]);
        }
        if ($request->notify_status && !is_array($request->notify_status)) {
            $request->merge(['notify_status' => explode(',', $request->notify_status)]);
        }

        $this->validate($request, [
            'started_at'              => ['nullable', 'date_format:' . DateTimeInterface::ATOM],
            'ended_at'                => ['nullable', 'date_format:' . DateTimeInterface::ATOM],
            'channel_code'            => ['nullable', 'array'],
            'status'                  => ['nullable', 'array'],
            'notify_status'           => ['nullable', 'array'],
            'provider_device_name'    => ['nullable', 'string'],
            'provider_device_hash_id' => ['nullable', 'string'],
        ]);

        $startedAt = optional(Carbon::make($request->started_at))->tz(config('app.timezone'));
        $endedAt = $request->ended_at ? Carbon::make($request->ended_at)->tz(config('app.timezone')) : now();

        abort_if(
            now()->diffInMonths($startedAt) > 2,
            Response::HTTP_BAD_REQUEST,
            '查无资料'
        );

        abort_if(
            !$startedAt
                || $startedAt->diffInDays($endedAt) > 91,
            Response::HTTP_BAD_REQUEST,
            '时间区间最多一次筛选三个月，请重新调整时间'
        );

        $builder = new TransactionBuilder;
        $transactions = $builder->transactions($request)->with('channel', 'to')
            ->leftJoin(DB::raw('transaction_fees USE INDEX (transaction_query_index)'), function ($join) {
                $join->on('transactions.id', '=', 'transaction_fees.transaction_id');
                $join->on('transactions.to_id', '=', 'transaction_fees.user_id');
            });

        if (config('app.region') == 'cn') {
            $transactions->with('from');
        }

        $statusTextMap = [
            1 => '已建立',
            '匹配中',
            '等待付款',
            '成功',
            '成功',
            '匹配超时',
            '支付超时',
            '失败',
        ];

        $notifyStatusTextMap = ['未通知', '等待发送', '发送中', '成功', '失败'];

        return response()->streamDownload(
            function () use ($transactions, $statusTextMap, $notifyStatusTextMap) {
                $handle = fopen('php://output', 'w');
                fputs($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // for UTF-8 BOM

                $columns = [
                    '系统订单号',
                    '商户订单号',
                    '通道',
                    '收款账号',
                    '订单金额',
                    '手续费',
                    '订单状态',
                    '建立时间',
                    '成功时间',
                    '转帐姓名',
                    '附言',
                    '回调状态',
                    '回调时间',
                    '商户名称'
                ];
                if (config('app.region') == 'ph') {
                    $columns[] = 'Ref No.';
                }
                if (config('app.region') == 'cn') {
                    $columns[] = '码商帐号';
                }
                fputcsv($handle, $columns);

                $transactions->chunkById(5000, function ($chunk) use ($transactions, $handle, $statusTextMap, $notifyStatusTextMap) {
                    foreach ($chunk as $transaction) {
                        $value = [
                            $transaction->system_order_number,
                            $transaction->order_number,
                            $transaction->channel->name,
                            data_get($transaction->from_channel_account, 'account') ?? data_get($transaction->from_channel_account, 'bank_card_number'),
                            $transaction->amount,
                            $transaction->fee,
                            data_get($statusTextMap, $transaction->status, '无'),
                            $transaction->created_at->toIso8601String(),
                            optional($transaction->confirmed_at)->toIso8601String(),
                            data_get($transaction->to_channel_account, 'real_name', ''),
                            $transaction->note,
                            data_get($notifyStatusTextMap, $transaction->notify_status, '无'),
                            optional($transaction->notified_at)->toIso8601String(),
                            optional($transaction->to)->name
                        ];
                        if (config('app.region') == 'ph') {
                            $value[] = $transaction->_search1;
                        }
                        if (config('app.region') == 'cn') {
                            $value[] = optional($transaction->from)->username;
                        }

                        fputcsv($handle, $value);
                    }
                });

                fclose($handle);
            },
            '交易报表' . now()->format('Ymd') . '.csv'
        );
    }

    public function renotify(Request $request, Transaction $transaction)
    {
        if ($transaction) {
            NotifyTransaction::dispatch($transaction);
        }

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function statistics(Request $request)
    {
        $this->validate($request, [
            'started_at'              => ['nullable', 'date_format:' . DateTimeInterface::ATOM],
            'ended_at'                => ['nullable', 'date_format:' . DateTimeInterface::ATOM],
            'channel_code'            => ['nullable', 'array'],
            'status'                  => ['nullable', 'array'],
            'notify_status'           => ['nullable', 'array'],
            'provider_device_name'    => ['nullable', 'string'],
            'provider_device_hash_id' => ['nullable', 'string'],
            'thirdchannel_id'         => 'nullable',
        ]);

        $startedAt = optional(Carbon::make($request->started_at))->tz(config('app.timezone'));
        $endedAt = $request->ended_at ? Carbon::make($request->ended_at)->tz(config('app.timezone')) : now();

        abort_if(
            now()->diffInMonths($startedAt) > 2,
            Response::HTTP_BAD_REQUEST,
            '查无资料'
        );

        abort_if(
            !$startedAt
                || $startedAt->diffInDays($endedAt) > 91,
            Response::HTTP_BAD_REQUEST,
            '时间区间最多一次筛选三个月，请重新调整时间'
        );

        $builder = new TransactionBuilder;
        $transactions = $builder->transactions($request);

        $stats = (clone $transactions)
            ->first([
                DB::raw('SUM(floating_amount) AS total_amount'),
                DB::raw('SUM(CASE WHEN status IN (4, 5) THEN 1 ELSE 0 END) AS total_success')
            ]);

        $totalTransactionFees = (clone $transactions)
            ->leftJoin(
                DB::raw('transaction_fees USE INDEX (transaction_query_index)'),
                'transactions.id',
                '=',
                'transaction_fees.transaction_id'
            );

        $totalFeeAndProfit = (clone $totalTransactionFees)->first([
            DB::raw('SUM(CASE WHEN transaction_fees.thirdchannel_id IS NULL THEN actual_fee ELSE 0 END) AS total_fee'),
            DB::raw('SUM(CASE WHEN transaction_fees.user_id = 0 THEN actual_profit ELSE 0 END) AS total_profit'),
            DB::raw('SUM(CASE WHEN transaction_fees.thirdchannel_id IS NOT NULL THEN actual_fee ELSE 0 END) AS total_thridchannel_fee')
        ]);

        return response()->json([
            'total_amount' => AmountDisplayTransformer::transform($stats->total_amount ?? '0.00'),
            'total_fee' => AmountDisplayTransformer::transform($totalFeeAndProfit->total_fee ?? '0.00'),
            'total_profit' => AmountDisplayTransformer::transform($totalFeeAndProfit->total_profit ?? '0.00'),
            'third_channel_fee' => AmountDisplayTransformer::transform($totalFeeAndProfit->total_thridchannel_fee ?? '0.00'),
            'total_success' => $stats->total_success ?? 0,
        ]);
    }

    public function demo(Request $request, CreateTransactionService $service)
    {
        $this->validate($request, [
            'channel_code' => 'required',
            'username'     => 'required',
            'secret_key'   => 'required',
            'amount'       => 'required|numeric|min:1',
            'notify_url'   => 'required',
            'order_number' => 'required',
        ]);

        try {
            $context = DemoContext::fromRequest($request);
            $result = $service->validateAndGenerateUrl($context);

            return response()->json(['url' => $result->url]);
        } catch (TransactionValidationException $e) {
            abort(Response::HTTP_BAD_REQUEST, $e->getMessage());
        }
    }
}
