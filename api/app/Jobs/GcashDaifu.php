<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use App\Http\Controllers\Country\PhilippineController;
use App\Model\Transaction;
use App\Model\UserChannelAccount;
use App\Utils\TransactionUtil;
use Illuminate\Support\Facades\Redis;

use App\Repository\FeatureToggleRepository;
use App\Model\FeatureToggle;

class GcashDaifu implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $transaction;
    private $status;

    public $tries = 0;

    public function __construct(Transaction $transaction, $status = 'init')
    {
        $this->status = $status;
        $this->transaction = $transaction;
        $this->queue = config('queue.queue-priority.high');
    }

    /**
     * Execute the job.
     *
     * @param  TransactionUtil  $transactionUtil
     * @return void
     */
    public function handle(TransactionUtil $transactionUtil, FeatureToggleRepository $featureToggleRepository)
    {
        if ($this->attempts() >= 1) {
            $this->delete();
        }
        Log::info(__METHOD__, [$this->transaction, $this->status]);

        if (
            config('app.region') != 'ph' ||
            !$featureToggleRepository->enabled(FeatureToggle::AUTO_DAIFU, true) ||
            $featureToggleRepository->valueOf(FeatureToggle::AUTO_DAIFU) != 2
        ) {
            return false;
        }

        $transaction = $this->transaction->refresh();

        if ($transaction->status != Transaction::STATUS_PAYING) {
            return;
        }

        $account = $transaction->toChannelAccount;

        if (!isset($account['account']) || !isset($account->detail['mpin'])) {
            return false;
        }

        if (!$account->is_auto) {
            return false;
        }

        $mobile = Str::padLeft($account['account'], 11, 0);
        $controller = app(PhilippineController::class);
        $id = $transaction->id;

        if ($this->status == 'init') {
            $mobileNumberRequest = new Request(['order_number' => $transaction->id, 'mobile_number' => $mobile]);
            $result = $controller->storeGcashData($mobileNumberRequest, app(TransactionUtil::class));
        }

        if ($this->status == 'mpin' && isset($account->detail['mpin'])) {
            if (!Redis::set("{$id}:gcash:daifu:mpin", 1, 'EX', 15, 'NX')) {
                return;
            }

            $mpinRequest = new Request(['order_number' => $transaction->id, 'mpin' => $account->detail['mpin']]);
            $result = $controller->storeGcashData($mpinRequest, app(TransactionUtil::class));
        }

        if ($this->status == 'pay') {
            if (!Redis::set("{$id}:gcash:daifu:pay", 1, 'EX', 180, 'NX')) {
                return;
            }

            $payRequest = new Request(['order_number' => $transaction->id, 'click_pay' => true, 'get_user_info' => true]);
            $result = $controller->storeGcashData($payRequest, app(TransactionUtil::class));
        }

        if ($this->status == 'otp') {
            if (!Redis::set("{$id}:gcash:daifu:otp", 1, 'EX', 15, 'NX')) {
                return;
            }

            $otpRequest = new Request(['order_number' => $transaction->id, 'resend_otp' => true]);
            $result = $controller->storeGcashData($otpRequest, app(TransactionUtil::class));
        }
    }
}
