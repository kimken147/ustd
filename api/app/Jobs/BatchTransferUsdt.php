<?php

namespace App\Jobs;

use App\Jobs\ConfirmUsdtWithdraw;
use App\Models\ChainTransaction;
use App\Models\Channel;
use App\Models\Transaction;
use App\Models\TransactionNote;
use App\Models\UserChannelAccount;
use App\Services\Crypto\Adapters\ChainAdapterFactory;
use App\Services\Crypto\Adapters\ChainAdapterInterface;
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

        $gasTokenName = match ($chainNetwork) {
            'trc20' => 'TRX', 'erc20' => 'ETH', 'bep20' => 'BNB', default => 'Native',
        };

        $nativeBalance = $adapter->getNativeBalance($source->account);

        if ($transaction->currency === Transaction::CURRENCY_USDT) {
            // USDT 轉帳
            $onchainUsdtBalance = $adapter->getTokenBalance($source->account);

            if (bccomp($onchainUsdtBalance, '0', 6) <= 0) {
                $this->markFailed($transaction, "鏈上 USDT 餘額為 0，無法轉帳");
                return;
            }

            $this->transferUsdt($transaction, $adapter, $source, $targetAddress, $chainNetwork, $gasTokenName, $nativeBalance);
        } else {
            // 原生幣轉帳（TRX/ETH/BNB）
            // 有 bandwidth → 手續費由 bandwidth 支付，全部轉出
            // 沒 bandwidth → 需用原生幣支付手續費，預留 0.4
            $resources = $adapter->getAccountResources($source->account);
            $bandwidthAvailable = $resources['bandwidth_available'] ?? 0;
            $trxBuffer = $bandwidthAvailable >= 300 ? '0' : '0.4';

            if (bccomp($nativeBalance, $trxBuffer, 6) <= 0) {
                $this->markFailed($transaction, "{$gasTokenName} 不足，跳過轉帳 ({$gasTokenName}: {$nativeBalance})");
                return;
            }

            $this->transferNativeToken($transaction, $adapter, $source, $targetAddress, $chainNetwork, $gasTokenName, $nativeBalance, $trxBuffer);
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
            Log::error("BatchTransfer: failed() 處理異常", [
                'transaction_id' => $this->transactionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function transferUsdt(
        Transaction $transaction,
        ChainAdapterInterface $adapter,
        UserChannelAccount $source,
        string $targetAddress,
        string $chainNetwork,
        string $gasTokenName,
        string $nativeBalance,
    ): void {
        // 動態預估所需 Gas
        $requiredGas = match ($chainNetwork) {
            'trc20' => $adapter instanceof \App\Services\Crypto\Adapters\Trc20Adapter
                ? $adapter->estimateTransferFee($source->account, $targetAddress ?: $source->account, $transaction->amount)
                : config('services.trongrid.min_trx_balance', '30'),
            'erc20' => config('services.ethereum.min_native_balance', '0.005'),
            'bep20' => config('services.bsc.min_native_balance', '0.005'),
            default => '0',
        };

        if (bccomp($nativeBalance, $requiredGas, 6) < 0) {
            $this->markFailed($transaction, "{$gasTokenName} / Energy 不足，請手動補充 (餘額: {$nativeBalance}, 需要: {$requiredGas})");
            return;
        }

        $privateKey = decrypt(data_get($source->detail, UserChannelAccount::DETAIL_KEY_ENCRYPTED_PRIVATE_KEY));

        try {
            $chainTx = $adapter->sendTransaction($source->account, $targetAddress, $transaction->amount, $privateKey);
            $privateKey = null;

            $transaction->update([
                'tx_hash'       => $chainTx->txHash,
                'chain_network' => $chainNetwork,
            ]);

            $this->createMatchedChainTransactions($transaction, $chainTx->txHash, $source, $targetAddress, ChainTransaction::TOKEN_TYPE_USDT, $transaction->amount);
            ConfirmUsdtWithdraw::dispatch($transaction->id)->delay(now()->addSeconds(15));

            $this->log($transaction, "USDT 交易已廣播 (tx_hash: {$chainTx->txHash})");
        } catch (\Throwable $e) {
            $privateKey = null;
            $this->log($transaction, "USDT 轉帳失敗: {$e->getMessage()}", 'error');
            throw $e;
        }
    }

    private function transferNativeToken(
        Transaction $transaction,
        ChainAdapterInterface $adapter,
        UserChannelAccount $source,
        string $targetAddress,
        string $chainNetwork,
        string $gasTokenName,
        string $nativeBalance,
        string $trxBuffer,
    ): void {
        $transferAmount = bcsub($nativeBalance, $trxBuffer, 6);

        // 更新交易金額為實際轉帳金額（扣除 buffer 後）
        $transaction->update([
            'amount' => $transferAmount,
            'floating_amount' => $transferAmount,
        ]);

        $privateKey = decrypt(data_get($source->detail, UserChannelAccount::DETAIL_KEY_ENCRYPTED_PRIVATE_KEY));

        try {
            $txHash = $adapter->sendNativeToken($source->account, $targetAddress, $transferAmount, $privateKey);
            $privateKey = null;

            $transaction->update([
                'tx_hash'       => $txHash,
                'chain_network' => $chainNetwork,
            ]);

            $tokenType = match ($chainNetwork) {
                'trc20' => ChainTransaction::TOKEN_TYPE_TRX,
                'erc20' => ChainTransaction::TOKEN_TYPE_ETH,
                'bep20' => ChainTransaction::TOKEN_TYPE_BNB,
                default => $gasTokenName,
            };

            $this->createMatchedChainTransactions($transaction, $txHash, $source, $targetAddress, $tokenType, $transferAmount);
            ConfirmUsdtWithdraw::dispatch($transaction->id)->delay(now()->addSeconds(15));

            $this->log($transaction, "{$gasTokenName} 轉帳已廣播: {$transferAmount} {$gasTokenName} (tx_hash: {$txHash})");
        } catch (\Throwable $e) {
            $privateKey = null;
            $this->log($transaction, "{$gasTokenName} 轉帳失敗: {$e->getMessage()}", 'error');
            throw $e;
        }
    }

    private function markFailed(Transaction $transaction, string $message): void
    {
        $transaction->update([
            'status' => Transaction::STATUS_FAILED,
        ]);

        $this->log($transaction, "轉帳失敗: {$message}", 'error');
    }

    private function createMatchedChainTransactions(
        Transaction $transaction,
        string $txHash,
        UserChannelAccount $senderAccount,
        string $targetAddress,
        string $tokenType = 'USDT',
        ?string $amount = null,
    ): void {
        $txAmount = $amount ?? $transaction->amount;

        try {
            // OUT record for sender
            ChainTransaction::updateOrCreate(
                ['tx_hash' => $txHash, 'user_channel_account_id' => $senderAccount->id],
                [
                    'direction' => ChainTransaction::DIRECTION_OUT,
                    'from_address' => $senderAccount->account,
                    'to_address' => $targetAddress,
                    'amount' => $txAmount,
                    'token_type' => $tokenType,
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
                        'amount' => $txAmount,
                        'token_type' => $tokenType,
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
