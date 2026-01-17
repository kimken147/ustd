<?php

/** @var Factory $factory */

use App\Models\Channel;
use App\Models\ChannelAmount;
use App\Models\ChannelGroup;
use Faker\Generator as Faker;
use Illuminate\Database\Eloquent\Factory;

$factory->define(ChannelAmount::class, function (Faker $faker) {
    return [
        'channel_group_id' => factory(ChannelGroup::class),
        'channel_code'     => factory(Channel::class),
        'max_amount'       => sprintf('%.2f', $maxAmount = $faker->randomFloat(2, 1, 1000)),
        'min_amount'       => sprintf('%.2f', $faker->randomFloat(2, 1, $maxAmount)),
    ];
});
