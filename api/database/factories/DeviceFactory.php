<?php

/** @var Factory $factory */

use App\Model\Device;
use App\Model\User;
use Faker\Generator as Faker;
use Illuminate\Database\Eloquent\Factory;
use Illuminate\Support\Carbon;

$factory->define(Device::class, function (Faker $faker) {
    return [
        'user_id'                => factory(User::class)->states('provider'),
        'regular_customer_first' => $faker->boolean,
        'name'                   => $faker->name,
        'last_login_at'          => $faker->randomElement([
            Carbon::make($faker->dateTimeThisYear('now', config('app.timezone'))), null,
        ]),
        'last_heartbeat_at'      => $faker->randomElement([
            Carbon::make($faker->dateTimeThisYear('now', config('app.timezone'))), null,
        ]),
        'last_login_ipv4'        => $faker->randomElement([
            $faker->ipv4, null,
        ]),
    ];
});
