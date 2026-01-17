<?php

/** @var Factory $factory */

use App\Model\Channel;
use App\Model\ChannelGroup;
use Faker\Generator as Faker;
use Illuminate\Database\Eloquent\Factory;

$factory->define(ChannelGroup::class, function (Faker $faker) {
    return [
        'channel_code' => factory(Channel::class),
        'fixed_amount' => $faker->boolean,
    ];
});
