<?php

namespace App\Services\Withdraw;

use App\Models\FeatureToggle;
use App\Models\MerchantThirdChannel;
use App\Models\ThirdChannel;
use App\Models\Transaction;
use App\Models\TransactionNote;
use App\Models\User;
use App\Repository\FeatureToggleRepository;
use App\Utils\TransactionUtil;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ThirdChannelDispatcher
{
    public function __construct(
        private readonly FeatureToggleRepository $featureToggleRepository,
        private readonly TransactionUtil $transactionUtil,
    ) {}

    /**
     * Dispatch withdraw to third-party channels or fallback to local processing
     */
    public function dispatch(
        User $merchant,
        string $amount,
        string $orderNumber,
        array $bankCardData,
        callable $onThirdChannelSuccess,
        callable $onLocalFallback,
    ): Transaction {
        if (!$merchant->third_channel_enable) {
            return $onLocalFallback();
        }

        $channels = $this->getAvailableChannels($merchant, $amount);

        if ($channels->isEmpty()) {
            $transaction = $onLocalFallback();
            $this->addNote($transaction, '无符合当前代付金额的三方可用，请调整限额设定');
            $this->markAsFailedIfEnabled($transaction, '无符合当前代付金额的三方可用，请调整限额设定');
            return $transaction;
        }

        $channels = $this->filterByThreshold($channels, $amount)->shuffle();

        if ($channels->isEmpty()) {
            $transaction = $onLocalFallback();
            $this->addNote($transaction, '无自动推送门槛内的三方可用，请手动推送');
            $this->markAsFailedIfEnabled($transaction, '无自动推送门槛内的三方可用，请手动推送');
            return $transaction;
        }

        return $this->tryChannels(
            $channels,
            $amount,
            $orderNumber,
            $bankCardData,
            $onThirdChannelSuccess,
            $onLocalFallback
        );
    }

    private function getAvailableChannels(User $merchant, string $amount): Collection
    {
        return MerchantThirdChannel::with('thirdChannel')
            ->where('owner_id', $merchant->id)
            ->where('daifu_min', '<=', $amount)
            ->where('daifu_max', '>=', $amount)
            ->whereHas('thirdChannel', function (Builder $query) {
                $query->where('status', ThirdChannel::STATUS_ENABLE)
                    ->where('type', '!=', ThirdChannel::TYPE_DEPOSIT_ONLY);
            })
            ->get();
    }

    private function filterByThreshold(Collection $channels, string $amount): Collection
    {
        return $channels->filter(function ($channel) use ($amount) {
            return $amount >= $channel->thirdChannel->auto_daifu_threshold_min
                && $amount <= $channel->thirdChannel->auto_daifu_threshold;
        });
    }

    private function tryChannels(
        Collection $channels,
        string $amount,
        string $orderNumber,
        array $bankCardData,
        callable $onThirdChannelSuccess,
        callable $onLocalFallback,
    ): Transaction {
        $tryOnce = $this->featureToggleRepository->enabled(FeatureToggle::TRY_NEXT_IF_THIRDCHANNEL_DAIFU_FAIL);

        if (!$tryOnce) {
            $channels = $channels->take(1);
        }

        $messages = [];
        $lastKey = $channels->keys()->last();

        foreach ($channels as $key => $channel) {
            Log::debug("{$orderNumber} 请求 {$channel->thirdChannel->class}({$channel->thirdChannel->merchant_id})");

            $result = $this->tryChannel($channel, $amount, $orderNumber, $bankCardData);

            if ($result['message']) {
                $messages[] = "{$channel->thirdChannel->name}: {$result['message']}";
            }

            if ($result['shouldAssign']) {
                $transaction = $onThirdChannelSuccess($channel->thirdChannel->id);
                $this->addNotes($transaction, $messages);
                return $transaction;
            }

            if ($key === $lastKey) {
                $transaction = $onLocalFallback();
                $messages[] = '无自动推送门槛内的三方可用，请手动推送';
                $this->addNotes($transaction, $messages);
                $this->markAsFailedIfEnabled($transaction, $result['message'] ?? null);
                return $transaction;
            }
        }

        // Should never reach here, but fallback just in case
        return $onLocalFallback();
    }

    private function tryChannel(
        MerchantThirdChannel $channel,
        string $amount,
        string $orderNumber,
        array $bankCardData
    ): array {
        $thirdChannel = $channel->thirdChannel;
        $path = "App\\ThirdChannel\\{$thirdChannel->class}";
        $api = new $path();

        preg_match("/^(?:https?:\/\/)?(?:[^@\n]+@)?(?:www\.)?([^:\/\n?]+)/", $api->daifuUrl, $url);

        $data = $this->buildApiData($api, $channel, $orderNumber, $bankCardData, $url[1] ?? '');

        $balance = $api->queryBalance($data);

        if ($balance <= $amount) {
            Log::debug("{$orderNumber} 请求 {$thirdChannel->class}({$thirdChannel->merchant_id}) 余额不足");
            return [
                'shouldAssign' => false,
                'message' => '三方余额不足',
            ];
        }

        $returnData = $api->sendDaifu($data);
        $message = $returnData['msg'] ?? '';

        if ($returnData['success']) {
            return [
                'shouldAssign' => true,
                'message' => $message,
            ];
        }

        // Query to check if order was created on third-party side
        $query = $api->queryDaifu($data);
        $isSuccessOrTimeout = (isset($query['success']) && $query['success'])
            || (isset($query['timeout']) && $query['timeout']);

        return [
            'shouldAssign' => $isSuccessOrTimeout,
            'message' => $message,
        ];
    }

    private function buildApiData(
        object $api,
        MerchantThirdChannel $channel,
        string $orderNumber,
        array $bankCardData,
        string $urlHost
    ): array {
        $thirdChannel = $channel->thirdChannel;

        $data = [
            'url' => preg_replace("/{$urlHost}/", $thirdChannel->custom_url, $api->daifuUrl),
            'queryDaifuUrl' => preg_replace("/{$urlHost}/", $thirdChannel->custom_url, $api->queryDaifuUrl),
            'queryBalanceUrl' => preg_replace("/{$urlHost}/", $thirdChannel->custom_url, $api->queryBalanceUrl),
            'callback_url' => config('app.url') . '/api/v1/callback/' . $orderNumber,
            'merchant' => $thirdChannel->merchant_id,
            'key' => $thirdChannel->key,
            'key2' => $thirdChannel->key2,
            'key3' => $thirdChannel->key3,
            'key4' => $thirdChannel->key4,
            'key5' => $thirdChannel->key5,
            'proxy' => $thirdChannel->proxy,
            'request' => (object) $bankCardData,
            'thirdchannelId' => $thirdChannel->id,
            'order_number' => $orderNumber,
            'system_order_number' => $orderNumber,
        ];

        if (property_exists($api, 'alipayDaifuUrl')) {
            $data['alipayDaifuUrl'] = preg_replace("/{$urlHost}/", $thirdChannel->custom_url, $api->alipayDaifuUrl);
        }

        return $data;
    }

    private function addNote(Transaction $transaction, string $note): void
    {
        TransactionNote::create([
            'user_id' => 0,
            'transaction_id' => $transaction->id,
            'note' => $note,
        ]);
    }

    private function addNotes(Transaction $transaction, array $notes): void
    {
        foreach ($notes as $note) {
            $this->addNote($transaction, $note);
        }
    }

    private function markAsFailedIfEnabled(Transaction $transaction, ?string $message): void
    {
        if ($this->featureToggleRepository->enabled(FeatureToggle::IF_THIRDCHANNEL_DAIFU_FIAL_THAN_ORDER_FAIL)) {
            $this->transactionUtil->markAsFailed($transaction, null, $message, false);
        }
    }
}
