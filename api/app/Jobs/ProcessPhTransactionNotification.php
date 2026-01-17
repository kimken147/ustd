<?php

namespace App\Jobs;

use App\Model\UserChannelAccount;
use App\Model\Device;
use App\Model\FeatureToggle;
use App\Model\Transaction;
use App\Model\Notification;
use App\Model\Channel;
use App\Repository\FeatureToggleRepository;
use App\Utils\TransactionUtil;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redis;

class ProcessPhTransactionNotification implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var Device
     */
    public $device;

    /**
     * @var string
     */
    public $payload;

    /**
     * Create a new job instance.
     *
     * @param  Device  $device
     * @param  string  $payload
     */
    public function __construct(Device $device, string $payload)
    {
        $this->device = $device;
        $this->payload = $payload;
    }

    /**
     * Execute the job.
     *
     * @param  TransactionUtil  $transactionUtil
     * @return void
     */
    public function handle(TransactionUtil $transactionUtil, FeatureToggleRepository $featureToggleRepository)
    {
        $provider = $this->device->user;

        if (!$featureToggleRepository->enabled(FeatureToggle::FEATURE_PROCESS_TRANSACTION_NOTIFICATION, true)) {
            return;
        }

        $payload = json_decode($this->payload);

        if (!$payload) {
            return;
        }

        $title = data_get($payload, 'title', 'none');
        $message = data_get($payload, 'message', 'none');

        $existsIn5Mins = Notification::where('notification', "{$title}:{$message}")
            ->where('device_id', $this->device->id)
            ->where('created_at', '>=', Carbon::now()->subMinutes(5))
            ->exists();

        if ($existsIn5Mins) {
            return;
        }

        $deviceId = !isset($this->device->id) ? 0 : $this->device->id;
        $notification = Notification::create(['notification' => "{$title}:{$message}", 'device_id' => $deviceId]);

        $appName = strtolower(data_get($payload, 'app_name', 'none'));

        $message = preg_replace('/\\r\\n|\\n/', ' ', $message); // 將 \n \r\n 換成 空白
        $matches = $this->extractData($appName, $title, $message);

        Log::debug(
            self::class,
            compact('payload', 'message', 'matches')
        );

        foreach ($matches as $match) {
            $notifiedAt = Carbon::make(data_get($payload, 'notified_at'))->tz(config('app.timezone'));
            $provider = $this->device->user;
            $deviceName = $this->device->name;

            $query = Transaction::where([
                ['from_id', $provider->getKey()],
                ['floating_amount', $match['amount']],
                ['from_device_name', $deviceName],
                ['type', Transaction::TYPE_PAUFEN_TRANSACTION],
            ])
            ->whereIn('channel_code', $match['channelCodes'])
            ->whereIn('status', [Transaction::STATUS_PAYING, Transaction::STATUS_PENDING_REVIEW]);

            if ($featureToggleRepository->enabled(FeatureToggle::FEATURE_PROCESS_TRANSACTION_NOTIFICATION, true)) {
                $query->where('matched_at', '>', $notifiedAt->subSeconds($featureToggleRepository->valueOf(FeatureToggle::FEATURE_PROCESS_TRANSACTION_NOTIFICATION, null, true)));
            }

            if ($featureToggleRepository->enabled(FeatureToggle::NOTIFICATION_CHECK_REAL_NAME_AND_CARD, true)) {
                $query->where('to_channel_account->real_name', $match['realname']);
            }

            $transactions = $query->get();

            // Log::debug(__METHOD__, [
            //     'transactions_count' => count($transactions),
            //     'transaction_id' => $transactions->pluck('id'),
            //     'transaction_query' => Str::replaceArray('?', $query->getBindings(), $query->toSql())
            // ]);

            foreach ($transactions as $transaction) {
                if (!empty($match['realname']) && $transaction->to_channel_account['real_name'] != $match['realname']) {
                    continue;
                }

                if (!empty($match['note']) && $transaction->note != $match['note']) {
                    continue;
                }

                // Log::debug(__METHOD__, [
                //     'payload'        => $payload,
                //     'transaction_id' => $transaction->getKey(),
                //     'matched'        => 1,
                // ]);

                $transactionUtil->markAsSuccess($transaction, null, true);
                break;
            }
        }
    }

    private function extractData(string $appName, string $title, string $message)
    {
        return $this->extractFromBankSms($title, $message);
    }

    private function extractAmount(string $message, $default = 0)
    {
        $matches = [];

        return (preg_match('{(\d+\.?\d*)}', $message, $matches) === 1
            ? $matches[0]
            : $default
        );
    }

    private function extractFromBankSms(string $title, string $message)
    {
        $banks = [
            'GCash' => [
                'name' => ['GCash'],
                'regex' => ['/^.* received PHP (?<amount>\d+\.\d+).*(?<mobile>0\d{10}).*balance.*PHP (?<balance>\d+.\d+).*.*No\. (?<refno>\d+)/'],
                'channelCodes' => [Channel::CODE_GCASH, Channel::CODE_QR_GCASH]
            ]
        ];

        $bankParameters = data_get($banks, $title);

        if (empty($bankParameters)) {
            return [];
        }

        $bankName = $bankParameters['name'];
        $channelCodes =  $bankParameters['channelCodes'];
        return array_map(function ($regex) use ($message, $bankName, $channelCodes) {
            preg_match($regex, $message, $matches);
            return [
                'banks' => $bankName,
                'realname' => $matches['name'] ?? '',
                'mobile' => $matches['mobile'] ?? '',
                'amount' => str_replace([',', ' '], '', $matches['amount'] ?? ''),
                'balance' => str_replace([',', ' '], '', $matches['balance'] ?? ''),
                'card' => $matches['card'] ?? '',
                'note' => $matches['note'] ?? '',
                'channelCodes' => $channelCodes
            ];
        }, $bankParameters['regex']);
    }
}
