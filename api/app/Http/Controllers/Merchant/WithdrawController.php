<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Merchant\Withdraw;
use App\Http\Resources\Merchant\WithdrawCollection;
use App\Jobs\NotifyTransaction;
use App\Models\FeatureToggle;
use App\Models\ThirdChannel;
use App\Models\Transaction;
use App\Models\TransactionFee;
use App\Repository\FeatureToggleRepository;
use App\Services\Withdraw\WithdrawService;
use App\Utils\BCMathUtil;
use App\Utils\DateRangeValidator;
use App\Utils\TransactionUtil;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WithdrawController extends Controller
{
    public function exportCsv(Request $request)
    {
        $this->validate($request, [
            'started_at' => ['nullable', 'date_format:' . DateTimeInterface::ATOM],
            'ended_at' => ['nullable', 'date_format:' . DateTimeInterface::ATOM],
            'status' => ['nullable', 'array'],
            'notify_status' => ['nullable', 'array'],
        ]);

        $lang = $request->input('lang', 'zh_CN');

        DateRangeValidator::parse($request)
            ->validateDays(31, __('withdraw.timeIntervalError', [], $lang));

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

        $confirmed = $request->confirmed;

        DateRangeValidator::parse($request)
            ->validateMonths(2)
            ->validateDays(31);

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
        Request $request,
        WithdrawService $service
    ) {
        $this->validate($request, [
            'bank_card_id' => 'required',
            'amount' => 'required|numeric|min:1',
        ]);

        $context = $service->buildContextFromMerchantWithBankCard($request, auth()->user());
        $result = $service->execute($context);

        abort_if(!$result->transaction, Response::HTTP_BAD_REQUEST, '代付失败');

        return Withdraw::make($result->getTransaction());
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
