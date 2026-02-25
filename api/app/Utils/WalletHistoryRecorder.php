<?php

namespace App\Utils;

use App\Models\Wallet;
use App\Models\WalletHistory;

class WalletHistoryRecorder
{
    /**
     * 系統操作紀錄（無操作者）
     */
    public function recordSystem(
        Wallet $wallet,
        int $type,
        array $delta,
        array $result,
        string $note
    ): WalletHistory {
        return WalletHistory::create([
            'user_id'     => $wallet->user->getKey(),
            'operator_id' => 0,
            'type'        => $type,
            'delta'       => $delta,
            'result'      => $result,
            'note'        => $note ?? '',
        ]);
    }

    /**
     * 有操作者的紀錄（人工調整、轉帳等）
     */
    public function recordWithOperator(
        Wallet $wallet,
        int $operatorId,
        int $type,
        array $delta,
        array $result,
        string $note
    ): WalletHistory {
        return WalletHistory::create([
            'user_id'     => $wallet->user->getKey(),
            'operator_id' => $operatorId,
            'type'        => $type,
            'delta'       => $delta,
            'result'      => $result,
            'note'        => $note ?? '',
        ]);
    }
}
