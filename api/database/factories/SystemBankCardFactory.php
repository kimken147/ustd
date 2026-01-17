<?php

/** @var Factory $factory */

use App\Model\SystemBankCard;
use Faker\Generator as Faker;
use Illuminate\Database\Eloquent\Factory;
use Illuminate\Support\Carbon;

$factory->define(SystemBankCard::class, function (Faker $faker) {
    return [
        'status'                => $faker->randomElement([
            SystemBankCard::STATUS_UNPUBLISHED, SystemBankCard::STATUS_PUBLISHED
        ]),
        'balance'               => $faker->randomFloat(2, 0, 100000),
        'bank_card_holder_name' => $faker->name,
        'bank_card_number'      => $faker->creditCardNumber,
        'bank_name'             => $faker->word,
        'published_at'          => $faker->randomElement([
            Carbon::make($faker->dateTimeThisYear('now', config('app.timezone'))),
            null,
        ]),
        'last_matched_at'       => $faker->randomElement([
            Carbon::make($faker->dateTimeThisYear('now', config('app.timezone'))),
            null,
        ]),
    ];
});

$factory->state(SystemBankCard::class, 'published', function (Faker $faker) {
    return [
        'status'       => SystemBankCard::STATUS_PUBLISHED,
        'balance'      => 50000,
        'published_at' => Carbon::make($faker->dateTimeThisYear('now', config('app.timezone'))),
    ];
});
