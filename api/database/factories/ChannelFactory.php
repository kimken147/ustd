<?php

/** @var Factory $factory */

use App\Models\Channel;
use App\Models\Channel as ChannelModel;
use Faker\Generator as Faker;
use Illuminate\Database\Eloquent\Factory;
use Illuminate\Support\Str;

$factory->define(ChannelModel::class, function (Faker $faker) {
    return [
        'code'                       => implode('', $faker->randomElements(range('A', 'Z'), 20, true)),
        'name'                       => Str::substr($faker->name, 0, 20),
        'status'                     => $faker->randomElement([
            Channel::STATUS_DISABLE, Channel::STATUS_ENABLE,
        ]),
        'order_timeout'              => $faker->numberBetween(0, 30),
        'order_timeout_enable'       => $faker->boolean,
        'transaction_timeout'        => $faker->numberBetween(0, 30),
        'transaction_timeout_enable' => $faker->boolean,
        'floating'                   => $faker->numberBetween(-1, 1),
        'floating_enable'            => $faker->boolean,
        'present_result'             => $faker->randomElement([
            Channel::RESPONSE_QRCODE, Channel::RESPONSE_URL, Channel::RESPONSE_BANK_CARD,
        ]),
    ];
});
