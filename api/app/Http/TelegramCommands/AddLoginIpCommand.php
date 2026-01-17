<?php

namespace App\Http\TelegramCommands;

use App\Model\User;
use App\Model\WhitelistedIp;
use Telegram\Bot\Commands\Command;

class AddLoginIpCommand extends Command
{

    protected $description = 'Add login ip';
    protected $name = 'addloginip';

    /**
     * {@inheritdoc}
     */
    public function handle()
    {
        if ($this->update->message->chat->id != config('services.telegram-bot-api.engineer-leader-group-id')) {
            return $this->replyWithMessage(['text' => '你是谁？']);
        }

        $arguments = explode(' ', $this->update->message->text);

        if (count($arguments) !== 3) {
            return $this->replyWithMessage(['text' => '格式错误']);
        }

        $username = $arguments[1];
        $ipv4 = $arguments[2];

        /** @var User $user */
        $user = User::where('username', $username)->first();

        if (!$user) {
            return $this->replyWithMessage(['text' => '查无使用者']);
        }

        if (!filter_var($ipv4, FILTER_VALIDATE_IP)) {
            return $this->replyWithMessage(['text' => 'IP 格式错误']);
        }

        WhitelistedIp::insertIgnore([
            [
                'type'       => WhitelistedIp::TYPE_LOGIN,
                'user_id'    => $user->getKey(),
                'ipv4'       => ip2long($ipv4),
                'created_at' => $now = now(),
                'updated_at' => $now,
            ]
        ]);

        return $this->replyWithMessage(['text' => 'IP 设定成功']);
    }
}
