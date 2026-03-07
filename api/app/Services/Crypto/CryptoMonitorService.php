<?php

namespace App\Services\Crypto;

use App\Models\Transaction;
use App\Models\UsdtDepositMonitor;
use App\Services\Crypto\Adapters\ChainAdapterInterface;
use App\Services\Crypto\Adapters\Trc20Adapter;
use App\Services\Transaction\TransactionStatusService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CryptoMonitorService
{
    public function __construct(
        private readonly TransactionStatusService $transactionStatusService,
    ) {}

    /**
     * 輪詢所有待監控的 USDT 收款地址
     */
    public function poll(): void
    {
        $pendingMonitors = UsdtDepositMonitor::pending()
            ->with(['transaction', 'userChannelAccount'])
            ->get();

        if ($pendingMonitors->isEmpty()) {
            return;
        }

        // 按鏈網絡分組處理
        $grouped = $pendingMonitors->groupBy('chain_network');

        foreach ($grouped as $network => $monitors) {
            $adapter = $this->resolveAdapter($network);
            if (!$adapter) {
                Log::warning("CryptoMonitorService: 找不到 {$network} 的 Adapter");
                continue;
            }

            // 按地址分組，減少 API 呼叫次數
            $byAddress = $monitors->groupBy('address');

            foreach ($byAddress as $address => $addressMonitors) {
                $this->pollAddress($adapter, $address, $addressMonitors);
            }
        }

        // 處理過期的監控記錄
        $this->expireTimedOutMonitors();
    }

    /**
     * 輪詢單一地址的鏈上交易，比對待收款記錄
     */
    private function pollAddress(ChainAdapterInterface $adapter, string $address, $monitors): void
    {
        $chainTxs = $adapter->fetchIncomingTransactions($address);

        // 更新最後輪詢時間
        UsdtDepositMonitor::where('address', $address)
            ->where('status', UsdtDepositMonitor::STATUS_PENDING)
            ->update(['last_polled_at' => now()]);

        if ($chainTxs->isEmpty()) {
            return;
        }

        foreach ($monitors as $monitor) {
            foreach ($chainTxs as $chainTx) {
                // 如果使用者有提供 tx_hash，以 tx_hash + 金額比對
                if ($monitor->user_tx_hash) {
                    if ($chainTx->txHash !== $monitor->user_tx_hash) {
                        continue;
                    }
                    if (bccomp($monitor->expected_amount, $chainTx->amount, 6) !== 0) {
                        continue;
                    }
                } else {
                    // 沒有 user_tx_hash，僅以金額比對
                    if (bccomp($monitor->expected_amount, $chainTx->amount, 6) !== 0) {
                        continue;
                    }
                }

                // 檢查此 tx_hash 是否已被使用
                $alreadyUsed = UsdtDepositMonitor::where('tx_hash', $chainTx->txHash)->exists();
                if ($alreadyUsed) {
                    continue;
                }

                $this->confirmDeposit($monitor, $chainTx);
                break; // 此 monitor 已匹配，跳到下一個
            }
        }
    }

    /**
     * 確認收款成功：更新 monitor 狀態並將交易標記為成功
     */
    private function confirmDeposit(UsdtDepositMonitor $monitor, $chainTx): void
    {
        DB::transaction(function () use ($monitor, $chainTx) {
            $monitor->update([
                'status' => UsdtDepositMonitor::STATUS_CONFIRMED,
                'received_amount' => $chainTx->amount,
                'tx_hash' => $chainTx->txHash,
                'matched_at' => now(),
                'confirmed_at' => now(),
            ]);

            $transaction = Transaction::lockForUpdate()->find($monitor->transaction_id);

            if ($transaction && $transaction->status === Transaction::STATUS_PAYING) {
                $transaction->update([
                    'tx_hash' => $chainTx->txHash,
                    'chain_network' => $monitor->chain_network,
                ]);

                $this->transactionStatusService->markAsSuccess($transaction);

                Log::info('CryptoMonitorService: 收款確認成功', [
                    'transaction_id' => $transaction->id,
                    'tx_hash' => $chainTx->txHash,
                    'amount' => $chainTx->amount,
                ]);
            }
        });
    }

    /**
     * 將逾時的監控記錄標記為過期
     */
    private function expireTimedOutMonitors(): void
    {
        UsdtDepositMonitor::where('status', UsdtDepositMonitor::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => UsdtDepositMonitor::STATUS_EXPIRED]);
    }

    /**
     * 根據鏈網絡取得對應的 Adapter
     */
    private function resolveAdapter(string $network): ?ChainAdapterInterface
    {
        return match ($network) {
            'trc20' => app(Trc20Adapter::class),
            // 'erc20' => app(Erc20Adapter::class),  // 後續擴充
            // 'bep20' => app(Bep20Adapter::class),  // 後續擴充
            default => null,
        };
    }
}
