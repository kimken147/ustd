<?php

namespace App\Jobs;

use App\Model\Transaction;
use App\Utils\WalletUtil;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Illuminate\Support\Facades\Notification;
use App\Notifications\MatchedTimeout;
use Illuminate\Support\Facades\Redis;

class MarkPaufenTransactionMatchingTimedOut implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var WalletUtil
     */
    private $wallet;

    /**
     * @var Transaction
     */
    private $transaction;

    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
        $this->queue = config('queue.queue-priority.medium');
    }

    /**
     * Execute the job.
     *
     * @param  WalletUtil  $wallet
     * @return void
     */
    public function handle(WalletUtil $wallet)
    {
        if ($this->transaction->status != Transaction::STATUS_MATCHING) {
            return;
        }

        DB::transaction(function () use ($wallet) {
            $updatedRow = Transaction::where([
                ['id', $this->transaction->getKey()],
                ['type', Transaction::TYPE_PAUFEN_TRANSACTION],
                ['status', Transaction::STATUS_MATCHING],
            ])
            ->update(['status' => Transaction::STATUS_MATCHING_TIMED_OUT]);

            throw_if($updatedRow > 1, new RuntimeException('Unexpected row being updated'));

            // 若任務已經不在等待支付的狀態，則忽略此 Job
            if ($updatedRow === 0) {
                return;
            }
        });

        $trans = $this->transaction;
        if (Redis::set('notify:matched:timeout', 1, 'EX', 5, 'NX')) {
            Notification::route('telegram', config('services.telegram-bot-api.system-admin-group-id'))
            ->notify(new MatchedTimeout($trans->order_number, $trans->to->name, $trans->channel->name, $trans->amount));
        }
    }
}
