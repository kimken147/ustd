<?php

/** @var Factory $factory */

use App\Models\Transaction;
use App\Models\TransactionFee;
use App\Models\User;
use Faker\Generator as Faker;
use Illuminate\Database\Eloquent\Factory;

$factory->define(TransactionFee::class, function (Faker $faker) {
    return [
        'transaction_id' => factory(Transaction::class),
        'user_id'        => factory(User::class),
        'profit'         => sprintf('%.2f', $faker->randomFloat(2, 1, 10)),
        'actual_profit'  => sprintf('%.2f', $faker->randomFloat(2, 1, 10)),
        'fee'            => sprintf('%.2f', $faker->randomFloat(2, 1, 10)),
        'actual_fee'     => sprintf('%.2f', $faker->randomFloat(2, 1, 10)),
    ];
});
