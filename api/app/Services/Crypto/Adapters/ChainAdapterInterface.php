<?php

namespace App\Services\Crypto\Adapters;

use App\Services\Crypto\DTO\ChainTransaction;
use Illuminate\Support\Collection;

interface ChainAdapterInterface
{
    /**
     * 取得指定地址最近的 USDT 轉入交易
     *
     * @return Collection<ChainTransaction>
     */
    public function fetchIncomingTransactions(string $address, ?string $sinceTimestamp = null): Collection;
}
