<?php

/** @var Factory $factory */

use App\Model\BankCard;
use App\Model\User;
use Faker\Generator as Faker;
use Illuminate\Database\Eloquent\Factory;

$factory->define(BankCard::class, function (Faker $faker) {
    return [
        'user_id'               => $faker->randomElement([0, factory(User::class)]),
        'status'                => $faker->randomElement([
            BankCard::STATUS_REVIEWING, BankCard::STATUS_REVIEW_PASSED, BankCard::STATUS_REVIEW_REJECTED
        ]),
        'bank_card_holder_name' => $faker->name,
        'bank_card_number'      => $faker->creditCardNumber,
        'bank_name'             => $faker->word,
    ];
});

$factory->state(BankCard::class, 'for-users', function (Faker $faker) {
    return [
        'user_id' => factory(User::class)->state($faker->randomElement(['provider', 'merchant'])),
    ];
});
