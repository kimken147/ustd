<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;
use Stevebauman\Location\Facades\Location;
use Stevebauman\Location\Position;
use Illuminate\Support\Str;

class GcashAccount extends Notification implements ShouldQueue
{

    use Queueable;

    private $account;
    private $message;

    public function __construct($account, $message)
    {
        $this->account = $account;
        $this->message = $message;
    }

    public function toTelegram()
    {
        if (!empty($this->message)) {
            return TelegramMessage::create()
                ->options(['parse_mode' => ''])
                ->content(
                    implode(
                        PHP_EOL,
                        [
                            '*GCash 卡警示*'.PHP_EOL,
                            '卡號：'.$this->account,
                            '問題：'.$this->message,
                        ]
                    )
                );
        }

    }

    public function via($notifiable)
    {
        return [TelegramChannel::class];
    }
}
