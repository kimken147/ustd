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

    /**
     * Get native token balance (e.g. TRX) in human-readable format.
     */
    public function getNativeBalance(string $address): string;

    /**
     * Get USDT token balance (6 decimals, human-readable).
     */
    public function getTokenBalance(string $address): string;

    /**
     * Check transaction status on-chain.
     * Returns ['confirmed' => bool, 'success' => bool, 'fee' => string] or null if not found yet.
     */
    public function getTransactionInfo(string $txHash): ?array;
}
