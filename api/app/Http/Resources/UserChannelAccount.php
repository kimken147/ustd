<?php

namespace App\Http\Resources;

use App\Models\Device;
use App\Models\UserChannelAccount as ModelUserChannelAccount;
use App\Utils\AmountDisplayTransformer;
use Hashids\Hashids;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * @property Device|null device
 */
class UserChannelAccount extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $dailyLimitEnabled = isset($this->user_channel_account_daily_limit_enabled) ? $this->user_channel_account_daily_limit_enabled : false;
        $dailyLimitValue   = isset($this->user_channel_account_daily_limit_value) ? $this->user_channel_account_daily_limit_value : false;

        $monthlyLimitEnabled = isset($this->user_channel_account_monthly_limit_enabled) ? $this->user_channel_account_monthly_limit_enabled : false;
        $monthlyLimitValue   = isset($this->user_channel_account_monthly_limit_value) ? $this->user_channel_account_monthly_limit_value : false;

        $dailyLimit = $this->daily_limit ?: $dailyLimitValue;
        $withdrawDailyLimit = $this->withdraw_daily_limit ?: $dailyLimitValue;
        $monthlyLimit = $this->monthly_limit ?: $monthlyLimitValue;
        $withdrawMonthlyLimit = $this->withdraw_monthly_limit ?: $monthlyLimitValue;

        $recordBalance = isset($this->record_user_channeL_account_balance) ? $this->record_user_channeL_account_balance : false;

        $bankName   = $this->bank->name ?? null;
        if ($this->bank_id != 0 && empty($this->bank))
            $bankName = '银行已删除';

        if ($this->type == ModelUserChannelAccount::TYPE_WITHDRAW) {
            $channelName = optional($this->channel)->name . ' 出款专用';
        } else {
            $channelName = $this->channelAmount->channel->name . ' ' . $this->channelAmount->amount_description;
        }

        return [
            'id'                  => $this->getKey(),
            'name'                => $this->name,
            'hash_id'             => (new Hashids())->encode($this->getKey()),
            'user'                => $this->user->only(['id', 'name', 'username']),
            'channel_code'        => $this->channel_code,
            'chain_network'       => data_get($this->detail, ModelUserChannelAccount::DETAIL_KEY_CHAIN_NETWORK),
            'channel_name'        => $channelName,
            'account'             => $this->account,
            'address_type'        => $this->address_type,
            'parent_account_id'   => $this->parent_account_id,
            'parent_account'      => $this->when($this->parent_account_id, function () {
                return [
                    'id'      => $this->parentAccount?->id,
                    'account' => $this->parentAccount?->account,
                    'name'    => $this->parentAccount?->name,
                ];
            }),
            'derivation_index'    => $this->derivation_index,
            'is_one_time'         => $this->is_one_time,
            'receive_status'      => $this->receive_status,
            'linked_transaction_id' => $this->linked_transaction_id,
            'child_count'         => $this->when($this->address_type === 'master', fn () => $this->childAccounts()->count()),
            'account_name'        => data_get(
                $this->detail,
                \App\Models\UserChannelAccount::DETAIL_KEY_BANK_CARD_HOLDER_NAME,
                data_get($this->detail, \App\Models\UserChannelAccount::DETAIL_KEY_RECEIVER_NAME)
            ),
            'bank_name'           => data_get(
                $this->detail,
                \App\Models\UserChannelAccount::DETAIL_KEY_BANK_NAME,
                data_get($this->detail, \App\Models\UserChannelAccount::DETAIL_KEY_BANK_NAME)
            ) ?? $bankName,
            'detail'              => $this->transformDetail($this->detail, $bankName),
            'bank_branch'        => data_get(
                $this->detail,
                \App\Models\UserChannelAccount::DETAIL_KEY_BANK_CARD_BRANCH,
                data_get($this->detail, \App\Models\UserChannelAccount::DETAIL_KEY_BANK_CARD_BRANCH)
            ) ?? '',
            'status'              => $this->status,
            'type'                => $this->type,
            'device'              => $this->device ? [
                'id'                => $this->device->getKey(),
                'name'              => $this->device->name,
                'last_heartbeat_at' => $this->deleted_at ? null : optional($this->device->last_heartbeat_at)->toIso8601String(),
            ] : null,
            'present_result'      => $this->channelAmount ? $this->channelAmount->channel->present_result : null,
            'time_limit_disabled' => $this->time_limit_disabled,
            'created_at'          => optional($this->created_at)->toIso8601String(),
            'deleted_at'          => optional($this->deleted_at)->toIso8601String(),
            'daily_status'        => $this->daily_status,
            'daily_limit'         => $dailyLimit,
            'daily_limit_value'   => $this->daily_limit ?: null,
            'daily_total'         => $this->daily_total,
            'daily_transaction_count_limit' => $this->daily_transaction_count_limit,
            'daily_transaction_count_total' => $this->daily_transaction_count_total,
            'withdraw_daily_limit' => $withdrawDailyLimit,
            'withdraw_daily_total' => $this->withdraw_daily_total,
            'withdraw_daily_transaction_count_limit' => $this->withdraw_daily_transaction_count_limit,
            'withdraw_daily_transaction_count_total' => $this->withdraw_daily_transaction_count_total,
            'monthly_status'      => $this->monthly_status,
            'monthly_limit'       => $monthlyLimit,
            'monthly_limit_value' => $this->monthly_limit ?: null,
            'monthly_total'       => $this->monthly_total,
            'monthly_transaction_count_limit' => $this->monthly_transaction_count_limit,
            'monthly_transaction_count_total' => $this->monthly_transaction_count_total,
            'withdraw_monthly_limit' => $withdrawMonthlyLimit,
            'withdraw_monthly_total' => $this->withdraw_monthly_total,
            'withdraw_monthly_transaction_count_limit' => $this->withdraw_monthly_transaction_count_limit,
            'withdraw_monthly_transaction_count_total' => $this->withdraw_monthly_transaction_count_total,
            'user_channel_account_daily_limit_enabled' => $dailyLimitEnabled,
            'user_channel_account_daily_limit_value'   => $dailyLimitValue,
            'user_channel_account_monthly_limit_enabled' => $monthlyLimitEnabled,
            'user_channel_account_monthly_limit_value'   => $monthlyLimitValue,
            'record_user_channeL_account_balance' => $recordBalance,
            'balance'             => $this->balance,
            'balance_limit'       => $this->balance_limit,
            'has_private_key'      => !empty(data_get($this->detail, ModelUserChannelAccount::DETAIL_KEY_ENCRYPTED_PRIVATE_KEY)),
            'onchain_usdt_balance' => $this->onchain_usdt_balance,
            'onchain_native_balance' => $this->onchain_native_balance,
            'onchain_energy_available' => $this->onchain_energy_available,
            'onchain_energy_limit' => $this->onchain_energy_limit,
            'onchain_bandwidth_available' => $this->onchain_bandwidth_available,
            'onchain_bandwidth_limit' => $this->onchain_bandwidth_limit,
            'onchain_synced_at'    => optional($this->onchain_synced_at)->toIso8601String(),
            'is_auto'             => $this->is_auto,
            'auto_sync'           => $this->auto_sync,
            'note'                => $this->note,
            'single_min_limit'              => $this->single_min_limit,
            'single_max_limit'              => $this->single_max_limit,
            'withdraw_single_min_limit'     => $this->withdraw_single_min_limit,
            'withdraw_single_max_limit'     => $this->withdraw_single_max_limit,
        ];
    }

    private function transformDetail($detail, $bankName = null)
    {
        if (empty($detail)) {
            return new \stdClass();
        }

        // Scrub encrypted private key from API responses
        unset($detail[\App\Models\UserChannelAccount::DETAIL_KEY_ENCRYPTED_PRIVATE_KEY]);

        try {
            if ($qrCodeFilePath = data_get(
                $detail,
                \App\Models\UserChannelAccount::DETAIL_KEY_QR_CODE_FILE_PATH
            )) {
                data_set(
                    $detail,
                    \App\Models\UserChannelAccount::DETAIL_KEY_QR_CODE_FILE_PATH,
                    Storage::disk('user-channel-accounts-qr-code')->temporaryUrl($qrCodeFilePath, now()->addHour())
                );
            }

            //更換 detail 銀行名稱
            if (!is_null($bankName) && $bank_name = data_get($detail, 'bank_name')) {
                data_set($detail, 'bank_name', $bankName);
            }
        } catch (RuntimeException $e) {
            Log::debug($e);

            return $detail;
        }

        return $detail;
    }

    public function generateTags(): array
    {
        return [
            $this->transactionId
        ];
    }
}
