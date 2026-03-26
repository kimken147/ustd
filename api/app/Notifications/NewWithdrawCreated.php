<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class NewWithdrawCreated extends Notification
{
    use Queueable;

    public string $systemOrderNumber;
    public string $merchantName;
    public string $amount;
    public string $bankName;
    public string $subTypeLabel;

    public function __construct(string $systemOrderNumber, string $merchantName, string $amount, string $bankName, int $subType)
    {
        $this->systemOrderNumber = $systemOrderNumber;
        $this->merchantName = $merchantName;
        $this->amount = $amount;
        $this->bankName = $bankName;
        // 代付(sub_type=2) 或 下發
        $this->subTypeLabel = $subType === 2 ? '代付' : '下發';
    }

    public function toTelegram($notifiable)
    {
        return TelegramMessage::create()
            ->options(["parse_mode" => ""])
            ->content(
                implode(PHP_EOL, [
                    "*新代付單通知*" . PHP_EOL,
                    "類型：" . $this->subTypeLabel,
                    "訂單號：" . $this->systemOrderNumber,
                    "商戶：" . $this->merchantName,
                    "金額：" . $this->amount,
                    "銀行：" . $this->bankName,
                ])
            );
    }

    public function via($notifiable)
    {
        return [TelegramChannel::class];
    }
}
