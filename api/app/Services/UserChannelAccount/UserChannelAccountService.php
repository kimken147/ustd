<?php

namespace App\Services\UserChannelAccount;

use App\Jobs\SyncGcashAccount;
use App\Models\Bank;
use App\Models\ChannelAmount;
use App\Models\Device;
use App\Models\Transaction;
use App\Models\TransactionGroup;
use App\Models\User;
use App\Models\UserChannel;
use App\Models\UserChannelAccount;
use App\Services\QrCodeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserChannelAccountService
{
    public function __construct(
        private readonly QrCodeService $qrCodeService
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

        Device::insertIgnore($device);

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
                'is_auto'                   => $data['is_auto'] ?? false,
                'daily_status'              => UserChannelAccount::DAILY_STATUS_ENABLE,
                'daily_limit'               => $data['daily_limit'] ?? null,
                'withdraw_daily_limit'      => $data['withdraw_daily_limit'] ?? null,
                'monthly_status'            => UserChannelAccount::MONTHLY_STATUS_ENABLE,
                'monthly_limit'             => $data['monthly_limit'] ?? null,
                'withdraw_monthly_limit'    => $data['withdraw_monthly_limit'] ?? null,
                'single_min_limit'          => $data['single_min_limit'] ?? null,
                'single_max_limit'          => $data['single_max_limit'] ?? null,
                'withdraw_single_min_limit' => $data['withdraw_single_min_limit'] ?? null,
                'withdraw_single_max_limit' => $data['withdraw_single_max_limit'] ?? null,
            ]);

            $userChannelAccount->name = $data['name'] ?? Str::padLeft($userChannelAccount->id, 5, '0');
            $userChannelAccount->save();

            $this->syncTransactionGroups($userChannelAccount, $provider);

            DB::commit();

            if (!empty($data['sync_after_create'])) {
                SyncGcashAccount::dispatch($userChannelAccount->id, 'init');
            }
        } catch (\Exception $e) {
            DB::rollBack();
        }

        abort_if(!$userChannelAccount, Response::HTTP_INTERNAL_SERVER_ERROR);

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
            'is_auto'                   => $data['is_auto'] ?? false,
            'daily_status'              => UserChannelAccount::DAILY_STATUS_ENABLE,
            'daily_limit'               => $data['daily_limit'] ?? null,
            'withdraw_daily_limit'      => $data['withdraw_daily_limit'] ?? null,
            'monthly_status'            => UserChannelAccount::MONTHLY_STATUS_ENABLE,
            'monthly_limit'             => $data['monthly_limit'] ?? null,
            'withdraw_monthly_limit'    => $data['withdraw_monthly_limit'] ?? null,
        ]);

        $userChannelAccount->name = $data['name'] ?? Str::padLeft($userChannelAccount->id, 5, '0');
        $userChannelAccount->save();

        $this->syncTransactionGroups($userChannelAccount, $provider);

        if (!empty($data['sync_after_create'])) {
            SyncGcashAccount::dispatch($userChannelAccount->id, 'init');
        }

        return $userChannelAccount;
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
