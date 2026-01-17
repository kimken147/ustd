<?php

namespace App\Notifications;

use App\Model\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;
use Stevebauman\Location\Facades\Location;
use Stevebauman\Location\Position;

class AdminLogin extends Notification implements ShouldQueue
{

    use Queueable;

    /**
     * @var User
     */
    public $admin;
    /**
     * @var string
     */
    public $ipv4;

    public function __construct(User $admin, string $ipv4)
    {
        $this->admin = $admin;
        $this->ipv4 = $ipv4;
    }

    public function toTelegram($notifiable)
    {
        /** @var Position $location */
        $location = optional(Location::get($this->ipv4));

        return TelegramMessage::create()
            ->options(['parse_mode' => ''])
            ->content(
                implode(
                    PHP_EOL,
                    [
                        '*管理员登入警示*'.PHP_EOL,
                        '管理员：'.$this->admin->name.' '.$this->admin->username,
                        'IP：'.$this->ipv4,
                        '地点：'.$location->countryName.' '.$location->cityName,
                    ]
                )
            );
    }

    public function via($notifiable)
    {
        return [TelegramChannel::class];
    }
}
