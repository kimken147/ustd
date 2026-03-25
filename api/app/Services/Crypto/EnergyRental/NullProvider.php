<?php

namespace App\Services\Crypto\EnergyRental;

class NullProvider implements EnergyRentalProviderInterface
{
    public function delegateEnergy(string $receiveAddress, int $amount): array
    {
        return ['order_id' => '', 'paid_trx' => '0', 'success' => false, 'hash' => null];
    }

    public function checkOrder(string $orderId): ?array
    {
        return null;
    }

    public function getBalance(): string
    {
        return '0';
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'null';
    }
}
