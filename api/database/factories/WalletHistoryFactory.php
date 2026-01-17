<?php

/** @var Factory $factory */

use App\Models\User;
use App\Models\WalletHistory;
use Faker\Generator as Faker;
use Illuminate\Database\Eloquent\Factory;

$factory->define(WalletHistory::class, function (Faker $faker) {
    return [
        'user_id'     => factory(User::class),
        'operator_id' => $faker->randomElement([
            factory(User::class),
            0, // 系統
        ]),
        'type'        => $faker->randomElement([
            WalletHistory::TYPE_SYSTEM_ADJUSTING, WalletHistory::TYPE_TRANSFER,
            WalletHistory::TYPE_DEPOSIT, WalletHistory::TYPE_WITHHOLD,
            WalletHistory::TYPE_WITHHOLD_ROLLBACK,
        ]),
        'delta'       => $faker->randomElement([
            // 第一種狀況，兩個額度同時被調整
            [
                'balance'        => sprintf('%.2f', $faker->randomFloat(2, -100)),
                'frozen_balance' => sprintf('%.2f', $faker->randomFloat(2, -100)),
            ],
            // 第二種狀況
            [
                'balance' => sprintf('%.2f', $faker->randomFloat(2, -100)),
            ],
            // 第三種狀況
            [
                'frozen_balance' => sprintf('%.2f', $faker->randomFloat(2, -100)),
            ],
        ]),
        'result'      => [
            'balance'        => sprintf('%.2f', $faker->randomFloat(2)),
            'frozen_balance' => sprintf('%.2f', $faker->randomFloat(2)),
        ],
        'note'        => $faker->words(3, true),
    ];
});
