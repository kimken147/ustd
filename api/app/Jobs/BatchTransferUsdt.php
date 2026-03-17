<?php

namespace App\Jobs;

use App\Models\FundTransferLog;
use App\Models\UserChannelAccount;
use App\Services\Crypto\Adapters\ChainAdapterFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BatchTransferUsdt implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 60;

    public function __construct(
        public readonly int $logId,
    ) {}

    public function handle(): void
    {
        $log = FundTransferLog::findOrFail($this->logId);
        $log->update(['status' => FundTransferLog::STATUS_PROCESSING]);

        $source = UserChannelAccount::findOrFail($log->source_account_id);
        $chainNetwork = data_get($source->detail, UserChannelAccount::DETAIL_KEY_CHAIN_NETWORK, 'trc20');
        $adapter = ChainAdapterFactory::makeOrFail($chainNetwork);

        // 取得最低原生代幣餘額門檻
        $minNative = match ($chainNetwork) {
            'trc20' => config('services.trongrid.min_trx_balance', '30'),
            'erc20' => config('services.ethereum.min_native_balance', '0.005'),
            'bep20' => config('services.bsc.min_native_balance', '0.005'),
            default => '0',
        };

        // 檢查 gas 餘額
        $nativeBalance = $adapter->getNativeBalance($source->account);

        if (bccomp($nativeBalance, $minNative, 6) < 0) {
            // 嘗試從母地址補充 gas
            if ($source->parent_account_id) {
                $parent = $source->parentAccount;
                $parentKey = decrypt(data_get($parent->detail, UserChannelAccount::DETAIL_KEY_ENCRYPTED_PRIVATE_KEY));

                try {
                    $extra = match ($chainNetwork) {
                        'trc20' => '5',
                        default => '0.001',
                    };
                    $sendAmount = bcadd($minNative, $extra, 6);
                    $adapter->sendNativeToken($parent->account, $source->account, $sendAmount, $parentKey);
                    $parentKey = null;

                    Log::info('BatchTransfer: 已從母地址補充 gas', [
                        'parent' => $parent->account,
                        'child'  => $source->account,
                        'amount' => $sendAmount,
                    ]);
                } catch (\Throwable $e) {
                    $parentKey = null;
                    $this->markFailed($log, "補充 gas 失敗: {$e->getMessage()}");
                    throw $e;
                }
            } else {
                $this->markFailed($log, "Gas 不足且無母地址可補充 (餘額: {$nativeBalance})");
                throw new \RuntimeException("Gas 不足: {$source->account}");
            }
        }

        // 執行 USDT 轉帳
        $privateKey = decrypt(data_get($source->detail, UserChannelAccount::DETAIL_KEY_ENCRYPTED_PRIVATE_KEY));

        try {
            $chainTx = $adapter->sendTransaction($source->account, $log->target_address, $log->amount, $privateKey);
            $privateKey = null;

            $log->update([
                'status'  => FundTransferLog::STATUS_SUCCESS,
                'tx_hash' => $chainTx->txHash,
            ]);

            Log::info('BatchTransfer: 轉帳完成', [
                'log_id'  => $log->id,
                'source'  => $source->account,
                'target'  => $log->target_address,
                'amount'  => $log->amount,
                'tx_hash' => $chainTx->txHash,
            ]);
        } catch (\Throwable $e) {
            $privateKey = null;
            $this->markFailed($log, $e->getMessage());
            throw $e;
        }
    }

    private function markFailed(FundTransferLog $log, string $message): void
    {
        $log->update([
            'status'        => FundTransferLog::STATUS_FAILED,
            'error_message' => $message,
        ]);

        Log::error('BatchTransfer: 轉帳失敗', [
            'log_id'  => $log->id,
            'source'  => $log->source_address,
            'target'  => $log->target_address,
            'amount'  => $log->amount,
            'error'   => $message,
        ]);
    }
}
