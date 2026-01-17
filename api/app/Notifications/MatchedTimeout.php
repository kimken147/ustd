<?php

namespace App\Notifications;

use App\Model\Transaction;
use App\Model\User;
use App\Utils\AmountDisplayTransformer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class MatchedTimeout extends Notification implements ShouldQueue
{
    use Queueable;

    public $transactionId;
    public $merchant;
    public $channel;
    public $amount;

    public function __construct($transactionId, $merchant, $channel, $amount)
    {
        $this->transactionId = $transactionId;
        $this->merchant = $merchant;
        $this->channel = $channel;
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
                        '*订单匹配超时*'.PHP_EOL,
                        '订单号：'.$this->transactionId,
                        '商户：'.$this->merchant,
                        '通道：'.$this->channel,
                        '金額：'.$this->amount
                    ]
                )
            );
    }

    public function via($notifiable)
    {
        return [TelegramChannel::class];
    }
}
