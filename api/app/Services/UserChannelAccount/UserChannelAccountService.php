<?php

namespace App\Services\UserChannelAccount;

use App\Models\Bank;
use App\Models\ChannelAmount;
use App\Models\Device;
use App\Models\Transaction;
use App\Models\TransactionGroup;
use App\Models\User;
use App\Models\UserChannel;
use App\Models\UserChannelAccount;
use App\Models\Channel;
use App\Jobs\BackfillChainTransactions;
use App\Services\Crypto\EthAddressService;
use App\Services\Crypto\TronAddressService;
use App\Services\QrCodeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserChannelAccountService
{
    public function __construct(
        private readonly QrCodeService $qrCodeService,
        private readonly TronAddressService $tronAddressService,
        private readonly EthAddressService $ethAddressService,
    ) {
    }

    public function validateChannelAmount(int $channelAmountId): ChannelAmount
    {
        $channelAmount = ChannelAmount::find($channelAmountId);

        abort_if(
            !$channelAmount,
            Response::HTTP_BAD_REQUEST,
            __('channel.User channel not found')
        );

        return $channelAmount;
    }

    public function validateUserChannel(User $provider, ChannelAmount $channelAmount): UserChannel
    {
        $userChannel = UserChannel::where([
            'user_id'          => $provider->getKey(),
            'channel_group_id' => $channelAmount->channel_group_id,
        ])->first();

        abort_if(!$userChannel, Response::HTTP_BAD_REQUEST, __('channel.User channel not found'));

        abort_if(is_null($userChannel->fee_percent), Response::HTTP_BAD_REQUEST, '通道费率未设定');

        return $userChannel;
    }

    public function validateAccountUniqueness(string $channelCode, string $account): void
    {
        $isExists = UserChannelAccount::where('channel_code', $channelCode)
            ->where('account', $account)
            ->exists();

        abort_if($isExists, Response::HTTP_BAD_REQUEST, $account . ' 已存在');
    }

    public function resolveDevice(User $provider, string $deviceName): Device
    {
        $device = [
            'user_id' => $provider->getKey(),
            'name'    => $deviceName,
        ];

        Device::insertOrIgnore([$device]);

        return Device::where($device)->firstOrFail();
    }

    public function resolveWallet(User $provider)
    {
        $wallet = $provider->wallet;

        if ($provider->depositModeEnabled()) {
            $wallet = User::whereIsRoot()
                ->whereAncestorOrSelf($provider)
                ->firstOrFail()->wallet;
        }

        return $wallet;
    }

    public function resolveBankId(?string $bankName): int
    {
        if (!$bankName) {
            return 0;
        }

        $bank = Bank::firstWhere('name', $bankName);
        abort_if(!$bank, Response::HTTP_BAD_REQUEST, '銀行設定錯誤');

        return $bank->id;
    }

    public function processQrCode(?UploadedFile $file, User $provider): array
    {
        if (!$file) {
            return [];
        }

        $redirectUrl = $this->qrCodeService->decodeQrCode($file);
        $path = Storage::disk('user-channel-accounts-qr-code')->putFile(
            $this->qrCodeService->getQrCodeFileBasePath($provider),
            $file
        );
        $processedPath = $this->qrCodeService->saveProcessedQrCode($redirectUrl, $provider);

        return [
            'redirect_url'               => $redirectUrl,
            'processed_qr_code_file_path' => $processedPath,
            'qr_code_file_path'          => $path,
        ];
    }

    public function createAccount(array $data, User $provider): UserChannelAccount
    {
        $channelAmount = $this->validateChannelAmount($data['channel_amount_id']);
        $userChannel = $this->validateUserChannel($provider, $channelAmount);

        $account = $data['account'] ?? null;
        if (!empty($data['bank_card_number'])) {
            $account = $data['bank_card_number'];
        }

        if ($account) {
            $this->validateAccountUniqueness($channelAmount->channel_code, $account);
        }

        $deviceName = $data['device_name'] ?? $provider->name;
        $device = $this->resolveDevice($provider, $deviceName);
        $wallet = $this->resolveWallet($provider);
        $bankId = $this->resolveBankId($data['bank_name'] ?? null);

        $detail = $data['detail'] ?? [];

        $qrCodeFields = $this->processQrCode($data['qr_code_file'] ?? null, $provider);
        if ($qrCodeFields) {
            $detail = array_merge($detail, $qrCodeFields);
        }

        $status = $data['status'] ?? UserChannelAccount::STATUS_DISABLE;
        if (!empty($data['sync_after_create'])) {
            $status = UserChannelAccount::STATUS_DISABLE;
        }

        $userChannelAccount = null;

        DB::beginTransaction();
        try {
            $userChannelAccount = $channelAmount->userChannelAccounts()->create([
                'user_id'                   => $provider->getKey(),
                'device_id'                 => $device->getKey(),
                'wallet_id'                 => $wallet->getKey(),
                'bank_id'                   => $bankId,
                'channel_code'              => $channelAmount->channel_code,
                'status'                    => $status,
                'type'                      => $data['type'] ?? UserChannelAccount::TYPE_DEPOSIT_WITHDRAW,
                'fee_percent'               => $userChannel->fee_percent,
                'min_amount'                => $userChannel->min_amount,
                'max_amount'                => $userChannel->max_amount,
                'account'                   => $account,
                'detail'                    => $detail,
                'note'                      => $data['note'] ?? '',
                'balance'                   => $data['balance'] ?? 0,
                'balance_limit'             => $data['balance_limit'] ?? null,
                'is_auto'                   => $data['is_auto'] ?? true,
                'daily_status'              => UserChannelAccount::DAILY_STATUS_ENABLE,
                'daily_limit'               => $data['daily_limit'] ?? 0,
                'withdraw_daily_limit'      => $data['withdraw_daily_limit'] ?? null,
                'monthly_status'            => UserChannelAccount::MONTHLY_STATUS_ENABLE,
                'monthly_limit'             => $data['monthly_limit'] ?? 0,
                'withdraw_monthly_limit'    => $data['withdraw_monthly_limit'] ?? null,
                'single_min_limit'          => $data['single_min_limit'] ?? null,
                'single_max_limit'          => $data['single_max_limit'] ?? null,
                'withdraw_single_min_limit' => $data['withdraw_single_min_limit'] ?? null,
                'withdraw_single_max_limit' => $data['withdraw_single_max_limit'] ?? null,
                'address_type'              => $data['address_type'] ?? UserChannelAccount::ADDRESS_TYPE_MASTER,
                'parent_account_id'         => $data['parent_account_id'] ?? null,
            ]);

            $userChannelAccount->name = $data['name'] ?? Str::padLeft($userChannelAccount->id, 5, '0');
            $userChannelAccount->save();

            $this->syncTransactionGroups($userChannelAccount, $provider);

            DB::commit();

            if ($userChannelAccount->channel_code === Channel::CODE_USDT) {
                BackfillChainTransactions::dispatch($userChannelAccount->id);
            }
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            DB::rollBack();
            abort(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getMessage());
        }

        return $userChannelAccount;
    }

    public function createAccountInTransaction(array $data, User $provider): UserChannelAccount
    {
        $channelAmount = $this->validateChannelAmount($data['channel_amount_id']);
        $userChannel = $this->validateUserChannel($provider, $channelAmount);

        $device = $data['device'] ?? $provider->devices->first();
        $wallet = $data['wallet'] ?? $provider->wallet;
        $bankId = $data['bank_id'] ?? 0;

        $status = $data['status'] ?? UserChannelAccount::STATUS_DISABLE;
        if (!empty($data['sync_after_create'])) {
            $status = UserChannelAccount::STATUS_DISABLE;
        }

        $userChannelAccount = $channelAmount->userChannelAccounts()->create([
            'user_id'                   => $provider->getKey(),
            'device_id'                 => $device->getKey(),
            'wallet_id'                 => $wallet->getKey(),
            'bank_id'                   => $bankId,
            'channel_code'              => $channelAmount->channel_code,
            'status'                    => $status,
            'type'                      => $data['type'] ?? UserChannelAccount::TYPE_DEPOSIT_WITHDRAW,
            'fee_percent'               => $userChannel->fee_percent,
            'min_amount'                => $userChannel->min_amount,
            'max_amount'                => $userChannel->max_amount,
            'account'                   => $data['account'],
            'detail'                    => $data['detail'] ?? [],
            'note'                      => $data['note'] ?? '',
            'balance'                   => $data['balance'] ?? 0,
            'balance_limit'             => $data['balance_limit'] ?? null,
            'is_auto'                   => $data['is_auto'] ?? true,
            'daily_status'              => UserChannelAccount::DAILY_STATUS_ENABLE,
            'daily_limit'               => $data['daily_limit'] ?? 0,
            'withdraw_daily_limit'      => $data['withdraw_daily_limit'] ?? null,
            'monthly_status'            => UserChannelAccount::MONTHLY_STATUS_ENABLE,
            'monthly_limit'             => $data['monthly_limit'] ?? 0,
            'withdraw_monthly_limit'    => $data['withdraw_monthly_limit'] ?? null,
        ]);

        $userChannelAccount->name = $data['name'] ?? Str::padLeft($userChannelAccount->id, 5, '0');
        $userChannelAccount->save();

        $this->syncTransactionGroups($userChannelAccount, $provider);

        if ($userChannelAccount->channel_code === Channel::CODE_USDT) {
            BackfillChainTransactions::dispatch($userChannelAccount->id)->afterCommit();
        }

        return $userChannelAccount;
    }

    /**
     * 從母地址自動衍生子地址
     */
    public function createChildAccount(UserChannelAccount $parentAccount, ?int $derivationIndex = null): UserChannelAccount
    {
        $encryptedKey = data_get($parentAccount->detail, UserChannelAccount::DETAIL_KEY_ENCRYPTED_PRIVATE_KEY);
        abort_if(!$encryptedKey, Response::HTTP_BAD_REQUEST, '母地址未設定私鑰，無法自動衍生');

        if ($derivationIndex === null) {
            $derivationIndex = ($parentAccount->childAccounts()->max('derivation_index') ?? -1) + 1;
        }

        // 檢查 derivation_index 唯一性
        $exists = UserChannelAccount::where('parent_account_id', $parentAccount->id)
            ->where('derivation_index', $derivationIndex)
            ->exists();
        abort_if($exists, Response::HTTP_BAD_REQUEST, "derivation_index {$derivationIndex} 已存在");

        $chainNetwork = data_get($parentAccount->detail, UserChannelAccount::DETAIL_KEY_CHAIN_NETWORK, 'trc20');
        $masterKey = decrypt($encryptedKey);
        $child = match ($chainNetwork) {
            'trc20' => $this->tronAddressService->deriveChildAccount($masterKey, $derivationIndex),
            'erc20', 'bep20' => $this->ethAddressService->deriveChildAccount($masterKey, $derivationIndex),
            default => throw new \InvalidArgumentException("不支援的鏈網路: {$chainNetwork}"),
        };
        $masterKey = null;

        $this->validateAccountUniqueness(Channel::CODE_USDT, $child['address']);

        $detail = [
            UserChannelAccount::DETAIL_KEY_CHAIN_NETWORK => data_get(
                $parentAccount->detail,
                UserChannelAccount::DETAIL_KEY_CHAIN_NETWORK,
                'trc20'
            ),
            UserChannelAccount::DETAIL_KEY_ENCRYPTED_PRIVATE_KEY => encrypt($child['private_key']),
        ];
        $child['private_key'] = null;

        $provider = User::findOrFail($parentAccount->user_id);
        $channelAmount = $parentAccount->channelAmount;
        $userChannel = $this->validateUserChannel($provider, $channelAmount);
        $device = $this->resolveDevice($provider, $provider->name);
        $wallet = $this->resolveWallet($provider);

        $childAccount = $channelAmount->userChannelAccounts()->create([
            'user_id' => $provider->getKey(),
            'device_id' => $device->getKey(),
            'wallet_id' => $wallet->getKey(),
            'bank_id' => 0,
            'channel_code' => Channel::CODE_USDT,
            'status' => UserChannelAccount::STATUS_DISABLE,
            'type' => $parentAccount->type,
            'fee_percent' => $userChannel->fee_percent,
            'min_amount' => $userChannel->min_amount,
            'max_amount' => $userChannel->max_amount,
            'account' => $child['address'],
            'detail' => $detail,
            'address_type' => UserChannelAccount::ADDRESS_TYPE_CHILD,
            'parent_account_id' => $parentAccount->id,
            'derivation_index' => $derivationIndex,
            'is_auto' => true,
        ]);

        $childAccount->name = Str::padLeft($childAccount->id, 5, '0');
        $childAccount->save();

        $this->syncTransactionGroups($childAccount, $provider);

        if ($childAccount->channel_code === Channel::CODE_USDT) {
            BackfillChainTransactions::dispatch($childAccount->id);
        }

        return $childAccount;
    }

    /**
     * 批量衍生子地址
     */
    public function batchCreateChildAccounts(UserChannelAccount $parentAccount, int $count): array
    {
        $created = [];
        $startIndex = ($parentAccount->childAccounts()->max('derivation_index') ?? -1) + 1;

        for ($i = 0; $i < $count; $i++) {
            $created[] = $this->createChildAccount($parentAccount, $startIndex + $i);
        }

        return $created;
    }

    private function syncTransactionGroups(UserChannelAccount $userChannelAccount, User $provider): void
    {
        $transactionGroups = TransactionGroup::where(
            'transaction_type',
            Transaction::TYPE_PAUFEN_TRANSACTION
        )
            ->whereHas('worker', function (Builder $users) use ($provider) {
                $users->whereAncestorOrSelf($provider);
            })
            ->pluck('id');

        $userChannelAccount->transactionGroups()->syncWithoutDetaching($transactionGroups);
    }
}
