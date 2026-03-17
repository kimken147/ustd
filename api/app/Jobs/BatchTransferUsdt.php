<?php

namespace App\Jobs;

use App\Models\UserChannelAccount;
use App\Services\Crypto\Adapters\ChainAdapterFactory;
use App\Services\Crypto\Adapters\Trc20Adapter;
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
        public readonly int    $sourceAccountId,
        public readonly string $targetAddress,
        public readonly string $amount,
    ) {}

    public function handle(): void
    {
        $source = UserChannelAccount::findOrFail($this->sourceAccountId);
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
                    Log::error('BatchTransfer: 補充 gas 失敗', [
                        'account' => $source->account,
                        'error'   => $e->getMessage(),
                    ]);
                    throw $e;
                }
            } else {
                Log::warning('BatchTransfer: gas 不足且無母地址可補充', [
                    'account'        => $source->account,
                    'native_balance' => $nativeBalance,
                    'min_required'   => $minNative,
                ]);
                throw new \RuntimeException("Gas 不足: {$source->account}");
            }
        }

        // 執行 USDT 轉帳
        $privateKey = decrypt(data_get($source->detail, UserChannelAccount::DETAIL_KEY_ENCRYPTED_PRIVATE_KEY));

        try {
            $chainTx = $adapter->sendTransaction($source->account, $this->targetAddress, $this->amount, $privateKey);
            $privateKey = null;

            Log::info('BatchTransfer: 轉帳完成', [
                'source'  => $source->account,
                'target'  => $this->targetAddress,
                'amount'  => $this->amount,
                'tx_hash' => $chainTx->txHash,
            ]);
        } catch (\Throwable $e) {
            $privateKey = null;
            Log::error('BatchTransfer: USDT 轉帳失敗', [
                'source' => $source->account,
                'target' => $this->targetAddress,
                'amount' => $this->amount,
                'error'  => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
