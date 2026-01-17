<?php

namespace App\Notifications;

use App\Model\User;
use App\Model\UserChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class ChangeChannelFee extends Notification implements ShouldQueue
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
     * @var UserChannel
     */
    public $channel;

    public $before;

    public $after;

    public function __construct(User $admin, UserChannel $channel, string $ipv4, $before, $after)
    {
        $this->admin = $admin;
        $this->channel = $channel;
        $this->ipv4 = $ipv4;
        $this->before = $before;
        $this->after = $after;
    }

    public function toTelegram($notifiable)
    {
        $targetUser = $this->channel->user;
        $channelGroup = $this->channel->channelGroup;
        return TelegramMessage::create()
            ->options(['parse_mode' => ''])
            ->content(
                implode(
                    PHP_EOL,
                    [
                        '*费率修改警示*'.PHP_EOL,
                        '使用者：'.$targetUser->name.' '.$targetUser->username,
                        '通道：'.$channelGroup->channel->name.' '.$channelGroup->amount_description.' '.$this->channel->id,
                        '费率：'.$this->before.'% => '.$this->after.'%',
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
