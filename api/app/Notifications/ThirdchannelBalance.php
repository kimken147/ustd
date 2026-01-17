<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;
use Illuminate\Support\Facades\Log;

class ThirdchannelBalance extends Notification implements ShouldQueue
{

    use Queueable;

    /**
     * @var string
     */
    public $name;
    /**
     * @var string
     */
    public $merchant;
    /**
     * @var float
     */
    public $balance;

    public function __construct(string $name, string $merchant, float $balance)
    {
        $this->name = $name;
        $this->merchant = $merchant;
        $this->balance = $balance;
    }

    public function toTelegram($notifiable)
    {
        $batch = rand();

        Log::debug(__METHOD__ . ' Fired', [$batch]);
        return TelegramMessage::create()
            ->options(['parse_mode' => ''])
            ->content(
                implode(
                    PHP_EOL,
                    [
                        '*三方余额不足警示*'.PHP_EOL,
                        '名称：'.$this->name,
                        '商戶号：'.$this->merchant,
                        '可用余额：'.$this->balance,
                    ]
                )
            );
        Log::debug(__METHOD__ . ' End', [$batch]);
    }

    public function via($notifiable)
    {
        return [TelegramChannel::class];
    }
}
