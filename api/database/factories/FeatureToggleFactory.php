<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Model\FeatureToggle;
use Faker\Generator as Faker;

$factory->define(FeatureToggle::class, function (Faker $faker) {
    return [];
});

$factory->state(FeatureToggle::class, FeatureToggle::INPUT_TYPE_TEXT, function (Faker $faker) {
    return [
        'enabled' => $faker->boolean,
        'input' => [
            'type' => 'text',
            'value' => $faker->numberBetween(0, 10000),
        ],
    ];
});
