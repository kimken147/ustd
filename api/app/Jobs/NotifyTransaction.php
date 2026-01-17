<?php

namespace App\Jobs;

use App\Models\Channel;
use App\Models\Transaction;
use App\Utils\GuzzleHttpClientTrait;
use Exception;
use GuzzleHttp\RequestOptions;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use RuntimeException;

class NotifyTransaction implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, GuzzleHttpClientTrait;

    /**
     * @var Transaction
     */
    public $transaction;

    /**
     * Create a new job instance.
     *
     * @param  Transaction  $transaction
     */
    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
        $this->queue = config('queue.queue-priority.high');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (!$this->transaction->notify_url) {
            Log::debug(__CLASS__, [
                'order_number'        => $this->transaction->order_number,
                'system_order_number' => $this->transaction->system_order_number,
                'message'             => 'Empty notify_url',
            ]);

            return;
        }

        // 這段是 HOTFIX 修正目前 TransactionUtil 中因為 DB Transaction 未完成時，就會先 dispatch job 的問題
        if (!in_array($this->transaction->status, [
            Transaction::STATUS_SUCCESS, //成功
            Transaction::STATUS_MANUAL_SUCCESS, //成功
            Transaction::STATUS_FAILED      //失败
        ])) {
            $this->release(10);
            return;
        }

        $this->transaction->update([
            'notify_status' => Transaction::NOTIFY_STATUS_SENDING,
        ]);

        $targetUser = null;

        switch ($this->transaction->type) {
            case Transaction::TYPE_PAUFEN_TRANSACTION:
                $targetUser = $this->transaction->to;
                break;
            case Transaction::TYPE_NORMAL_WITHDRAW:
            case Transaction::TYPE_PAUFEN_WITHDRAW:
                $targetUser = $this->transaction->from;
                break;
            default:
                throw new RuntimeException('Unsupported transaction type');
        }

        if (!$targetUser) {
            throw new RuntimeException('Target user null');
        }

        $mainData = [
            'order_number'        => $this->transaction->order_number,
            'system_order_number' => $this->transaction->system_order_number,
            'username'            => $targetUser->username,
            'amount'              => $this->transaction->amount,
            'status'              => $this->transaction->status,
        ];

        if ($this->transaction->channel_code == Channel::CODE_USDT) {
            $mainData['usdt_rate'] = $this->transaction->usdt_rate;
            $mainData['rate_amount'] = $this->transaction->rate_amount;
        }

        $data = [
            'data'             => $mainData,
            'http_status_code' => Response::HTTP_OK,
            'error_code'       => 0,
            'message'          => '异步回调',
        ];

        $parameters = $data['data'];

        ksort($parameters);

        $data['data']['sign'] = md5(urldecode(http_build_query($parameters) . '&secret_key=' . $targetUser->secret_key));

        $responseContents = null;

        Log::debug(__CLASS__ . '::Request', [
            'data' => $data,
            'url' => $this->transaction->notify_url
        ]);

        try {
            $client = new Client([
                RequestOptions::HTTP_ERRORS     => false,
                RequestOptions::TIMEOUT         => 10,
                RequestOptions::CONNECT_TIMEOUT => 10,
                RequestOptions::VERIFY          => false,
            ]);
            $response = $client->post(
                $this->transaction->notify_url,
                [
                    RequestOptions::JSON => $data,
                ]
            );

            $responseContents = $response->getBody()->getContents();
        } catch (Exception $e) {
            Log::debug(__CLASS__, [
                'order_number'        => $this->transaction->order_number,
                'system_order_number' => $this->transaction->system_order_number,
                'message'             => 'Notify failed with exception',
                'exception'           => $e,
            ]);
        }

        if (!in_array(strtolower($responseContents), ['success', 'ok']) && $this->attempts() <= 2) {
            $this->transaction->update([
                'notify_status' => Transaction::NOTIFY_STATUS_PENDING,
            ]);

            Log::debug(__CLASS__, [
                'order_number'        => $this->transaction->order_number,
                'system_order_number' => $this->transaction->system_order_number,
                'message'             => $responseContents,
            ]);

            $this->release(30);

            return;
        }

        if (!in_array(strtolower($responseContents), ['success', 'ok']) && $this->attempts() > 2) {
            Log::debug(__CLASS__, [
                'order_number'        => $this->transaction->order_number,
                'system_order_number' => $this->transaction->system_order_number,
                'message'             => $responseContents,
            ]);
            $this->transaction->update([
                'notify_status' => Transaction::NOTIFY_STATUS_FAILED,
            ]);

            return;
        }

        $this->transaction->update([
            'notified_at'   => now(),
            'notify_status' => Transaction::NOTIFY_STATUS_SUCCESS,
        ]);
    }
}
