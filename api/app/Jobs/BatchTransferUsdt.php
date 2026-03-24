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

        $gasTokenName = match ($chainNetwork) {
            'trc20' => 'TRX', 'erc20' => 'ETH', 'bep20' => 'BNB', default => 'Native',
        };

        // 動態預估所需 Gas（與代付 UsdtWithdrawHandler 一致）
        $requiredGas = match ($chainNetwork) {
            'trc20' => $adapter instanceof \App\Services\Crypto\Adapters\Trc20Adapter
                ? $adapter->estimateTransferFee($source->account, $targetAddress ?: $source->account, $transaction->amount)
                : config('services.trongrid.min_trx_balance', '30'),
            'erc20' => config('services.ethereum.min_native_balance', '0.005'),
            'bep20' => config('services.bsc.min_native_balance', '0.005'),
            default => '0',
        };

        // 加 buffer：TRC-20 多 5 TRX，其他多 10%
        $buffer = match ($chainNetwork) {
            'trc20' => '5',
            default => bcmul($requiredGas, '0.1', 6),
        };
        $requiredWithBuffer = bcadd($requiredGas, $buffer, 6);

        // 檢查 gas 餘額
        $nativeBalance = $adapter->getNativeBalance($source->account);

        if (bccomp($nativeBalance, $requiredWithBuffer, 6) < 0) {
            // 計算差額（只補不足的部分）
            $deficit = bcsub($requiredWithBuffer, $nativeBalance, 6);

            if ($source->parent_account_id) {
                $parent = $source->parentAccount;
                $parentKey = decrypt(data_get($parent->detail, UserChannelAccount::DETAIL_KEY_ENCRYPTED_PRIVATE_KEY));

                try {
                    $adapter->sendNativeToken($parent->account, $source->account, $deficit, $parentKey);
                    $parentKey = null;

                    $this->log($transaction, "已從母地址補充 {$deficit} {$gasTokenName} (預估需要: {$requiredGas}, 現有: {$nativeBalance}, parent: {$parent->account})");

                    // 等待 gas 到帳（與代付邏輯一致）
                    sleep(5);

                } catch (\Throwable $e) {
                    $parentKey = null;
                    // 不呼叫 markFailed — 讓 retry 有機會重試
                    $this->log($transaction, "補充 {$gasTokenName} 失敗: {$e->getMessage()}", 'error');
                    throw $e;
                }
            } else {
                // 無母地址可補充，直接失敗（不可重試解決）
                $this->markFailed($transaction, "{$gasTokenName} 不足且無母地址可補充 (餘額: {$nativeBalance}, 需要: {$requiredWithBuffer})");
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

            // 歸集剩餘原生代幣到目標地址
            $this->transferRemainingGas($adapter, $source, $targetAddress, $chainNetwork, $gasTokenName, $transaction);
        } catch (\Throwable $e) {
            $privateKey = null;
            // 不呼叫 markFailed — 讓 retry 有機會重試
            $this->log($transaction, "USDT 轉帳失敗: {$e->getMessage()}", 'error');
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
            Log::error("BatchTransfer: failed() 處理異常", [
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

    private function transferRemainingGas(
        $adapter,
        UserChannelAccount $source,
        string $targetAddress,
        string $chainNetwork,
        string $gasTokenName,
        Transaction $transaction,
    ): void {
        try {
            // 查詢剩餘原生代幣餘額
            $remainingBalance = $adapter->getNativeBalance($source->account);

            // 計算原生轉帳手續費
            $transferFee = match ($chainNetwork) {
                'trc20' => $adapter instanceof \App\Services\Crypto\Adapters\Trc20Adapter
                    ? $adapter->estimateNativeTransferFee($source->account)
                    : '1',
                default => $this->estimateEvmNativeTransferFee($adapter, $chainNetwork),
            };

            // 可轉出金額 = 餘額 - 手續費
            $transferable = bcsub($remainingBalance, $transferFee, 6);

            // 取得最低轉帳門檻
            $minAmount = match ($chainNetwork) {
                'trc20' => config('services.trongrid.min_gas_transfer_amount', '1'),
                'erc20' => config('services.ethereum.min_gas_transfer_amount', '0.001'),
                'bep20' => config('services.bsc.min_gas_transfer_amount', '0.001'),
                default => '0',
            };

            if (bccomp($transferable, $minAmount, 6) <= 0) {
                $this->log($transaction, "剩餘 {$gasTokenName} 不足轉出門檻 (餘額: {$remainingBalance}, 手續費: {$transferFee}, 門檻: {$minAmount})");
                return;
            }

            // 執行原生代幣轉帳
            $privateKey = decrypt(data_get($source->detail, UserChannelAccount::DETAIL_KEY_ENCRYPTED_PRIVATE_KEY));
            $gasTxHash = $adapter->sendNativeToken($source->account, $targetAddress, $transferable, $privateKey);
            $privateKey = null;

            $this->log($transaction, "已歸集 {$transferable} {$gasTokenName} 到目標地址 (tx_hash: {$gasTxHash})");
        } catch (\Throwable $e) {
            // Gas 轉帳失敗不影響 USDT 轉帳結果
            $this->log($transaction, "歸集 {$gasTokenName} 失敗: {$e->getMessage()}", 'warning');
        }
    }

    private function estimateEvmNativeTransferFee($adapter, string $chainNetwork): string
    {
        $configKey = $chainNetwork === 'erc20' ? 'ethereum' : 'bsc';
        $gasLimit = config("services.{$configKey}.gas_limit_native_transfer", 21000);

        // 取得當前 gas price
        $gasPrice = $adapter->getGasPrice();

        // fee = gasLimit × gasPrice (in wei), convert to ETH/BNB
        $feeWei = bcmul((string) $gasLimit, $gasPrice, 0);
        $fee = bcdiv($feeWei, bcpow('10', '18'), 18);

        // +10% buffer
        return bcmul($fee, '1.1', 18);
    }
}
