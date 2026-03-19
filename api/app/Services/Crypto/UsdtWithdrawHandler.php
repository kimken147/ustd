<?php

namespace App\Services\Crypto;

use App\Models\ChainTransaction;
use App\Models\Channel;
use App\Models\Transaction;
use App\Models\TransactionNote;
use App\Models\UserChannelAccount;
use App\Services\Crypto\Adapters\ChainAdapterInterface;
use App\Services\Crypto\Adapters\ChainAdapterFactory;
use App\Jobs\ConfirmUsdtWithdraw;
use App\Services\Crypto\Exceptions\InsufficientBalanceException;
use App\Services\Crypto\Exceptions\TransactionBroadcastException;
use Illuminate\Support\Facades\Log;

class UsdtWithdrawHandler
{
    public function handle(Transaction $transaction): void
    {
        // Idempotency: skip if already broadcast
        if (!empty($transaction->tx_hash)) {
            $this->log($transaction, '交易已有 tx_hash，跳過', [
                'tx_hash' => $transaction->tx_hash,
            ]);
            return;
        }

        $account = UserChannelAccount::find($transaction->to_channel_account_id);
        if (!$account) {
            $this->log($transaction, '找不到出款帳號', level: 'error');
            return;
        }

        $encryptedKey = data_get($account->detail, UserChannelAccount::DETAIL_KEY_ENCRYPTED_PRIVATE_KEY);
        if (!$encryptedKey) {
            $this->log($transaction, '帳號未設定私鑰，跳過自動出款', [
                'account_id' => $account->id,
            ], 'warning');
            return;
        }

        $chainNetwork = data_get($account->detail, UserChannelAccount::DETAIL_KEY_CHAIN_NETWORK, 'trc20');
        $adapter = $this->resolveAdapter($chainNetwork);

        $fromAddress = $account->account;

        // Pre-flight: check native token balance for gas fees
        $gasTokenName = match ($chainNetwork) {
            'trc20' => 'TRX', 'erc20' => 'ETH', 'bep20' => 'BNB', default => 'Native',
        };
        $nativeBalance = $adapter->getNativeBalance($fromAddress);

        // 動態預估所需 Gas（TRC-20 用 Energy 預估，其他用固定最低值）
        $toAddress = data_get($transaction->from_channel_account, 'bank_card_number', '');
        $txAmount = $transaction->floating_amount ?? $transaction->amount;
        $requiredGas = match ($chainNetwork) {
            'trc20' => $adapter instanceof \App\Services\Crypto\Adapters\Trc20Adapter
                ? $adapter->estimateTransferFee($fromAddress, $toAddress ?: $fromAddress, $txAmount)
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

        if (bccomp($nativeBalance, $requiredWithBuffer, 6) < 0) {
            // 計算差額
            $deficit = bcsub($requiredWithBuffer, $nativeBalance, 6);

            // 嘗試從母地址補充 Gas
            if ($account->address_type === UserChannelAccount::ADDRESS_TYPE_CHILD && $account->parent_account_id) {
                $parentAccount = UserChannelAccount::find($account->parent_account_id);
                $parentKey = $parentAccount ? data_get($parentAccount->detail, UserChannelAccount::DETAIL_KEY_ENCRYPTED_PRIVATE_KEY) : null;

                if ($parentAccount && $parentKey) {
                    try {
                        $decryptedParentKey = decrypt($parentKey);
                        $gasTxHash = $adapter->sendNativeToken(
                            $parentAccount->account,
                            $fromAddress,
                            $deficit,
                            $decryptedParentKey
                        );
                        $decryptedParentKey = null;

                        $this->log($transaction, "已從母地址補充 {$deficit} {$gasTokenName} (預估需要: {$requiredGas}, 現有: {$nativeBalance}, tx: {$gasTxHash})", [
                            'gas_tx_hash' => $gasTxHash,
                            'parent_account_id' => $parentAccount->id,
                            'estimated_fee' => $requiredGas,
                            'deficit' => $deficit,
                        ]);

                        // 等待 Gas 到帳後再繼續出款
                        sleep(5);
                    } catch (\Throwable $e) {
                        $this->log($transaction, "從母地址補充 {$gasTokenName} 失敗: {$e->getMessage()}", [
                            'parent_account_id' => $parentAccount->id,
                            'error' => $e->getMessage(),
                        ], 'error');
                        throw new InsufficientBalanceException(
                            "{$gasTokenName} balance {$nativeBalance}, need {$requiredWithBuffer}, gas top-up from parent failed"
                        );
                    }
                } else {
                    $this->log($transaction, "{$gasTokenName} 餘額不足且無法從母地址補充 (餘額: {$nativeBalance}, 需要: {$requiredWithBuffer})", [
                        'native_balance' => $nativeBalance,
                        'required'       => $requiredWithBuffer,
                    ], 'error');
                    throw new InsufficientBalanceException(
                        "{$gasTokenName} balance {$nativeBalance}, need {$requiredWithBuffer}, no parent account available"
                    );
                }
            } else {
                $this->log($transaction, "{$gasTokenName} 餘額不足支付 Gas (餘額: {$nativeBalance}, 需要: {$requiredWithBuffer})", [
                    'native_balance' => $nativeBalance,
                    'required'       => $requiredWithBuffer,
                    'gas_token'      => $gasTokenName,
                ], 'error');
                throw new InsufficientBalanceException(
                    "{$gasTokenName} balance {$nativeBalance}, need {$requiredWithBuffer} for gas fees"
                );
            }
        }

        if (empty($toAddress)) {
            $this->log($transaction, '目標地址為空', level: 'error');
            return;
        }

        $privateKey = null;
        try {
            $privateKey = decrypt($encryptedKey);
            $chainTx = $adapter->sendTransaction($fromAddress, $toAddress, $txAmount, $privateKey);

            $transaction->update([
                'tx_hash' => $chainTx->txHash,
                'chain_network' => $chainNetwork,
            ]);

            // Dispatch delayed confirmation check
            ConfirmUsdtWithdraw::dispatch($transaction->id)->delay(now()->addSeconds(15));

            // 主動建立鏈上交易記錄並標記匹配，避免 sync→match 非同步流程誤匹配
            $this->createMatchedChainTransactions(
                $transaction, $chainTx->txHash, $account, $fromAddress, $toAddress, $txAmount
            );

            $this->log($transaction, "出款交易已廣播 (tx_hash: {$chainTx->txHash})", [
                'tx_hash' => $chainTx->txHash,
            ]);
        } catch (InsufficientBalanceException|TransactionBroadcastException $e) {
            $this->log($transaction, "鏈上出款失敗: {$e->getMessage()}", [
                'error' => $e->getMessage(),
            ], 'error');
            throw $e;
        } catch (\Throwable $e) {
            $this->log($transaction, "未預期錯誤: {$e->getMessage()}", [
                'error' => $e->getMessage(),
            ], 'error');
            throw $e;
        } finally {
            $privateKey = null;
        }
    }

    /**
     * 廣播成功後主動建立 ChainTransaction 記錄並標記為已匹配。
     * 出金方建立 OUT 記錄；若收款方也是平台帳號（內轉），建立 IN 記錄。
     * 這樣後續 ChainTransactionSyncService 同步時會 updateOrCreate 命中已有記錄，
     * 不會再觸發 ChainTransactionMatchService 的自動比對。
     */
    private function createMatchedChainTransactions(
        Transaction $transaction,
        string $txHash,
        UserChannelAccount $senderAccount,
        string $fromAddress,
        string $toAddress,
        string $amount,
    ): void {
        try {
            // OUT record for sender
            ChainTransaction::updateOrCreate(
                ['tx_hash' => $txHash, 'user_channel_account_id' => $senderAccount->id],
                [
                    'direction' => ChainTransaction::DIRECTION_OUT,
                    'from_address' => $fromAddress,
                    'to_address' => $toAddress,
                    'amount' => $amount,
                    'block_timestamp' => now(),
                    'confirmations' => 0,
                    'source' => ChainTransaction::SOURCE_INTERNAL,
                    'match_status' => ChainTransaction::STATUS_MATCHED,
                    'matched_transaction_id' => $transaction->id,
                    'matched_at' => now(),
                ]
            );

            // IN record for receiver (only if address belongs to a platform account)
            $receiverAccount = UserChannelAccount::where('channel_code', Channel::CODE_USDT)
                ->where('account', $toAddress)
                ->whereNull('deleted_at')
                ->first();

            if ($receiverAccount) {
                ChainTransaction::updateOrCreate(
                    ['tx_hash' => $txHash, 'user_channel_account_id' => $receiverAccount->id],
                    [
                        'direction' => ChainTransaction::DIRECTION_IN,
                        'from_address' => $fromAddress,
                        'to_address' => $toAddress,
                        'amount' => $amount,
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
            // 不影響主流程，僅記錄錯誤
            Log::warning('UsdtWithdrawHandler: 建立匹配鏈上交易記錄失敗', [
                'transaction_id' => $transaction->id,
                'tx_hash' => $txHash,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function log(Transaction $transaction, string $message, array $context = [], string $level = 'info'): void
    {
        $logContext = array_merge(['transaction_id' => $transaction->id], $context);
        Log::$level("UsdtWithdrawHandler: {$message}", $logContext);

        TransactionNote::create([
            'transaction_id' => $transaction->id,
            'user_id' => 0,
            'note' => "[USDT出款] {$message}",
        ]);
    }

    private function resolveAdapter(string $chainNetwork): ChainAdapterInterface
    {
        return ChainAdapterFactory::makeOrFail($chainNetwork);
    }
}
