<?php

namespace App\Jobs;

use App\Jobs\ConfirmUsdtWithdraw;
use App\Models\Transaction;
use App\Models\TransactionNote;
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
        public readonly int $transactionId,
    ) {}

    public function handle(): void
    {
        $transaction = Transaction::findOrFail($this->transactionId);
        $source = UserChannelAccount::findOrFail($transaction->from_channel_account_id);
        $targetAddress = data_get($transaction->to_channel_account, 'account', '');

        if (empty($targetAddress) && $transaction->to_channel_account_id) {
            $targetAddress = UserChannelAccount::find($transaction->to_channel_account_id)?->account ?? '';
        }

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

                    $this->log($transaction, "已從母地址補充 {$sendAmount} gas (parent: {$parent->account})");

                } catch (\Throwable $e) {
                    $parentKey = null;
                    $this->markFailed($transaction, "補充 gas 失敗: {$e->getMessage()}");
                    throw $e;
                }
            } else {
                $this->markFailed($transaction, "Gas 不足且無母地址可補充 (餘額: {$nativeBalance})");
                throw new \RuntimeException("Gas 不足: {$source->account}");
            }
        }

        // 執行 USDT 轉帳
        $privateKey = decrypt(data_get($source->detail, UserChannelAccount::DETAIL_KEY_ENCRYPTED_PRIVATE_KEY));

        try {
            $chainTx = $adapter->sendTransaction($source->account, $targetAddress, $transaction->amount, $privateKey);
            $privateKey = null;

            $transaction->update([
                'tx_hash'       => $chainTx->txHash,
                'chain_network' => $chainNetwork,
            ]);

            // 延遲確認鏈上交易結果，由 ConfirmUsdtWithdraw 走 markAsSuccess 流程
            ConfirmUsdtWithdraw::dispatch($transaction->id)->delay(now()->addSeconds(15));

            $this->log($transaction, "交易已廣播，等待鏈上確認 (tx_hash: {$chainTx->txHash})");
        } catch (\Throwable $e) {
            $privateKey = null;
            $this->markFailed($transaction, $e->getMessage());
            throw $e;
        }
    }

    private function markFailed(Transaction $transaction, string $message): void
    {
        $transaction->update([
            'status'       => Transaction::STATUS_FAILED,
            'confirmed_at' => now(),
        ]);

        $this->log($transaction, "轉帳失敗: {$message}", 'error');
    }

    private function log(Transaction $transaction, string $message, string $level = 'info'): void
    {
        Log::$level("BatchTransfer: {$message}", ['transaction_id' => $transaction->id]);

        TransactionNote::create([
            'transaction_id' => $transaction->id,
            'user_id' => 0,
            'note' => "[批量轉帳] {$message}",
        ]);
    }
}
