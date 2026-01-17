<?php

namespace App\Jobs;

use App\Model\DevicePayingTransaction;
use App\Model\FeatureToggle;
use App\Model\Transaction;
use App\Notifications\UserChannelAccountTooManyPayingTimeout;
use App\Repository\FeatureToggleRepository;
use App\Utils\BCMathUtil;
use App\Utils\NotificationUtil;
use App\Utils\WalletUtil;
use App\Utils\UserChannelAccountUtil;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Illuminate\Support\Facades\Log;

class MarkPaufenTransactionPayingTimedOut implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var Transaction
     */
    private $transactionId;

    public function __construct($transactionId)
    {
        $this->transactionId = $transactionId;
        $this->queue = config('queue.queue-priority.medium');
    }

    public function handle(WalletUtil $wallet, BCMathUtil $bcMath, NotificationUtil $notificationUtil, FeatureToggleRepository $featureToggleRepository, UserChannelAccountUtil $userChannelAccountUtil)
    {
        $transaction = Transaction::where([
            ['id', $this->transactionId],
            ['status', Transaction::STATUS_PAYING],
        ])->first();

        if (!$transaction) return;

        DB::transaction(function () use ($wallet, $transaction, $featureToggleRepository) {
            // 如果近幾次的交易都是支付超時，則關閉碼商的交易
            $from = optional($transaction)->from;

            $updateData = ['status' => Transaction::STATUS_PAYING_TIMED_OUT];
            if ($from && $from->cancel_order_enable || $featureToggleRepository->enabled(FeatureToggle::CANCEL_PAUFEN_MECHANISM)) {
                // 如果启用销单，则付款超时不退还预扣
                // 免簽模式，款超时不退还预扣
            } else {
                $wallet->withdrawRollback(
                    $transaction->fromWallet,
                    $transaction->floating_amount,
                    $transaction->system_order_number,
                    $transactionType = 'transaction',
                );
                $updateData['refunded_at'] = now();
            }

            $updateResult = $transaction->update($updateData);

            DevicePayingTransaction::where([
                'user_channel_account_id' => $transaction->from_channel_account_id,
                'transaction_id'          => $transaction->getKey(),
            ])->delete();
        });

        $userChannelAccountId = $transaction->from_channel_account_id;
        $payingTimeoutCacheKey = "user-channel-account-paying-timeout-$userChannelAccountId";

        if ($featureToggleRepository->enabled(FeatureToggle::NOTIFY_NON_SUCCESS_USER_CHANNEL_ACCOUNT, true)) {
            Cache::put($payingTimeoutCacheKey, $currentCount = Cache::increment($payingTimeoutCacheKey), now()->addDay());

            if ($currentCount >= $featureToggleRepository->valueOf(FeatureToggle::NOTIFY_NON_SUCCESS_USER_CHANNEL_ACCOUNT, 5, true)) {
                Cache::forget($payingTimeoutCacheKey);

                $notificationUtil->notify(
                    new UserChannelAccountTooManyPayingTimeout($transaction)
                );
            }
        }

        // 如果近幾次的交易都是支付超時，則關閉碼商的交易
        $from = optional($transaction)->from;
        if ($featureToggleRepository->enabled(FeatureToggle::DISABLE_TRANSACTION_IF_PAYING_TIMEOUT, true) && $from && $from->isProvider()) {
            $value = $featureToggleRepository->valueOf(FeatureToggle::DISABLE_TRANSACTION_IF_PAYING_TIMEOUT, 5, true);
            $key = "provider:{$from->username}:paying:timeout:times";

            if (Redis::incr($key) == $value) {
                $from->update(['transaction_enable' => false]);
                Redis::set($key, 0);
            }
        }
    }
}
