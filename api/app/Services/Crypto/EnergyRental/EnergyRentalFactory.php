<?php

namespace App\Services\Crypto\EnergyRental;

class EnergyRentalFactory
{
    public static function make(): EnergyRentalProviderInterface
    {
        $provider = config('services.energy_rental.provider', '');

        return match ($provider) {
            'netts' => new NettsProvider(),
            default => new NullProvider(),
        };
    }
}
