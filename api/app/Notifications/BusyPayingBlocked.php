<?php

namespace App\Notifications;

use App\Model\User;
use App\Utils\AmountDisplayTransformer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class BusyPayingBlocked extends Notification implements ShouldQueue
{

    use Queueable;

    /**
     * @var string
     */
    public $amount;

    /**
     * @var string
     */
    public $ipv4;
    /**
     * @var User
     */
    public $merchant;
    /**
     * @var string
     */
    public $orderNumber;

    public function __construct(User $merchant, string $orderNumber, string $ipv4, string $amount)
    {
        $this->merchant = $merchant;
        $this->orderNumber = $orderNumber;
        $this->ipv4 = $ipv4;
        $this->amount = $amount;
    }

    public function toTelegram($notifiable)
    {
        return TelegramMessage::create()
            ->options(['parse_mode' => ''])
            ->content(
                implode(
                    PHP_EOL,
                    [
                        '*刷单警示*'.PHP_EOL,
                        '商户：'.$this->merchant->name.' '.$this->merchant->username,
                        '订单号：'.$this->orderNumber,
                        '金额：'.AmountDisplayTransformer::transform($this->amount),
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
