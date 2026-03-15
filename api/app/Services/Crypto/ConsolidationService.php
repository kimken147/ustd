<?php

namespace App\Services\Crypto;

use App\Models\UserChannelAccount;
use App\Services\Crypto\Adapters\ChainAdapterInterface;
use App\Services\Crypto\Adapters\Trc20Adapter;
use Illuminate\Support\Facades\Log;

class ConsolidationService
{
    /**
     * 歸集單一子地址的 USDT 到主地址
     *
     * @return array{status: string, tx_hash?: string, gas_tx_hash?: string, amount?: string, error?: string}
     */
    public function consolidate(UserChannelAccount $childAccount): array
    {
        $parentAccount = $childAccount->parentAccount;
        if (!$parentAccount) {
            return ['status' => 'error', 'error' => '無母地址'];
        }

        $chainNetwork = data_get($childAccount->detail, UserChannelAccount::DETAIL_KEY_CHAIN_NETWORK, 'trc20');
        $adapter = $this->resolveAdapter($chainNetwork);

        // 1. 查詢子地址 USDT 餘額
        $usdtBalance = $adapter->getTokenBalance($childAccount->account);
        if (bccomp($usdtBalance, '0.000001', 6) <= 0) {
            return ['status' => 'skip', 'error' => 'USDT 餘額不足'];
        }

        // 2. 查詢子地址原生代幣餘額，不夠則從主地址送
        $nativeBalance = $adapter->getNativeBalance($childAccount->account);
        $minNative = $this->getMinNativeBalance($chainNetwork);
        $gasTokenName = $this->getGasTokenName($chainNetwork);
        $gasTxHash = null;

        if (bccomp($nativeBalance, $minNative, 6) < 0) {
            $parentKey = $this->getPrivateKey($parentAccount);
            if (!$parentKey) {
                return ['status' => 'error', 'error' => '母地址無私鑰'];
            }

            try {
                $extra = match ($chainNetwork) {
                    'trc20' => '5',
                    default => '0.001',
                };
                $sendAmount = bcadd($minNative, $extra, 6); // 多送一點確保夠用
                $gasTxHash = $adapter->sendNativeToken(
                    $parentAccount->account,
                    $childAccount->account,
                    $sendAmount,
                    $parentKey
                );
                $parentKey = null;
            } catch (\Throwable $e) {
                $parentKey = null;
                return ['status' => 'error', 'error' => "送 {$gasTokenName} 失敗: " . $e->getMessage()];
            }
        }

        // 3. 從子地址送 USDT 回主地址
        $childKey = $this->getPrivateKey($childAccount);
        if (!$childKey) {
            return ['status' => 'error', 'error' => '子地址無私鑰'];
        }

        try {
            $chainTx = $adapter->sendTransaction(
                $childAccount->account,
                $parentAccount->account,
                $usdtBalance,
                $childKey
            );
            $childKey = null;

            return [
                'status' => 'success',
                'tx_hash' => $chainTx->txHash,
                'gas_tx_hash' => $gasTxHash,
                'amount' => $usdtBalance,
            ];
        } catch (\Throwable $e) {
            $childKey = null;
            return ['status' => 'error', 'error' => '送 USDT 失敗: ' . $e->getMessage()];
        }
    }

    /**
     * 歸集主地址下所有子地址
     */
    public function consolidateAll(UserChannelAccount $masterAccount): array
    {
        $children = $masterAccount->childAccounts()
            ->whereNull('deleted_at')
            ->get();

        $results = [];
        foreach ($children as $child) {
            $results[$child->id] = $this->consolidate($child);
        }

        return $results;
    }

    /**
     * 從帳號取得解密後的私鑰
     */
    private function getPrivateKey(UserChannelAccount $account): ?string
    {
        $encrypted = data_get($account->detail, UserChannelAccount::DETAIL_KEY_ENCRYPTED_PRIVATE_KEY);
        if (!$encrypted) {
            return null;
        }
        return decrypt($encrypted);
    }

    private function getGasTokenName(string $network): string
    {
        return match ($network) {
            'trc20' => 'TRX',
            'erc20' => 'ETH',
            'bep20' => 'BNB',
            default => 'Native',
        };
    }

    private function getMinNativeBalance(string $network): string
    {
        return match ($network) {
            'trc20' => config('services.trongrid.min_trx_balance', '30'),
            'erc20' => config('services.ethereum.min_native_balance', '0.005'),
            'bep20' => config('services.bsc.min_native_balance', '0.005'),
            default => '0',
        };
    }

    /**
     * 根據鏈網路解析對應的 Adapter
     */
    private function resolveAdapter(string $network): ChainAdapterInterface
    {
        return match ($network) {
            'trc20' => app(Trc20Adapter::class),
            'erc20' => app('evm.adapter.erc20'),
            'bep20' => app('evm.adapter.bep20'),
            default => throw new \InvalidArgumentException("不支援的鏈網路: {$network}"),
        };
    }
}
