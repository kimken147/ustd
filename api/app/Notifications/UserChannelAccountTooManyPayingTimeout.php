<?php

namespace App\Notifications;

use App\Model\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class UserChannelAccountTooManyPayingTimeout extends Notification implements ShouldQueue
{

    use Queueable;

    /**
     * @var Transaction
     */
    public $transaction;

    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    public function toTelegram($notifiable)
    {
        return TelegramMessage::create()
            ->options(['parse_mode' => ''])
            ->content(
                implode(
                    PHP_EOL,
                    [
                        '收款号支付超时警示'.PHP_EOL,
                        '收款帐号编号：'.$this->transaction->from_channel_account_hash_id,
                        '码商：'.$this->transaction->from->username,
                    ]
                )
            );
    }

    public function via($notifiable)
    {
        return [TelegramChannel::class];
    }
}
