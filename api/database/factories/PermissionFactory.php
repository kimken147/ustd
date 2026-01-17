<?php

/** @var Factory $factory */

use App\Model\Permission;
use App\Model\User;
use Faker\Generator as Faker;
use Illuminate\Database\Eloquent\Factory;

$factory->define(Permission::class, function (Faker $faker) {
    return [
        'role'       => $faker->randomElement([User::ROLE_ADMIN, User::ROLE_PROVIDER, User::ROLE_MERCHANT]),
        'group_name' => $faker->name,
        'name'       => $faker->name,
    ];
});
