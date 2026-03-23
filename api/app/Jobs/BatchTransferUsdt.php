<?php

namespace App\Jobs;

use App\Jobs\ConfirmUsdtWithdraw;
use App\Models\ChainTransaction;
use App\Models\Channel;
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
        // to_channel_account_id = 出款帳號（跟代付語意一致）
        $source = UserChannelAccount::findOrFail($transaction->to_channel_account_id);
        // from_channel_account = 接收方資訊（目標地址）
        $targetAddress = data_get($transaction->from_channel_account, 'bank_card_number', '');

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

                    // 等待 gas 到帳（與代付邏輯一致）
                    sleep(5);

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

            // 建立鏈上交易記錄並標記已匹配，防止同步服務重複匹配
            $this->createMatchedChainTransactions($transaction, $chainTx->txHash, $source, $targetAddress);

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

    private function createMatchedChainTransactions(
        Transaction $transaction,
        string $txHash,
        UserChannelAccount $senderAccount,
        string $targetAddress,
    ): void {
        try {
            // OUT record for sender
            ChainTransaction::updateOrCreate(
                ['tx_hash' => $txHash, 'user_channel_account_id' => $senderAccount->id],
                [
                    'direction' => ChainTransaction::DIRECTION_OUT,
                    'from_address' => $senderAccount->account,
                    'to_address' => $targetAddress,
                    'amount' => $transaction->amount,
                    'block_timestamp' => now(),
                    'confirmations' => 0,
                    'source' => ChainTransaction::SOURCE_INTERNAL,
                    'match_status' => ChainTransaction::STATUS_MATCHED,
                    'matched_transaction_id' => $transaction->id,
                    'matched_at' => now(),
                ]
            );

            // IN record for receiver (if address belongs to a platform account)
            $receiverAccount = UserChannelAccount::whereIn('channel_code', Channel::USDT_CODES)
                ->where('account', $targetAddress)
                ->whereNull('deleted_at')
                ->first();

            if ($receiverAccount) {
                ChainTransaction::updateOrCreate(
                    ['tx_hash' => $txHash, 'user_channel_account_id' => $receiverAccount->id],
                    [
                        'direction' => ChainTransaction::DIRECTION_IN,
                        'from_address' => $senderAccount->account,
                        'to_address' => $targetAddress,
                        'amount' => $transaction->amount,
                        'block_timestamp' => now(),
                        'confirmations' => 0,
                        'source' => ChainTransaction::SOURCE_INTERNAL,
                        'match_status' => ChainTransaction::STATUS_MATCHED,
                        'matched_transaction_id' => $transaction->id,
                        'matched_at' => now(),
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::warning('BatchTransfer: 建立鏈上交易記錄失敗', [
                'transaction_id' => $transaction->id,
                'tx_hash' => $txHash,
                'error' => $e->getMessage(),
            ]);
        }
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
