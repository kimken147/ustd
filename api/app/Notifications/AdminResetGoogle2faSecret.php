<?php

namespace App\Notifications;

use App\Model\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class AdminResetGoogle2faSecret extends Notification implements ShouldQueue
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
    /**
     * @var User
     */
    public $targetUser;

    public function __construct(User $admin, User $targetUser, string $ipv4)
    {
        $this->admin = $admin;
        $this->targetUser = $targetUser;
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
                        '*谷歌验证码重置警示*'.PHP_EOL,
                        '使用者：'.$this->targetUser->name.' '.$this->targetUser->username,
                        '管理员：'.$this->admin->name.' '.$this->admin->username,
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
