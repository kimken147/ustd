<?php

namespace App\Notifications;

use App\Model\User;
use App\Utils\AmountDisplayTransformer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class LoginThrottle extends Notification implements ShouldQueue
{

    use Queueable;

    /**
     * @var string
     */
    public $ipv4;
    /**
     * @var string
     */
    public $tryingUsername;

    public function __construct(string $tryingUsername, string $ipv4)
    {
        $this->tryingUsername = $tryingUsername;
        $this->ipv4 = $ipv4;
    }

    public function toTelegram($notifiable)
    {
        return TelegramMessage::create()
            ->options(['parse_mode' => ''])
            ->content(
                implode(
                    PHP_EOL,
                    [
                        '*登入错误次数过多暂时锁定*'.PHP_EOL,
                        '尝试帐号：'.$this->tryingUsername,
                        'IP：'.$this->ipv4,
                    ]
                )
            );
    }

    public function via($notifiable)
    {
        return [TelegramChannel::class];
    }
}
