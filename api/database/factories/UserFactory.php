<?php

/** @var Factory $factory */

use App\Models\User;
use Faker\Generator as Faker;
use Illuminate\Database\Eloquent\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| This directory should contain each of the model factory definitions for
| your application. Factories provide a convenient way to generate new
| model instances for testing / seeding your application's database.
|
*/

$factory->define(User::class, function (Faker $faker) {
    return [
        'last_login_ipv4'    => $faker->ipv4,
        'role'               => $faker->randomElement([User::ROLE_ADMIN, User::ROLE_PROVIDER, User::ROLE_MERCHANT]),
        'status'             => User::STATUS_ENABLE,
        'agent_enable'       => $faker->boolean,
        'google2fa_enable'   => $faker->boolean,
        'deposit_enable'     => $faker->boolean,
        'withdraw_enable'    => $faker->boolean,
        'transaction_enable' => $faker->boolean,
        'account_mode'       => User::ACCOUNT_MODE_GENERAL,
        'password'           => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
        'name'               => Str::substr($faker->name, 0, 20),
        'username'           => join('', $faker->randomElements(range('A', 'Z'), 10, true)),
        'google2fa_secret'   => implode('', $faker->unique()->randomElements(range('A', 'Z'), 16, true)),
        'secret_key'         => Str::random(32),
        'last_login_at'      => Carbon::make($faker->dateTimeThisYear('now',
            config('app.timezone'))),
        'phone'              => $faker->phoneNumber,
        'contact'            => $faker->words(3, true),
    ];
});

$factory->state(User::class, 'admin', [
    'role' => User::ROLE_ADMIN,
]);

$factory->state(User::class, 'provider', [
    'role' => User::ROLE_PROVIDER,
]);

$factory->state(User::class, 'merchant', [
    'role' => User::ROLE_MERCHANT,
]);

$factory->state(User::class, 'agent_enabled', [
    'agent_enable' => true,
]);

$factory->state(User::class, 'agent_disabled', [
    'agent_enable' => false,
]);

$factory->state(User::class, 'google2fa_enabled', [
    'google2fa_enable' => true,
]);

$factory->state(User::class, 'google2fa_disabled', [
    'google2fa_enable' => false,
]);

$factory->state(User::class, 'enabled', [
    'status' => User::STATUS_ENABLE,
]);

$factory->state(User::class, 'credit_mode_enabled', [
    'account_mode' => User::ACCOUNT_MODE_CREDIT,
]);

$factory->state(User::class, 'general_mode_enabled', [
    'account_mode' => User::ACCOUNT_MODE_GENERAL,
]);

$factory->state(User::class, 'deposit_mode_enabled', [
    'account_mode' => User::ACCOUNT_MODE_DEPOSIT,
]);

$factory->state(User::class, 'sub-account', function (Faker $faker) {
    return [
        'last_login_ipv4'    => $faker->ipv4,
        'role'               => User::ROLE_SUB_ACCOUNT,
        'status'             => $faker->randomElement([User::STATUS_DISABLE, User::STATUS_ENABLE]),
        'agent_enable'       => false,
        'google2fa_enable'   => true,
        'deposit_enable'     => false,
        'withdraw_enable'    => false,
        'transaction_enable' => false,
        'password'           => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
        'name'               => Str::substr($faker->name, 0, 20),
        'username'           => join('', $this->faker->randomElements(range('A', 'Z'), 10, true)),
        'google2fa_secret'   => implode('', $faker->unique()->randomElements(range('A', 'Z'), 16, true)),
        'secret_key'         => Str::random(32),
        'last_login_at'      => Carbon::make($faker->dateTimeThisYear('now',
            config('app.timezone'))),
    ];
});
