<?php

namespace App\Services\Crypto;

use App\Models\Transaction;
use App\Models\UserChannelAccount;
use App\Services\Crypto\Adapters\ChainAdapterInterface;
use App\Services\Crypto\Adapters\Trc20Adapter;
use App\Services\Crypto\Exceptions\InsufficientBalanceException;
use App\Services\Crypto\Exceptions\TransactionBroadcastException;
use Illuminate\Support\Facades\Log;

class UsdtWithdrawHandler
{
    public function handle(Transaction $transaction): void
    {
        $account = UserChannelAccount::find($transaction->from_channel_account_id);
        if (!$account) {
            Log::error('UsdtWithdrawHandler: 找不到出款帳號', ['transaction_id' => $transaction->id]);
            return;
        }

        $encryptedKey = data_get($account->detail, UserChannelAccount::DETAIL_KEY_ENCRYPTED_PRIVATE_KEY);
        if (!$encryptedKey) {
            Log::warning('UsdtWithdrawHandler: 帳號未設定私鑰，跳過自動出款', ['account_id' => $account->id]);
            return;
        }

        $chainNetwork = data_get($account->detail, UserChannelAccount::DETAIL_KEY_CHAIN_NETWORK, 'trc20');
        $adapter = $this->resolveAdapter($chainNetwork);

        $privateKey = decrypt($encryptedKey);
        $fromAddress = $account->account;
        $toAddress = data_get($transaction->to_channel_account, 'bank_card_number', '');
        $amount = $transaction->floating_amount ?? $transaction->amount;

        if (empty($toAddress)) {
            Log::error('UsdtWithdrawHandler: 目標地址為空', ['transaction_id' => $transaction->id]);
            return;
        }

        try {
            $chainTx = $adapter->sendTransaction($fromAddress, $toAddress, $amount, $privateKey);

            $transaction->update([
                'tx_hash' => $chainTx->txHash,
                'chain_network' => $chainNetwork,
            ]);

            Log::info('UsdtWithdrawHandler: 出款交易已廣播', [
                'transaction_id' => $transaction->id,
                'tx_hash' => $chainTx->txHash,
            ]);
        } catch (InsufficientBalanceException|TransactionBroadcastException $e) {
            Log::error('UsdtWithdrawHandler: 鏈上出款失敗', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function resolveAdapter(string $chainNetwork): ChainAdapterInterface
    {
        return match ($chainNetwork) {
            'trc20' => app(Trc20Adapter::class),
            default => throw new \InvalidArgumentException("不支援的鏈網路: {$chainNetwork}"),
        };
    }
}
