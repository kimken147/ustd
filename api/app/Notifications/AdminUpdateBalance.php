<?php

namespace App\Notifications;

use App\Model\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class AdminUpdateBalance extends Notification implements ShouldQueue
{

    use Queueable;

    /**
     * @var User
     */
    public $admin;
    /**
     * @var array
     */
    public $delta;
    /**
     * @var string
     */
    public $ipv4;
    /**
     * @var string|null
     */
    public $note;
    /**
     * @var User
     */
    public $targetUser;

    public function __construct(User $admin, User $targetUser, array $delta, string $note, string $ipv4)
    {
        $this->admin = $admin;
        $this->targetUser = $targetUser;
        $this->delta = $delta;
        $this->note = $note ?? '';
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
                        '*钱包修改警示*'.PHP_EOL,
                        '使用者：'.$this->targetUser->name.' '.$this->targetUser->username,
                        '总余额调整：'.data_get($this->delta, 'balance', 0),
                        '冻结调整：'.data_get($this->delta, 'frozen_balance', 0),
                        '红利调整：'.data_get($this->delta, 'profit', 0),
                        '备注：'.$this->note,
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
