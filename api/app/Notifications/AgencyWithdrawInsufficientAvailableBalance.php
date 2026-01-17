<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class AgencyWithdrawInsufficientAvailableBalance extends Notification
{
    use Queueable;

    public $transactionId;
    public $merchant;
    public $channel;
    public $amount;

    public function __construct($transactionId, $merchant, $amount)
    {
        $this->transactionId = $transactionId;
        $this->merchant = $merchant;
        $this->amount = $amount;
    }

    public function toTelegram($notifiable)
    {
        return TelegramMessage::create()
            ->options(["parse_mode" => ""])
            ->content(
                implode(PHP_EOL, [
                    "*三方代付餘額不足*" . PHP_EOL,
                    "订单号：" . $this->transactionId,
                    "商户：" . $this->merchant,
                    "金額：" . $this->amount,
                ])
            );
    }

    public function via($notifiable)
    {
        return [TelegramChannel::class];
    }
}
