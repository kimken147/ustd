<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Merchant\TransactionCollection;
use App\Model\Transaction;
use App\Model\TransactionFee;
use App\Utils\AmountDisplayTransformer;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TransactionController extends Controller
{

    public function index(Request $request)
    {
        $this->validate($request, [
            'started_at'    => ['nullable', 'date_format:'.DateTimeInterface::ATOM],
            'ended_at'      => ['nullable', 'date_format:'.DateTimeInterface::ATOM],
            'channel_code'  => ['nullable', 'array'],
            'status'        => ['nullable', 'array'],
            'notify_status' => ['nullable', 'array'],
        ]);

        $startedAt = optional(Carbon::make($request->started_at))->tz(config('app.timezone'));
        $endedAt = $request->ended_at ? Carbon::make($request->ended_at)->tz(config('app.timezone')) : now();
        $confirmed = $request->confirmed;
        $lang = $request->input('lang', 'zh_CN');

        abort_if(
            now()->diffInMonths($startedAt) > 2,
            Response::HTTP_BAD_REQUEST,
            __('transaction.noRecord',[],$lang)
        );

        abort_if(
            !$startedAt
            || $startedAt->diffInDays($endedAt) > 31,
            Response::HTTP_BAD_REQUEST,
            __('transaction.timeIntervalError',[],$lang)
        );

        $transactions = Transaction::where('type', Transaction::TYPE_PAUFEN_TRANSACTION)
            ->whereIn('to_id', auth()->user()->getDescendantsId())
            ->latest()
            ->with('from', 'to', 'to.descendants', 'transactionFees', 'transactionFees.user');

        $transactions->when($request->started_at, function ($builder, $startedAt) use ($confirmed) {
            if ($confirmed === 'confirmed') {
                $builder->where('confirmed_at', '>=', Carbon::make($startedAt)->tz(config('app.timezone')));
            } else {
                $builder->where('created_at', '>=', Carbon::make($startedAt)->tz(config('app.timezone')));
            }
        });

        $transactions->when($request->ended_at, function ($builder, $endedAt) use ($confirmed) {
            if ($confirmed === 'confirmed') {
                $builder->where('confirmed_at', '<=', Carbon::make($endedAt)->tz(config('app.timezone')));
            } else {
                $builder->where('created_at', '<=', Carbon::make($endedAt)->tz(config('app.timezone')));
            }
        });

        $transactions->when($request->descendant_merchent_username_or_name, function ($builder, $descendantMerchentUsernameOrName) {
            $builder->whereIn('to_id', function ($query) use ($descendantMerchentUsernameOrName) {
                $query->select('id')
                    ->from('users')
                    ->where('name', 'like', "%$descendantMerchentUsernameOrName%")
                    ->orWhere('username', $descendantMerchentUsernameOrName);
            });
        });

        $transactions->when(
            $request->order_number_or_system_order_number,
            function ($builder, $orderNumberOrSystemOrderNumber) {
                $builder->where('order_number', 'like', "%$orderNumberOrSystemOrderNumber%")
                    ->orWhere('system_order_number', 'like', "%$orderNumberOrSystemOrderNumber%");
            }
        );

        $transactions->when(
            $request->channel_code,
            function ($builder, $channelCode) {
                $builder->whereIn('channel_code', $channelCode);
            }
        );

        $transactions->when(
            $request->amount,
            function ($builder, $amount) {
                $builder->where('amount', $amount);
            }
        );

        $transactions->when(
            $request->status,
            function ($builder, $status) {
                $builder->whereIn('status', $status);
            }
        );

        $transactions->when(
            $request->notify_status,
            function ($builder, $notifyStatus) {
                $builder->whereIn('notify_status', $notifyStatus);
            }
        );

        $transactions->when($request->real_name, function ($builder, $realName) {
            return $builder->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(to_channel_account, "$.real_name")) LIKE ?', ['%' . $realName . '%']);
        });

        $stats = (clone $transactions)
        ->useIndex('transactions_query_1')
        ->first([
            DB::raw('SUM(floating_amount) AS total_amount'),
            DB::raw('SUM(CASE WHEN status = 4 OR status = 5 THEN 1 ELSE 0 END) AS total_success')
        ]);

        $transactionFeeStats = TransactionFee::whereIn('transaction_id', (clone $transactions)->select(['id']))
            ->where('user_id', auth()->user()->getKey())
            ->first([
                DB::raw('SUM(fee) AS total_fee')
            ]);

        return TransactionCollection::make($transactions->paginate(20))
            ->additional([
                'meta' => [
                    'total_amount' => AmountDisplayTransformer::transform($stats->total_amount ?? '0.00'),
                    'total_fee'    => AmountDisplayTransformer::transform($transactionFeeStats->total_fee ?? '0.00'),
                    'total_success' => $stats->total_success ?? 0
                ],
            ]);
    }

    public function show(Transaction $transaction)
    {
        abort_if(!$transaction->to->isSelfOrDescendantOf(auth()->user()), Response::HTTP_NOT_FOUND);

        return \App\Http\Resources\Merchant\Transaction::make($transaction->load('from', 'to', 'transactionFees.user'));
    }

    public function exportCsv(Request $request)
    {
        $this->validate($request, [
            'started_at'    => ['nullable', 'date_format:'.DateTimeInterface::ATOM],
            'ended_at'      => ['nullable', 'date_format:'.DateTimeInterface::ATOM],
            'channel_code'  => ['nullable', 'array'],
            'status'        => ['nullable', 'array'],
            'notify_status' => ['nullable', 'array'],
        ]);

        $startedAt = optional(Carbon::make($request->started_at))->tz(config('app.timezone'));
        $endedAt = $request->ended_at ? Carbon::make($request->ended_at)->tz(config('app.timezone')) : now();
        $lang = $request->input('lang', 'zh_CN');

        abort_if(
            !$startedAt
            || $startedAt->diffInDays($endedAt) > 31,
            Response::HTTP_BAD_REQUEST,
            __('transaction.timeIntervalError',[],$lang)
        );

        $confirmed = $request->confirmed;


        $transactions = Transaction::where('type', Transaction::TYPE_PAUFEN_TRANSACTION)
            ->addSelect(['current_user_fee' => TransactionFee::select('fee')
                ->whereColumn('transaction_id', 'transactions.id')
                ->where('user_id', auth()->user()->getKey())
                ->limit(1)
            ])
            ->whereIn('to_id', auth()->user()->getDescendantsId())
            ->with('channel');


        $transactions->when($request->started_at, function ($builder, $startedAt) use ($confirmed) {
            if ($confirmed === 'confirmed') {
                $builder->where('confirmed_at', '>=', Carbon::make($startedAt)->tz(config('app.timezone')));
            } else {
                $builder->where('created_at', '>=', Carbon::make($startedAt)->tz(config('app.timezone')));
            }
        });

        $transactions->when($request->ended_at, function ($builder, $endedAt) use ($confirmed) {
            if ($confirmed === 'confirmed') {
                $builder->where('confirmed_at', '<=', Carbon::make($endedAt)->tz(config('app.timezone')));
            } else {
                $builder->where('created_at', '<=', Carbon::make($endedAt)->tz(config('app.timezone')));
            }
        });

        $transactions->when(
            $request->channel_code,
            function ($builder, $channelCode) {
                $builder->whereIn('channel_code', $channelCode);
            }
        );

        $transactions->when(
            $request->amount,
            function ($builder, $amount) {
                $builder->where('amount', $amount);
            }
        );

        $transactions->when(
            $request->status,
            function ($builder, $status) {
                $builder->whereIn('status', $status);
            }
        );

        $transactions->when(
            $request->notify_status,
            function ($builder, $notifyStatus) {
                $builder->whereIn('notify_status', $notifyStatus);
            }
        );

        $transactions->when($request->real_name, function ($builder, $realName) {
            return $builder->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(to_channel_account, "$.real_name")) LIKE ?', ['%' . $realName . '%']);
        });

        $statusTextMap = [
            1 => __('transaction.established',[],$lang),
            __('transaction.matching',[],$lang),
            __('transaction.waitForPaying',[],$lang),
            __('transaction.success',[],$lang),
            __('transaction.success',[],$lang),
            __('transaction.matchingTimeout',[],$lang),
            __('transaction.paymentTimeout',[],$lang),
            __('transaction.fail',[],$lang),
        ];

        $notifyStatusTextMap = [
            __('transaction.notNotified',[],$lang),
            __('transaction.waitForSending',[],$lang),
            __('transaction.sending',[],$lang),
            __('transaction.success',[],$lang),
            __('transaction.fail',[],$lang),
        ];

        return response()->streamDownload(
            function () use ($transactions, $statusTextMap, $notifyStatusTextMap, $lang) {
                $handle = fopen('php://output', 'w');
                fputs($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // for UTF-8 BOM

                fputcsv($handle, [
                    __('transaction.systemNumber',[],$lang),
                    __('transaction.merchantNumber',[],$lang),
                    __('transaction.amount',[],$lang),
                    __('transaction.fee',[],$lang),
                    __('transaction.status',[],$lang),
                    __('transaction.realName',[],$lang),
                    __('transaction.createdAt',[],$lang),
                    __('transaction.completedAt',[],$lang),
                    __('transaction.callbackStatus',[],$lang),
                    __('transaction.notifiedAt',[],$lang)
                ]);

                $transactions->chunkById(
                    300,
                    function ($chunk) use ($handle, $statusTextMap, $notifyStatusTextMap, $lang) {
                        foreach ($chunk as $transaction) {
                            fputcsv($handle, [
                                $transaction->system_order_number,
                                $transaction->order_number,
                                $transaction->amount,
                                $transaction->current_user_fee,
                                data_get($statusTextMap, $transaction->status, __('transaction.none',[],$lang)),
                                data_get($transaction->to_channel_account, 'real_name'),
                                $transaction->created_at->toIso8601String(),
                                optional($transaction->confirmed_at)->toIso8601String(),
                                data_get($notifyStatusTextMap, $transaction->notify_status, __('transaction.none',[],$lang)),
                                optional($transaction->notified_at)->toIso8601String(),
                            ]);
                        }
                    }
                );

                fclose($handle);
            },
            __('transaction.report',[],$lang) . now()->format('Ymd') . '.csv'
        );
    }
}
