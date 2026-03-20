<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Utils\GuzzleHttpClientTrait;
use App\Utils\SignatureCalculator;
use Exception;
use GuzzleHttp\RequestOptions;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
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
                'out_trade_no' => $this->transaction->order_number,
                'trade_no'     => $this->transaction->system_order_number,
                'message'      => 'Empty notify_url',
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
            'out_trade_no'  => $this->transaction->order_number,
            'trade_no'      => $this->transaction->system_order_number,
            'merchant_id'   => $targetUser->username,
            'amount'        => $this->transaction->amount,
            'status'        => Transaction::toMerchantStatus($this->transaction->status),
        ];

        if ($this->transaction->chain_network) {
            $mainData['chain_network'] = $this->transaction->chain_network;
        }
        if ($this->transaction->tx_hash) {
            $mainData['tx_hash'] = $this->transaction->tx_hash;
        }

        $data = [
            'data'       => $mainData,
            'error_code' => 0,
            'message'    => '异步回调',
        ];

        $parameters = $data['data'];

        $data['data']['sign'] = SignatureCalculator::calculate($parameters, $targetUser->secret_key);

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
                'out_trade_no' => $this->transaction->order_number,
                'trade_no'     => $this->transaction->system_order_number,
                'message'      => 'Notify failed with exception',
                'exception'    => $e,
            ]);
        }

        if (!in_array(strtolower($responseContents), ['success', 'ok']) && $this->attempts() <= 2) {
            $this->transaction->update([
                'notify_status' => Transaction::NOTIFY_STATUS_PENDING,
            ]);

            Log::debug(__CLASS__, [
                'out_trade_no' => $this->transaction->order_number,
                'trade_no'     => $this->transaction->system_order_number,
                'message'      => $responseContents,
            ]);

            $this->release(30);

            return;
        }

        if (!in_array(strtolower($responseContents), ['success', 'ok']) && $this->attempts() > 2) {
            Log::debug(__CLASS__, [
                'out_trade_no' => $this->transaction->order_number,
                'trade_no'     => $this->transaction->system_order_number,
                'message'      => $responseContents,
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
