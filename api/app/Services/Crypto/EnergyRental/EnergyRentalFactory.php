<?php

namespace App\Services\Crypto\EnergyRental;

class EnergyRentalFactory
{
    public static function make(): EnergyRentalProviderInterface
    {
        $provider = config('services.energy_rental.provider', '');

        $primary = self::resolve($provider);
        if (!$primary->isAvailable()) {
            return new NullProvider();
        }

        // Fallback providers
        $fallbackNames = array_filter(
            explode(',', config('services.energy_rental.fallback', '')),
            fn ($name) => trim($name) !== '' && trim($name) !== $provider
        );

        if (empty($fallbackNames)) {
            return $primary;
        }

        $fallbacks = array_filter(
            array_map(fn ($name) => self::resolve(trim($name)), $fallbackNames),
            fn ($p) => $p->isAvailable()
        );

        if (empty($fallbacks)) {
            return $primary;
        }

        return new FallbackProvider($primary, array_values($fallbacks));
    }

    private static function resolve(string $name): EnergyRentalProviderInterface
    {
        return match ($name) {
            'netts' => new NettsProvider(),
            'tronzap' => new TronZapProvider(),
            default => new NullProvider(),
        };
    }
}
