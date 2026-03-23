<?php

namespace App\Jobs;

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

class ProcessNativeTransfer implements ShouldQueue
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
        // to_channel_account_id = 出款帳號（跟代付語意一致）
        $source = UserChannelAccount::findOrFail($transaction->to_channel_account_id);
        // from_channel_account = 接收方資訊（目標地址）
        $targetAddress = data_get($transaction->from_channel_account, 'bank_card_number', '');

        $chainNetwork = data_get($source->detail, UserChannelAccount::DETAIL_KEY_CHAIN_NETWORK, 'trc20');
        $adapter = ChainAdapterFactory::makeOrFail($chainNetwork);

        $nativeCurrency = Transaction::NATIVE_CURRENCIES[$chainNetwork] ?? 'Native';

        $privateKey = decrypt(data_get($source->detail, UserChannelAccount::DETAIL_KEY_ENCRYPTED_PRIVATE_KEY));

        try {
            $txHash = $adapter->sendNativeToken($source->account, $targetAddress, $transaction->amount, $privateKey);
            $privateKey = null;

            $transaction->update([
                'tx_hash'       => $txHash,
                'chain_network' => $chainNetwork,
                'status'        => Transaction::STATUS_SUCCESS,
                'confirmed_at'  => now(),
            ]);

            $this->log($transaction, "轉帳成功: {$transaction->amount} {$nativeCurrency} → {$targetAddress} (tx_hash: {$txHash})");
        } catch (\Throwable $e) {
            $privateKey = null;
            $this->log($transaction, "{$nativeCurrency} 轉帳失敗: {$e->getMessage()}", 'error');
            throw $e;
        }
    }

    /**
     * 所有重試都失敗後才標記訂單為失敗
     */
    public function failed(\Throwable $exception): void
    {
        try {
            $transaction = Transaction::find($this->transactionId);
            if ($transaction && $transaction->status !== Transaction::STATUS_FAILED) {
                $this->markFailed($transaction, $exception->getMessage());
            }
        } catch (\Throwable $e) {
            Log::error("ProcessNativeTransfer: failed() 處理異常", [
                'transaction_id' => $this->transactionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function markFailed(Transaction $transaction, string $message): void
    {
        $transaction->update([
            'status' => Transaction::STATUS_FAILED,
        ]);

        $this->log($transaction, "轉帳失敗: {$message}", 'error');
    }

    private function log(Transaction $transaction, string $message, string $level = 'info'): void
    {
        Log::$level("ProcessNativeTransfer: {$message}", ['transaction_id' => $transaction->id]);

        TransactionNote::create([
            'transaction_id' => $transaction->id,
            'user_id' => 0,
            'note' => "[原生幣轉帳] {$message}",
        ]);
    }
}
