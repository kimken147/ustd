<?php

namespace App\Repository;

use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StatisticsRepository
{
    /**
     * Get transaction stats grouped by username (for index).
     */
    public function getTransactionStatsByUsername($start, $end): Collection
    {
        $statuses = [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS];

        $sql = "
            SELECT u.username AS username, COUNT(*) AS count, SUM(amount) AS sum
            FROM transactions AS t FORCE INDEX(transactions_confirmed_at_to_id_index)
            LEFT JOIN users AS u ON t.to_id = u.id
            WHERE t.status IN (?, ?)
            AND u.role = 3
            AND t.confirmed_at BETWEEN ? AND ?
            GROUP BY to_id
            ORDER BY username
        ";

        return collect(DB::select($sql, [...$statuses, $start, $end]))->keyBy('username');
    }

    /**
     * Get transaction stats grouped by date (for date).
     */
    public function getTransactionStatsByDate($start, $end): Collection
    {
        $statuses = [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS];

        $sql = "
            SELECT COUNT(*) AS count, SUM(amount) AS sum, DATE(confirmed_at) AS date
            FROM transactions AS t FORCE INDEX(transactions_confirmed_at_to_id_index)
            LEFT JOIN users AS u ON t.to_id = u.id
            WHERE t.status IN (?, ?)
            AND u.role = 3
            AND t.confirmed_at BETWEEN ? AND ?
            GROUP BY date
        ";

        return collect(DB::select($sql, [...$statuses, $start, $end]))->keyBy('date');
    }

    /**
     * Get withdraw stats grouped by username (for index).
     */
    public function getWithdrawStatsByUsername($start, $end): Collection
    {
        $statuses = [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS];
        $withdrawTypes = [Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_WITHDRAW];

        $sql = "
            SELECT u.username AS username, COUNT(*) AS count, SUM(amount) AS sum
            FROM transactions AS t FORCE INDEX(transactions_confirmed_at_from_id_index)
            LEFT JOIN users AS u ON t.from_id = u.id
            WHERE t.status IN (?, ?)
            AND t.sub_type IN (?, ?)
            AND u.role = 3
            AND t.confirmed_at BETWEEN ? AND ?
            GROUP BY from_id
            ORDER BY username
        ";

        return collect(DB::select($sql, [...$statuses, ...$withdrawTypes, $start, $end]))->keyBy('username');
    }

    /**
     * Get withdraw stats grouped by date (for date).
     */
    public function getWithdrawStatsByDate($start, $end): Collection
    {
        $statuses = [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS];
        $withdrawTypes = [Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_WITHDRAW];

        $sql = "
            SELECT COUNT(*) AS count, SUM(amount) AS sum, DATE(confirmed_at) AS date
            FROM transactions AS t FORCE INDEX(transactions_confirmed_at_from_id_index)
            LEFT JOIN users AS u ON t.from_id = u.id
            WHERE t.status IN (?, ?)
            AND t.sub_type IN (?, ?)
            AND u.role = 3
            AND t.confirmed_at BETWEEN ? AND ?
            GROUP BY date
        ";

        return collect(DB::select($sql, [...$statuses, ...$withdrawTypes, $start, $end]))->keyBy('date');
    }

    /**
     * Get system profit for a date range.
     */
    public function getSystemProfit($start, $end): object
    {
        $sql = "
            SELECT SUM(f.actual_profit) AS sum FROM transaction_fees AS f
            LEFT JOIN transactions AS t ON f.transaction_id = t.id
            WHERE t.confirmed_at BETWEEN ? AND ?
            AND f.user_id = 0
        ";

        return DB::select($sql, [$start, $end])[0];
    }
}
