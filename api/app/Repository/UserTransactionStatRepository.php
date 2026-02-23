<?php

namespace App\Repository;

use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserTransactionStatRepository
{
    /**
     * Get self transaction stats grouped by user_id + date for the given user IDs.
     *
     * @param Collection|array $userIds
     * @param string $column 'from_id' for providers, 'to_id' for merchants
     * @param string $sumColumn 'amount' or 'floating_amount'
     * @return Collection keyed by "{user_id}_{date}"
     */
    public function getSelfTransactionStats($userIds, string $column = 'from_id', string $sumColumn = 'amount'): Collection
    {
        return DB::table('transactions')
            ->whereIn($column, $userIds)
            ->where('confirmed_at', '>=', Carbon::yesterday())
            ->where('type', Transaction::TYPE_PAUFEN_TRANSACTION)
            ->whereIn('status', [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS])
            ->groupBy(DB::raw("CONCAT({$column}, \"_\", DATE(confirmed_at))"))
            ->get([
                DB::raw("CONCAT({$column}, \"_\", DATE(confirmed_at)) AS user_id_date"),
                DB::raw("SUM({$sumColumn}) AS total"),
            ])
            ->pluck('total', 'user_id_date');
    }

    /**
     * Get descendant transaction stats grouped by date for the given user IDs.
     *
     * @param Collection|array $descendantIds
     * @param string $column 'from_id' for providers, 'to_id' for merchants
     * @param string $sumColumn 'amount' or 'floating_amount'
     * @return Collection keyed by date
     */
    public function getDescendantTransactionStats($descendantIds, string $column = 'from_id', string $sumColumn = 'amount'): Collection
    {
        return DB::table('transactions')
            ->whereIn($column, $descendantIds)
            ->where('confirmed_at', '>=', Carbon::yesterday())
            ->where('type', Transaction::TYPE_PAUFEN_TRANSACTION)
            ->whereIn('status', [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS])
            ->groupBy(DB::raw('DATE(confirmed_at)'))
            ->get([
                DB::raw('DATE(confirmed_at) AS date'),
                DB::raw("SUM({$sumColumn}) AS total"),
            ])
            ->pluck('total', 'date');
    }

    /**
     * Get total wallet balance for the given user IDs.
     */
    public function getWalletBalanceTotal($userIds): string
    {
        return data_get(DB::table('wallets')
            ->whereIn('user_id', $userIds)
            ->get([
                DB::raw('SUM(balance) AS balance_total'),
            ])
            ->first(), 'balance_total', '0');
    }
}
