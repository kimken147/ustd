<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListWalletHistoryRequest;
use App\Http\Resources\WalletHistoryCollection;
use App\Utils\AmountDisplayTransformer;
use App\Utils\DateRangeValidator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WalletHistoryController extends Controller
{

    public function index(ListWalletHistoryRequest $request)
    {
        $lang = $request->input('lang', 'zh_CN');

        $dateRange = DateRangeValidator::parse($request, today())
            ->validateDays(31, __('wallet.timeIntervalError',[],$lang));
        $startedAt = $dateRange->startedAt;

        $walletHistories = auth()->user()->walletHistories()
            ->where('created_at', '>=', $startedAt)
            ->when($request->ended_at, function ($builder, $endedAt) {
                $endedAt = Carbon::make($endedAt)->tz(config('app.timezone'));

                $builder->where('created_at', '<=', $endedAt);
            })
            ->when($request->type, function ($builder, $type) {
                $builder->whereIn('type', $type);
            })
            ->when($request->note, function ($builder, $note) {
                $builder->where('note', 'like', "%{$note}%");
            });

        $walletBalanceTotal = (clone $walletHistories)
            ->first(
                [
                    DB::raw(
                        'SUM(delta->>"$.balance") + SUM(delta->>"$.profit") + SUM(delta->>"$.frozen_balance")AS total'
                    )
                ]
            );

        return WalletHistoryCollection::make(
            $walletHistories->latest('created_at')->latest('id')->paginate(20)->appends($request->query->all())
        )
            ->additional([
                'meta' => [
                    'wallet_balance_total' => AmountDisplayTransformer::transform(
                        data_get($walletBalanceTotal, 'total', '0.00')
                    )
                ]
            ]);
    }

    public function exportCsv(ListWalletHistoryRequest $request)
    {
        $lang = $request->input('lang', 'zh_CN');

        $dateRange = DateRangeValidator::parse($request, today())
            ->validateDays(31, __('wallet.timeIntervalError',[],$lang));
        $startedAt = $dateRange->startedAt;

        $walletHistories = auth()->user()->walletHistories()
            ->where('created_at', '>=', $startedAt)
            ->when($request->ended_at, function ($builder, $endedAt) {
                $endedAt = Carbon::make($endedAt)->tz(config('app.timezone'));

                $builder->where('created_at', '<=', $endedAt);
            })
            ->when($request->type, function ($builder, $type) {
                $builder->where('type', $type);
            });
        $optionType = [
            1=> __('wallet.sysAdjustment',[],$lang),
            2=> __('wallet.balanceTransfer',[],$lang),
            3=> __('wallet.deposit',[],$lang),
            4=> __('wallet.priorDeduction',[],$lang),
            5=> __('wallet.refund',[],$lang),
            6=> __('wallet.depositReward',[],$lang),
            7=> __('wallet.transactionReward',[],$lang),
            11=> __('wallet.sysAdjustmentFrozenBalance',[],$lang),
            12=> __('wallet.withdraw',[],$lang),
            13=> __('wallet.withdrawRefund',[],$lang),
            14=> __('wallet.depositRefund',[],$lang),
        ];

        return response()->streamDownload(
            function () use ($walletHistories, $optionType, $lang) {
                $handle = fopen('php://output', 'w');
                fputs($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // for UTF-8 BOM

                fputcsv($handle, [
                    __('wallet.alterClass',[],$lang),
                    __('wallet.totalBalanceAlter',[],$lang),
                    __('wallet.frozenBalanceAlter',[],$lang),
                    __('wallet.totalBalanceAfterAlter',[],$lang),
                    __('wallet.frozenBalanceAfterAlter',[],$lang),
                    __('wallet.note',[],$lang),
                    __('wallet.alterTime',[],$lang),
                ]);

                $walletHistories->chunkById(
                    300,
                    function ($chunk) use ($handle, $optionType) {
                        foreach ($chunk as $history) {
                            fputcsv($handle, [
                                $optionType[intval($history->type)],
                                $history->delta['balance'],
                                $history->delta['frozen_balance'],
                                $history->result['balance'],
                                $history->result['frozen_balance'],
                                $history->note,
                                (string)$history->created_at,
                            ]);
                        }
                    }
                );

                fclose($handle);
            },
            __('wallet.history',[],$lang) . now()->format('Ymd') . '.csv'
        );
    }
}
