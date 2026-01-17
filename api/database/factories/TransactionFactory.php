<?php

/** @var Factory $factory */

use App\Models\Channel;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserChannelAccount;
use App\Models\Wallet;
use Faker\Generator as Faker;
use Illuminate\Database\Eloquent\Factory;
use Illuminate\Support\Carbon;

$factory->define(Transaction::class, function (Faker $faker) {
    $channel = factory(Channel::class)->create();

    $type = $faker->randomElement([
        Transaction::TYPE_PAUFEN_TRANSACTION, Transaction::TYPE_PAUFEN_WITHDRAW,
        Transaction::TYPE_NORMAL_DEPOSIT, Transaction::TYPE_NORMAL_WITHDRAW,
    ]);

    $shouldHaveCertificateFilePath = in_array($type,
        [Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_DEPOSIT]);

    $status = $faker->randomElement([
        Transaction::STATUS_PENDING_REVIEW, Transaction::STATUS_MATCHING, Transaction::STATUS_PAYING,
        Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS, Transaction::STATUS_MATCHING_TIMED_OUT,
        Transaction::STATUS_PAYING_TIMED_OUT, Transaction::STATUS_FAILED,
    ]);

    $notifyStatus = $faker->randomElement([
        Transaction::NOTIFY_STATUS_NONE, Transaction::NOTIFY_STATUS_PENDING,
        Transaction::NOTIFY_STATUS_SENDING, Transaction::NOTIFY_STATUS_SUCCESS,
        Transaction::NOTIFY_STATUS_FAILED,
    ]);

    $amount = sprintf('%.2f', $faker->randomFloat(2, 1, 50000));

    $statusInSuccess = in_array($status, [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS]);
    $statusNotInMatched = in_array(
        $status, [Transaction::STATUS_PENDING_REVIEW, Transaction::STATUS_MATCHING, Transaction::STATUS_MATCHING_TIMED_OUT]
    );

    $notifyStatusNone = $notifyStatus === Transaction::NOTIFY_STATUS_NONE;

    $now = Carbon::make($faker->dateTimeThisYear('now', config('app.timezone')));

    return [
        'from_id'               => ($from = factory(User::class)->create())->getKey(),
        'from_account_mode'     => $from->account_mode,
        'from_wallet_id'        => factory(Wallet::class)->create(['user_id' => $from->getKey()])->getKey(),
        'to_id'                 => ($to = factory(User::class)->create())->getKey(),
        'to_account_mode'       => $to->account_mode,
        'to_wallet_id'          => factory(Wallet::class)->create(['user_id' => $to->getKey()])->getKey(),
        'type'                  => $type,
        'status'                => $status,
        'notify_status'         => $notifyStatus,
        'from_channel_account'  => factory(UserChannelAccount::class)->create()->detail,
        'to_channel_account'    => factory(UserChannelAccount::class)->create()->detail,
        'amount'                => $amount,
        'floating_amount'       => sprintf(
            '%.2f',
            bcadd(
                $amount,
                $channel->floating_enable * $channel->floating,
                2
            )
        ),
        'actual_amount'         => sprintf('%.2f', $statusInSuccess ? $amount : 0),
        'channel_code'          => in_array($type,
            [Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_DEPOSIT]) ? null : $channel->code,
        'order_number'          => $faker->uuid,
        'note'                  => $faker->words(3, true),
        'notify_url'            => $notifyStatusNone ? null : $faker->url,
        'certificate_file_path' => $shouldHaveCertificateFilePath ? $faker->uuid.'.'.$faker->fileExtension : null,
        'notified_at'           => $notifyStatusNone ? null : $now,
        'matched_at'            => $statusNotInMatched ? null : Carbon::make($faker->dateTimeThisYear('now',
            config('app.timezone'))),
        'confirmed_at'          => $statusInSuccess ? $now : null,
        'created_at'            => $now,
        'updated_at'            => $now,
    ];
});

$factory->state(Transaction::class, Transaction::TYPE_PAUFEN_TRANSACTION, function (Faker $faker) {
    $channel = factory(Channel::class)->create();

    $status = $faker->randomElement([
        Transaction::STATUS_PENDING_REVIEW, Transaction::STATUS_MATCHING, Transaction::STATUS_PAYING,
        Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS, Transaction::STATUS_MATCHING_TIMED_OUT,
        Transaction::STATUS_PAYING_TIMED_OUT, Transaction::STATUS_FAILED,
    ]);

    $notifyStatus = $faker->randomElement([
        Transaction::NOTIFY_STATUS_NONE, Transaction::NOTIFY_STATUS_PENDING,
        Transaction::NOTIFY_STATUS_SENDING, Transaction::NOTIFY_STATUS_SUCCESS,
        Transaction::NOTIFY_STATUS_FAILED,
    ]);

    $amount = sprintf('%.2f', $faker->randomFloat(2, 1, 50000));

    $statusInSuccess = in_array($status, [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS]);
    $statusNotInMatched = in_array(
        $status, [Transaction::STATUS_PENDING_REVIEW, Transaction::STATUS_MATCHING, Transaction::STATUS_MATCHING_TIMED_OUT]
    );

    $notifyStatusNone = $notifyStatus === Transaction::NOTIFY_STATUS_NONE;

    $now = Carbon::make($faker->dateTimeThisYear('now', config('app.timezone')));

    return [
        'from_id'               => factory(User::class),
        'to_id'                 => factory(User::class),
        'type'                  => Transaction::TYPE_PAUFEN_TRANSACTION,
        'status'                => $status,
        'notify_status'         => $notifyStatus,
        'from_channel_account'  => factory(UserChannelAccount::class)->create()->detail,
        'to_channel_account'    => factory(UserChannelAccount::class)->create()->detail,
        'amount'                => $amount,
        'floating_amount'       => sprintf(
            '%.2f',
            bcadd(
                $amount,
                $channel->floating_enable * $channel->floating,
                2
            )
        ),
        'actual_amount'         => sprintf('%.2f', $statusInSuccess ? $amount : 0),
        'channel_code'          => $channel->code,
        'order_number'          => $faker->uuid,
        'note'                  => $faker->words(3, true),
        'notify_url'            => $notifyStatusNone ? null : $faker->url,
        'from_device_name'      => $statusNotInMatched ? null : $faker->name,
        'certificate_file_path' => null,
        'notified_at'           => $notifyStatusNone ? null : $now,
        'matched_at'            => $statusNotInMatched ? null : Carbon::make($faker->dateTimeThisYear('now',
            config('app.timezone'))),
        'confirmed_at'          => $statusInSuccess ? $now : null,
        'created_at'            => $now,
        'updated_at'            => $now,
    ];
});

$factory->state(Transaction::class, Transaction::TYPE_NORMAL_WITHDRAW, function (Faker $faker) {
    $status = $faker->randomElement([
        Transaction::STATUS_PENDING_REVIEW, Transaction::STATUS_MATCHING, Transaction::STATUS_PAYING,
        Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS, Transaction::STATUS_MATCHING_TIMED_OUT,
        Transaction::STATUS_PAYING_TIMED_OUT, Transaction::STATUS_FAILED,
    ]);

    $notifyStatus = $faker->randomElement([
        Transaction::NOTIFY_STATUS_NONE, Transaction::NOTIFY_STATUS_PENDING,
        Transaction::NOTIFY_STATUS_SENDING, Transaction::NOTIFY_STATUS_SUCCESS,
        Transaction::NOTIFY_STATUS_FAILED,
    ]);

    $amount = sprintf('%.2f', $faker->randomFloat(2, 1, 50000));

    $statusInSuccess = in_array($status, [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS]);
    $statusNotInMatched = in_array(
        $status, [Transaction::STATUS_PENDING_REVIEW, Transaction::STATUS_MATCHING, Transaction::STATUS_MATCHING_TIMED_OUT]
    );

    $notifyStatusNone = $notifyStatus === Transaction::NOTIFY_STATUS_NONE;

    $now = Carbon::make($faker->dateTimeThisYear('now', config('app.timezone')));

    return [
        'from_id'              => factory(User::class)->state($faker->randomElement(['provider', 'merchant'])),
        'type'                 => Transaction::TYPE_NORMAL_WITHDRAW,
        'status'               => $status,
        'notify_status'        => $notifyStatus,
        'from_channel_account' => [
            UserChannelAccount::DETAIL_KEY_BANK_CARD_HOLDER_NAME => $faker->name,
            UserChannelAccount::DETAIL_KEY_BANK_CARD_NUMBER      => $faker->creditCardNumber,
            UserChannelAccount::DETAIL_KEY_BANK_NAME             => $this->faker->randomElement([
                '中国银行', '工商银行', '建设银行', '农业银行', '招商银行', '交通银行',
            ]),
        ],
        'amount'               => $amount,
        'floating_amount'      => $amount,
        'actual_amount'        => sprintf('%.2f', $statusInSuccess ? $amount : 0),
        'order_number'         => $faker->uuid,
        'note'                 => $faker->words(3, true),
        'notify_url'           => $notifyStatusNone ? null : $faker->url,
        'notified_at'          => $notifyStatusNone ? null : $now,
        'matched_at'           => $statusNotInMatched ? null : Carbon::make($faker->dateTimeThisYear('now',
            config('app.timezone'))),
        'confirmed_at'         => $statusInSuccess ? $now : null,
        'created_at'           => $now,
        'updated_at'           => $now,
    ];
});

$factory->state(Transaction::class, Transaction::TYPE_PAUFEN_WITHDRAW, function (Faker $faker) {
    $status = $faker->randomElement([
        Transaction::STATUS_PENDING_REVIEW, Transaction::STATUS_MATCHING, Transaction::STATUS_PAYING,
        Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS, Transaction::STATUS_MATCHING_TIMED_OUT,
        Transaction::STATUS_PAYING_TIMED_OUT, Transaction::STATUS_FAILED,
    ]);

    $notifyStatus = $faker->randomElement([
        Transaction::NOTIFY_STATUS_NONE, Transaction::NOTIFY_STATUS_PENDING,
        Transaction::NOTIFY_STATUS_SENDING, Transaction::NOTIFY_STATUS_SUCCESS,
        Transaction::NOTIFY_STATUS_FAILED,
    ]);

    $amount = sprintf('%.2f', $faker->randomFloat(2, 1, 50000));

    $statusInSuccess = in_array($status, [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS]);
    $statusNotInMatched = in_array(
        $status, [Transaction::STATUS_PENDING_REVIEW, Transaction::STATUS_MATCHING, Transaction::STATUS_MATCHING_TIMED_OUT]
    );

    $notifyStatusNone = $notifyStatus === Transaction::NOTIFY_STATUS_NONE;

    $now = Carbon::make($faker->dateTimeThisYear('now', config('app.timezone')));

    return [
        'from_id'               => factory(User::class)->state('merchant'),
        'to_id'                 => factory(User::class)->state('provider'),
        'type'                  => Transaction::TYPE_PAUFEN_WITHDRAW,
        'status'                => $status,
        'notify_status'         => $notifyStatus,
        'from_channel_account'  => [
            UserChannelAccount::DETAIL_KEY_BANK_CARD_HOLDER_NAME => $faker->name,
            UserChannelAccount::DETAIL_KEY_BANK_CARD_NUMBER      => $faker->creditCardNumber,
            UserChannelAccount::DETAIL_KEY_BANK_NAME             => $this->faker->randomElement([
                '中国银行', '工商银行', '建设银行', '农业银行', '招商银行', '交通银行',
            ]),
        ],
        'amount'                => $amount,
        'floating_amount'       => $amount,
        'actual_amount'         => sprintf('%.2f', $statusInSuccess ? $amount : 0),
        'order_number'          => $faker->uuid,
        'note'                  => $faker->words(3, true),
        'notify_url'            => $notifyStatusNone ? null : $faker->url,
        'certificate_file_path' => $faker->uuid.'.'.$faker->fileExtension,
        'notified_at'           => $notifyStatusNone ? null : $now,
        'matched_at'            => $statusNotInMatched ? null : Carbon::make($faker->dateTimeThisYear('now',
            config('app.timezone'))),
        'confirmed_at'          => $statusInSuccess ? $now : null,
        'created_at'            => $now,
        'updated_at'            => $now,
    ];
});
