<?php

namespace App\Services\Crypto;

use App\Models\ChainTransaction;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class ChainTransactionMatchService
{
    /**
     * 嘗試自動比對單筆鏈上交易
     */
    public function matchTransaction(ChainTransaction $chainTx): bool
    {
        if ($chainTx->match_status !== ChainTransaction::STATUS_PENDING) {
            return false;
        }

        // 策略 1: tx_hash 精確匹配
        $matched = Transaction::where('tx_hash', $chainTx->tx_hash)->first();
        if ($matched) {
            $this->markMatched($chainTx, $matched->id);
            return true;
        }

        // 策略 2: 金額 + 時間窗口匹配（僅收款）
        if ($chainTx->direction === ChainTransaction::DIRECTION_IN && $chainTx->user_channel_account_id) {
            $candidates = Transaction::where('channel_code', 'USDT')
                ->where(function ($q) use ($chainTx) {
                    $q->where('from_channel_account_id', $chainTx->user_channel_account_id)
                        ->orWhere('to_channel_account_id', $chainTx->user_channel_account_id);
                })
                ->whereRaw('ABS(amount - ?) < 0.000001', [$chainTx->amount])
                ->whereBetween('created_at', [
                    $chainTx->block_timestamp->copy()->subMinutes(10),
                    $chainTx->block_timestamp->copy()->addMinutes(10),
                ])
                ->whereNull('tx_hash')
                ->get();

            if ($candidates->count() === 1) {
                $this->markMatched($chainTx, $candidates->first()->id);
                return true;
            }
        }

        return false;
    }

    /**
     * 批次比對所有 pending 記錄
     */
    public function matchPendingTransactions(): int
    {
        $pending = ChainTransaction::pending()->get();
        $matched = 0;

        foreach ($pending as $chainTx) {
            if ($this->matchTransaction($chainTx)) {
                $matched++;
            }
        }

        return $matched;
    }

    /**
     * 管理員手動關聯
     */
    public function manualMatch(ChainTransaction $chainTx, int $transactionId, int $userId): void
    {
        $chainTx->update([
            'match_status' => ChainTransaction::STATUS_MATCHED,
            'matched_transaction_id' => $transactionId,
            'matched_at' => now(),
            'matched_by' => $userId,
        ]);

        Log::info('ChainTransaction: 手動關聯', [
            'chain_tx_id' => $chainTx->id,
            'transaction_id' => $transactionId,
            'matched_by' => $userId,
        ]);
    }

    /**
     * 將超過指定時間的 pending 標記為 unmatched
     */
    public function markStaleAsUnmatched(int $hours = 24): int
    {
        return ChainTransaction::where('match_status', ChainTransaction::STATUS_PENDING)
            ->where('created_at', '<', now()->subHours($hours))
            ->update(['match_status' => ChainTransaction::STATUS_UNMATCHED]);
    }

    private function markMatched(ChainTransaction $chainTx, int $transactionId): void
    {
        $chainTx->update([
            'match_status' => ChainTransaction::STATUS_MATCHED,
            'matched_transaction_id' => $transactionId,
            'matched_at' => now(),
            'matched_by' => null,
        ]);
    }
}
