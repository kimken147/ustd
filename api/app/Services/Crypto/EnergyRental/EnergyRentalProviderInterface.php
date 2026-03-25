<?php

namespace App\Services\Crypto\EnergyRental;

interface EnergyRentalProviderInterface
{
    /**
     * 委託能量到指定地址
     *
     * @param string $receiveAddress 接收能量的地址（出款地址）
     * @param int $amount 能量數量（65000 或 131000）
     * @return array{order_id: string, paid_trx: string, success: bool, hash: ?string}
     *
     * @throws \RuntimeException 委託失敗時拋出
     */
    public function delegateEnergy(string $receiveAddress, int $amount): array;

    /**
     * 查詢訂單狀態
     *
     * @return array{success: bool, energy: int, cost: string}|null
     */
    public function checkOrder(string $orderId): ?array;

    /**
     * 查詢帳戶餘額（TRX）
     */
    public function getBalance(): string;

    /**
     * 是否可用（已設定 API key 且服務正常）
     */
    public function isAvailable(): bool;

    /**
     * Provider 名稱（用於日誌）
     */
    public function name(): string;
}
