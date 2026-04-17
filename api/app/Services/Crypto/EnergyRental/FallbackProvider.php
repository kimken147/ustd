<?php

namespace App\Services\Crypto\EnergyRental;

use Illuminate\Support\Facades\Log;

class FallbackProvider implements EnergyRentalProviderInterface
{
    /** @param EnergyRentalProviderInterface[] $fallbacks */
    public function __construct(
        private readonly EnergyRentalProviderInterface $primary,
        private readonly array $fallbacks,
    ) {}

    public function delegateEnergy(string $receiveAddress, int $amount): array
    {
        // 先嘗試主要供應商
        try {
            return $this->primary->delegateEnergy($receiveAddress, $amount);
        } catch (\Throwable $e) {
            Log::warning("EnergyFallback: {$this->primary->name()} 失敗，嘗試備用供應商", [
                'primary' => $this->primary->name(),
                'error'   => $e->getMessage(),
            ]);
        }

        // 依序嘗試備用供應商
        foreach ($this->fallbacks as $fallback) {
            try {
                $result = $fallback->delegateEnergy($receiveAddress, $amount);
                Log::info("EnergyFallback: 使用 {$fallback->name()} 成功", [
                    'fallback' => $fallback->name(),
                    'address'  => $receiveAddress,
                ]);
                return $result;
            } catch (\Throwable $e) {
                Log::warning("EnergyFallback: {$fallback->name()} 也失敗", [
                    'fallback' => $fallback->name(),
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        // 全部失敗
        throw new \RuntimeException(
            "Energy delegation failed: all providers exhausted ({$this->primary->name()}, "
            . implode(', ', array_map(fn ($f) => $f->name(), $this->fallbacks)) . ")"
        );
    }

    public function checkOrder(string $orderId): ?array
    {
        return $this->primary->checkOrder($orderId);
    }

    public function getBalance(): string
    {
        return $this->primary->getBalance();
    }

    public function isAvailable(): bool
    {
        return $this->primary->isAvailable();
    }

    public function name(): string
    {
        return $this->primary->name() . '+fallback';
    }
}
