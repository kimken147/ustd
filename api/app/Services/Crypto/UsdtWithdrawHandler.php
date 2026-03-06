<?php

namespace App\Services\Crypto;

use App\Models\Transaction;
use App\Models\TransactionNote;
use App\Models\UserChannelAccount;
use App\Services\Crypto\Adapters\ChainAdapterInterface;
use App\Services\Crypto\Adapters\Trc20Adapter;
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

        $account = UserChannelAccount::find($transaction->from_channel_account_id);
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

        // Pre-flight: check TRX balance for gas fees
        $minTrxBalance = config('services.trongrid.min_trx_balance', '30');
        $trxBalance = $adapter->getNativeBalance($fromAddress);

        if (bccomp($trxBalance, $minTrxBalance, 6) < 0) {
            $this->log($transaction, "TRX 餘額不足支付 Gas (餘額: {$trxBalance}, 最低: {$minTrxBalance})", [
                'trx_balance'  => $trxBalance,
                'min_required' => $minTrxBalance,
            ], 'error');
            throw new InsufficientBalanceException(
                "TRX balance {$trxBalance} below minimum {$minTrxBalance} for gas fees"
            );
        }

        $toAddress = data_get($transaction->to_channel_account, 'bank_card_number', '');
        $amount = $transaction->floating_amount ?? $transaction->amount;

        if (empty($toAddress)) {
            $this->log($transaction, '目標地址為空', level: 'error');
            return;
        }

        $privateKey = null;
        try {
            $privateKey = decrypt($encryptedKey);
            $chainTx = $adapter->sendTransaction($fromAddress, $toAddress, $amount, $privateKey);

            $transaction->update([
                'tx_hash' => $chainTx->txHash,
                'chain_network' => $chainNetwork,
            ]);

            // Dispatch delayed confirmation check
            ConfirmUsdtWithdraw::dispatch($transaction->id)->delay(now()->addSeconds(15));

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
        return match ($chainNetwork) {
            'trc20' => app(Trc20Adapter::class),
            default => throw new \InvalidArgumentException("不支援的鏈網路: {$chainNetwork}"),
        };
    }
}
