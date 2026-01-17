<?php

/** @var Factory $factory */

use App\Model\ChannelAmount;
use App\Model\Device;
use App\Model\User;
use App\Model\UserChannelAccount;
use App\Model\Wallet;
use Faker\Generator as Faker;
use Illuminate\Database\Eloquent\Factory;

$factory->define(UserChannelAccount::class, function (Faker $faker) {
    /** @var ChannelAmount $channelAmount */
    $channelAmount = factory(ChannelAmount::class)->create();

    $detail = [
        1 => [
            UserChannelAccount::DETAIL_KEY_QR_CODE_FILE_PATH => 'http://paufen-api.test/qr-code',
            UserChannelAccount::DETAIL_KEY_REDIRECT_URL      => 'http://paufen-api.test/wap'
        ],
        [UserChannelAccount::DETAIL_KEY_REDIRECT_URL => 'http://paufen-api.test/wap'],
        [
            UserChannelAccount::DETAIL_KEY_BANK_CARD_HOLDER_NAME => $faker->name,
            UserChannelAccount::DETAIL_KEY_BANK_CARD_NUMBER      => $faker->creditCardNumber,
            UserChannelAccount::DETAIL_KEY_BANK_NAME             => $faker->name,
        ],
    ][$channelAmount->channel->present_result];

    $account = [
        1 => $faker->unique()->creditCardNumber,
        $faker->unique()->creditCardNumber,
        data_get($detail, UserChannelAccount::DETAIL_KEY_BANK_CARD_NUMBER),
    ][$channelAmount->channel->present_result];

    return [
        'user_id'           => $user = factory(User::class),
        'channel_amount_id' => $channelAmount,
        'device_id'         => factory(Device::class)->create([
            'user_id' => $user,
        ]),
        'wallet_id'         => factory(Wallet::class)->create(['user_id' => $user]),
        'max_amount'        => sprintf('%.2f', $maxAmount = $faker->randomFloat(2, 1, 1000)),
        'min_amount'        => sprintf('%.2f', $faker->randomFloat(2, 1, $maxAmount)),
        'fee_percent'       => sprintf('%.2f', $faker->randomFloat(2, 1.7, 2.0)),
        'status'            => UserChannelAccount::STATUS_DISABLE,
        'account'           => $account,
        'detail'            => $detail,
    ];
});
