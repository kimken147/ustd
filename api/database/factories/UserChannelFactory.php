<?php

/** @var Factory $factory */

use App\Model\Channel;
use App\Model\ChannelGroup;
use App\Model\User;
use App\Model\UserChannel;
use Faker\Generator as Faker;
use Illuminate\Database\Eloquent\Factory;

$factory->define(UserChannel::class, function (Faker $faker) {
    return [
        'user_id'          => factory(User::class)->create(),
        'channel_group_id' => factory(ChannelGroup::class),
        'status'           => $faker->randomElement([
            Channel::STATUS_DISABLE, Channel::STATUS_ENABLE
        ]),
        'max_amount'       => sprintf('%.2f', $maxAmount = $faker->randomFloat(2, 1, 1000)),
        'min_amount'       => sprintf('%.2f', $faker->randomFloat(2, 1, $maxAmount)),
        'fee_percent'      => sprintf('%.2f', $faker->randomFloat(2, 1.7, 2.0)),
        'floating_enable'  => $faker->boolean,
    ];
});
