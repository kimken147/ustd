<?php

/** @var Factory $factory */

use App\Model\User;
use App\Model\Wallet;
use Faker\Generator as Faker;
use Illuminate\Database\Eloquent\Factory;

$factory->define(Wallet::class, function (Faker $faker) {
    return [
        'user_id'        => factory(User::class)->create(),
        'status'         => $faker->randomElement([Wallet::STATUS_DISABLE, Wallet::STATUS_ENABLE]),
        'balance'        => $faker->randomFloat(2, 0, 1000),
        'frozen_balance' => $faker->randomFloat(2, 0, 1000),
        'withdraw_fee'   => sprintf('%.2f', $faker->randomFloat(2, 1, 10)),
    ];
});
