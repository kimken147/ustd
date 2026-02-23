<?php

namespace App\Services\Crypto\Adapters;

use App\Services\Crypto\DTO\ChainTransaction;
use App\Services\Crypto\Exceptions\InsufficientBalanceException;
use App\Services\Crypto\Exceptions\TransactionBroadcastException;
use Illuminate\Support\Collection;

interface ChainAdapterInterface
{
    /**
     * 取得指定地址最近的 USDT 轉入交易
     *
     * @return Collection<ChainTransaction>
     */
    public function fetchIncomingTransactions(string $address, ?string $sinceTimestamp = null): Collection;

    /**
     * 發送 USDT 到指定地址
     *
     * @throws InsufficientBalanceException
     * @throws TransactionBroadcastException
     */
    public function sendTransaction(
        string $fromAddress,
        string $toAddress,
        string $amount,
        string $privateKey
    ): ChainTransaction;
}
