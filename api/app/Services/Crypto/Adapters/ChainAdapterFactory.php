<?php

namespace App\Services\Crypto\Adapters;

class ChainAdapterFactory
{
    /**
     * 根據鏈網路取得對應 Adapter，找不到時回傳 null。
     */
    public static function make(string $chainNetwork): ?ChainAdapterInterface
    {
        return match ($chainNetwork) {
            'trc20' => app(Trc20Adapter::class),
            'erc20' => app('evm.adapter.erc20'),
            'bep20' => app('evm.adapter.bep20'),
            default => null,
        };
    }

    /**
     * 同 make()，但找不到時拋出例外。
     */
    public static function makeOrFail(string $chainNetwork): ChainAdapterInterface
    {
        return self::make($chainNetwork)
            ?? throw new \InvalidArgumentException("不支援的鏈網路: {$chainNetwork}");
    }
}
