<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserChannelAccountCollection;
use App\Console\Commands\DisableTimeLimitUserChannelAccount;
use App\Model\Channel;
use App\Model\ChannelAmount;
use App\Model\Device;
use App\Model\Transaction;
use App\Model\TransactionGroup;
use App\Model\User;
use App\Model\UserChannel;
use App\Model\UserChannelAccount;
use App\Model\Bank;
use App\Model\FeatureToggle;
use App\Utils\GuzzleHttpClientTrait;
use App\Utils\AmountDisplayTransformer;
use App\Repository\FeatureToggleRepository;
use Endroid\QrCode\Builder\Builder as QrBuilder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use Exception;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\RequestOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Zxing\QrReader;
use Hashids\Hashids;

class UserChannelAccountController extends Controller
{

    use GuzzleHttpClientTrait;

    public function destroy(UserChannelAccount $userChannelAccount)
    {
        abort_if(
            $userChannelAccount->user->role !== User::ROLE_PROVIDER
                || ($userChannelAccount->user_id !== auth()->user()->getKey()
                    && $userChannelAccount->user->parent_id !== auth()->user()->getKey()),
            Response::HTTP_NOT_FOUND
        );

        DB::transaction(function () use ($userChannelAccount) {
            $userChannelAccount->update(['status' => UserChannelAccount::STATUS_DISABLE]);

            $userChannelAccount->transactionGroups()->detach();

            abort_if(
                !$userChannelAccount->delete(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        });

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function index(Request $request, FeatureToggleRepository $featureToggleRepository)
    {
        $this->validate($request, [
            'channel_code' => 'array',
            'status'       => 'array',
            'device_name'  => 'nullable|string',
            'no_paginate' => 'nullable|boolean'
        ]);

        /** @var Builder $userChannelAccounts */
        $userChannelAccounts = UserChannelAccount::with('channelAmount.channel', 'bank')->latest('created_at')->latest('id');

        $userChannelAccounts->when(empty($request->provider), function ($builder) {
            $builder->where('user_id', auth()->user()->getKey());
        });

        $userChannelAccounts->when(!empty($request->provider), function ($builder) use ($request) {
            $provider = $request->provider;
            $builder->whereIn('user_id', function ($query) use ($provider) {
                $query->select('id')
                    ->from('users')
                    ->where(function ($q) {
                        $q->orWhere('id', auth()->user()->getKey())
                            ->orWhere('parent_id', auth()->user()->getKey());
                    })
                    ->where(function ($q) use ($provider) {
                        $q->orWhere('name', $provider)
                            ->orWhere('username', $provider);
                    });
                error_log(json_encode($query->get()));
            });
        });

        $userChannelAccounts->when(!empty($request->channel_code), function ($builder) use ($request) {
            $builder->whereHas('channelAmount', function ($builder) use ($request) {
                $builder->whereIn('channel_code', $request->channel_code);
            });
        });

        $userChannelAccounts->when(!empty($request->status), function ($builder) use ($request) {
            $builder->whereIn('status', $request->status);
        });

        $userChannelAccounts->when(!empty($request->type), function ($builder) use ($request) {
            $builder->whereIn('type', $request->type);
        });

        $userChannelAccounts->when($request->has('is_auto'), function ($builder) use ($request) {
            $builder->where('is_auto', $request->is_auto);
        });

        $userChannelAccounts->when(!is_null($request->device_name), function ($builder) use ($request) {
            $builder->whereHas('device', function ($devices) use ($request) {
                $devices->where('name', 'like', "%{$request->device_name}%");
            });
        });

        $userChannelAccounts->when(!empty($request->bank_id), function ($builder) use ($request) {
            $builder->whereIn('bank_id', $request->bank_id);
        });

        $userChannelAccounts->when(!empty($request->provider_channel_account_hash_id), function ($builder) use ($request) {
            $builder->whereIn('name', $request->provider_channel_account_hash_id);
        });

        $userChannelAccounts->when(!empty($request->note), function ($builder) use ($request) {
            $builder->where("note", "like", "%$request->note%");
        });

        $dailyLimitId       = FeatureToggle::USER_CHANNEL_ACCOUNT_DAILY_LIMIT;
        $dailyLimitEnabled  = $featureToggleRepository->enabled($dailyLimitId);
        $dailyLimitvalue    = $featureToggleRepository->valueOf($dailyLimitId);

        $monthlyLimitId       = FeatureToggle::USER_CHANNEL_ACCOUNT_MONTHLY_LIMIT;
        $monthlyLimitEnabled  = $featureToggleRepository->enabled($monthlyLimitId);
        $monthlyLimitvalue    = $featureToggleRepository->valueOf($monthlyLimitId);

        $totalBalance = (clone $userChannelAccounts)->first([
            DB::raw('SUM(balance) AS total_balance')
        ]);

        $data = !empty($request->no_paginate) ? $userChannelAccounts->get() : $userChannelAccounts->paginate(20)->appends($request->query->all());
        $data->transform(function ($value) use ($dailyLimitEnabled, $dailyLimitvalue, $monthlyLimitEnabled, $monthlyLimitvalue) {
            $value->user_channel_account_daily_limit_enabled = $dailyLimitEnabled;
            $value->user_channel_account_daily_limit_value = $dailyLimitvalue;
            $value->user_channel_account_monthly_limit_enabled = $monthlyLimitEnabled;
            $value->user_channel_account_monthly_limit_value = $monthlyLimitvalue;

            return $value;
        });

        return UserChannelAccountCollection::make($data)->additional([
            'meta' => [
                'user_channel_account_daily_limit_enabled' => $dailyLimitEnabled,
                'user_channel_account_daily_limit_value' => $dailyLimitvalue,
                'user_channel_account_monthly_limit_enabled' => $monthlyLimitEnabled,
                'user_channel_account_monthly_limit_value' => $monthlyLimitvalue,
                'record_user_channeL_account_balance' => $featureToggleRepository->enabled(FeatureToggle::RECORD_USER_CHANNEL_ACCOUNT_BALANCE),
                'total_balance' => data_get($totalBalance, 'total_balance', '0.00')
            ]
        ]);
    }

    public function show(FeatureToggleRepository $featureToggleRepository, UserChannelAccount $userChannelAccount)
    {
        abort_if(
            $userChannelAccount->user->role !== User::ROLE_PROVIDER
                || ($userChannelAccount->user_id !== auth()->user()->getKey()
                    && $userChannelAccount->user->parent_id !== auth()->user()->getKey()),
            Response::HTTP_NOT_FOUND
        );

        $userChannelAccount->user_channel_account_daily_limit_enabled = $featureToggleRepository->enabled(FeatureToggle::USER_CHANNEL_ACCOUNT_DAILY_LIMIT);
        $userChannelAccount->user_channel_account_daily_limit_value = $featureToggleRepository->valueOf(FeatureToggle::USER_CHANNEL_ACCOUNT_DAILY_LIMIT);

        $userChannelAccount->user_channel_account_monthly_limit_enabled = $featureToggleRepository->enabled(FeatureToggle::USER_CHANNEL_ACCOUNT_MONTHLY_LIMIT);
        $userChannelAccount->user_channel_account_monthly_limit_value = $featureToggleRepository->valueOf(FeatureToggle::USER_CHANNEL_ACCOUNT_MONTHLY_LIMIT);
        $userChannelAccount->record_user_channeL_account_balance = $featureToggleRepository->enabled(FeatureToggle::RECORD_USER_CHANNEL_ACCOUNT_BALANCE);

        return \App\Http\Resources\UserChannelAccount::make($userChannelAccount->load('user.parent', 'channelAmount.channel'));
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'channel_amount_id' => 'required|int',
            'device_name'       => 'required',
        ]);

        /** @var ChannelAmount $channelAmount */
        $channelAmount = ChannelAmount::find($request->channel_amount_id);

        abort_if(!$channelAmount, Response::HTTP_BAD_REQUEST, __('channel.User channel not found'));

        /** @var UserChannel $userChannel */
        $userChannel = UserChannel::where([
            'user_id'          => auth()->user()->getKey(),
            'channel_group_id' => $channelAmount->channel_group_id,
        ])->first();

        abort_if(!$userChannel, Response::HTTP_BAD_REQUEST, __('channel.User channel not found'));

        abort_if(is_null($userChannel->fee_percent), Response::HTTP_BAD_REQUEST, '通道费率未设定');

        $device = ['user_id' => auth()->user()->getKey(), 'name' => $request->device_name];

        Device::insertIgnore($device);

        $device = Device::where($device)->firstOrFail();

        $userChannelAccount = null;

        $wallet = auth()->user()->wallet;

        if (auth()->user()->depositModeEnabled()) {
            $wallet = User::whereIsRoot()->whereAncestorOrSelf(auth()->user())->firstOrFail()->wallet;
        }

        $detail = $request->only('account', 'bank_name', 'bank_card_number', 'bank_card_holder_name', 'bank_card_branch', 'mpin', 'mobile', 'receiver_name', 'pin', 'otp', 'pwd');

        if ($request->has('qr_code')) {
            $file = $request->file('qr_code');
            $redirectUrl = $this->decodeQrCode($file);
            $path = Storage::disk('user-channel-accounts-qr-code')->putFile($this->getQrCodeFileBasePath(), $file);
            $processedPath = $this->saveProcessedQrCode($redirectUrl);

            // 新增 qrcode 在用的 field
            $detail['redirect_url'] = $redirectUrl;
            $detail['processed_qr_code_file_path'] = $processedPath;
            $detail['qr_code_file_path'] = $path;
        }

        $account = $request->account;
        if ($request->has('bank_card_number')) {
            $account = $request->bank_card_number;
        }

        $bankId = 0;
        if ($request->has('bank_name')) {
            $bank = Bank::firstWhere('name', $request->bank_name);
            abort_if(!$bank, Response::HTTP_BAD_REQUEST, '銀行設定錯誤');
            $bankId = $bank->id;
        }


        DB::beginTransaction();
        try {
            $userChannelAccount = $channelAmount->userChannelAccounts()->create([
                'user_id'      => auth()->user()->getKey(),
                'device_id'    => $device->getKey(),
                'wallet_id'    => $wallet->getKey(),
                'bank_id'      => $bankId,
                'channel_code' => $channelAmount->channel_code,
                'status'       => UserChannelAccount::STATUS_DISABLE,
                'type'         => $request->input('type', UserChannelAccount::TYPE_DEPOSIT_WITHDRAW),
                'fee_percent'  => $userChannel->fee_percent,
                'min_amount'   => $userChannel->min_amount,
                'max_amount'   => $userChannel->max_amount,
                'account'      => $account,
                'detail'       => $detail,
                'balance'      => $request->input('balance'),
                'balance_limit' => $request->input('balance_limit'),
                "note" => $request->input("note") ?? "",
                'is_auto' => $request->input('is_auto', false),
                'daily_status' => UserChannelAccount::DAILY_STATUS_ENABLE,
                'monthly_status' => UserChannelAccount::MONTHLY_STATUS_ENABLE
            ]);
            $userChannelAccount->name = $request->input('name', Str::padLeft($userChannelAccount->id, 5, '0'));
            $userChannelAccount->save();

            $transactionGroups = TransactionGroup::where('transaction_type', Transaction::TYPE_PAUFEN_TRANSACTION)
                ->whereHas('worker', function (Builder $users) {
                    $users->whereAncestorOrSelf(auth()->user());
                })
                ->pluck('id');
            $userChannelAccount->transactionGroups()->syncWithoutDetaching($transactionGroups);
            DB::commit();
        } catch (exception $e) {
            DB::rollBack();
        }

        abort_if(!$userChannelAccount, Response::HTTP_INTERNAL_SERVER_ERROR);

        Artisan::call('paufen:disable-time-limit-user-channel-account', [
            'user_channel_account' => $userChannelAccount
        ]);

        return \App\Http\Resources\UserChannelAccount::make($userChannelAccount);
    }

    private function decodeQrCode(UploadedFile $file)
    {
        try {
            $qrCodeText = '';

            try {
                $qrCodeText = trim((new QrReader($file))->text());
            } catch (Exception $exception) {
                return abort(Response::HTTP_BAD_REQUEST, __('common.Invalid qr-code'));
            }

            if (!empty($qrCodeText)) {
                return $qrCodeText;
            }

            $response = $this->makeClient()
                ->post('http://api.qrserver.com/v1/read-qr-code/', [
                    RequestOptions::MULTIPART => [
                        [
                            'name'     => 'file',
                            'contents' => fopen($file->path(), 'r'),
                        ],
                        [
                            'name'     => 'MAX_FILE_SIZE',
                            'contents' => $file->getSize(),
                        ]
                    ],
                ]);

            $responseData = json_decode($response->getBody()->getContents());

            $qrCodeText = trim(data_get($responseData, '0.symbol.0.data'));

            abort_if(
                empty($qrCodeText),
                Response::HTTP_BAD_REQUEST,
                __('common.Invalid qr-code')
            );

            return $qrCodeText;
        } catch (TransferException $transferException) {
            return abort(Response::HTTP_BAD_REQUEST, __('common.Invalid qr-code'));
        }
    }

    private function getQrCodeFileBasePath()
    {
        $userId = auth()->user()->getKey();

        return "users/$userId";
    }

    private function saveProcessedQrCode($redirectUrl)
    {
        $qrcode = QrBuilder::create()
            ->writer(new PngWriter())
            ->data($redirectUrl)
            ->encoding(new Encoding('UTF-8'))
            ->margin(10)
            ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
            ->size(500)
            ->build();

        $basePath = $this->getQrCodeFileBasePath();
        $fileHashName = Str::random(40);

        Storage::disk('user-channel-accounts-qr-code')->put(
            $path = trim("$basePath/$fileHashName", '/') . '.png',
            $qrcode->getString()
        );

        return $path;
    }

    private function bankCode($bankCardNumber)
    {
        $fullCardNumberCacheKey = "bank_card_number_to_bank_code_$bankCardNumber";

        $binCodeOfBankCardNumber = Str::substr($bankCardNumber, 0, 6);
        $binCodeCacheKey = "bank_card_number_to_bank_code_$binCodeOfBankCardNumber";

        // 若 BinCode 快取命中，代表曾經有正確的查詢結果，就不需要再發送一次 API，避免被 Alipay 禁止
        if ($cachedBankName = Cache::get($binCodeCacheKey)) {
            return $cachedBankName;
        }

        // 有整張卡號快取的記錄，代表該卡號已經查過，且不是正確的卡號，避免重複發送到 Alipay
        // 這邊用 Has 的原因是 cache 內容是空字串，會直接被當 falsy 判掉
        if (Cache::has($fullCardNumberCacheKey)) {
            return Cache::get($fullCardNumberCacheKey);
        }

        // 沒有命中時，發送 Alipay API 查詢
        try {
            $response = $this->makeClient()
                ->get('https://ccdcapi.alipay.com/validateAndCacheCardInfo.json?' . http_build_query([
                    '_input_charset' => 'utf-8',
                    'cardNo'         => $bankCardNumber,
                    'cardBinCheck'   => 'true',
                ]));

            $responseData = json_decode($response->getBody()->getContents());

            $bankCode = data_get($responseData, 'bank');

            // 只有 API 查詢結果正確的狀況下，再做 Cache
            if (!empty($bankCode)) {
                Cache::put($binCodeCacheKey, $bankCode, now()->addDay());

                return $bankCode;
            }
        } catch (TransferException $transferException) {
            Log::debug($transferException->getMessage(), [self::class, $bankCardNumber]);

            // 如果是連線問題，不要快取該卡號
            return '';
        }

        Cache::put($fullCardNumberCacheKey, '', now()->addDay());

        return '';
    }

    public function update(Request $request, UserChannelAccount $userChannelAccount)
    {
        abort_if(
            $userChannelAccount->user->role !== User::ROLE_PROVIDER
                || ($userChannelAccount->user_id !== auth()->user()->getKey()
                    && $userChannelAccount->user->parent_id !== auth()->user()->getKey()),
            Response::HTTP_NOT_FOUND
        );

        DB::transaction(function () use ($request, $userChannelAccount) {
            $this->updateUserChannelAccount($request, $userChannelAccount);
        });

        return \App\Http\Resources\UserChannelAccount::make($userChannelAccount->load(
            'user.parent',
            'channelAmount.channel'
        ));
    }

    private function updateUserChannelAccount(Request $request, UserChannelAccount $userChannelAccount)
    {
        $updateData = [];

        if ($request->has('status')) {
            $this->validate($request, [
                'status' => ['integer', Rule::in(UserChannelAccount::STATUS_ENABLE, UserChannelAccount::STATUS_ONLINE)],
            ]);

            $this->abortIfStatusUpdateIsInvalid($userChannelAccount, $request->status);

            $updateData['status'] = $request->status;
        }


        if ($request->has('device_name')) {
            $device = Device::firstOrCreate([
                'user_id' => auth()->user()->getKey(),
                'name' => $request->device_name
            ]);

            $updateData['device_id'] = $device->id;
        }

        if ($request->has('name')) {
            $updateData['name'] = $request->name;
        }

        if ($request->has('type')) {
            $updateData['type'] = $request->type;
        }

        if ($request->has("note")) {
            $updateData['note'] = $request->note;
        }

        if ($updateData) {
            $userChannelAccount->update($updateData);
        }

        return $userChannelAccount;
    }

    private function abortIfStatusUpdateIsInvalid(UserChannelAccount $userChannelAccount, $toStatus)
    {
        // 出款帳不檢查
        if ($userChannelAccount->type == UserChannelAccount::TYPE_WITHDRAW) {
            return;
        }

        /** @var UserChannel $userChannel */
        $userChannel = UserChannel::where([
            'user_id'          => $userChannelAccount->user_id,
            'channel_group_id' => $userChannelAccount->channelAmount->channel_group_id,
        ])->firstOrFail();

        abort_if(
            $toStatus == UserChannelAccount::STATUS_ONLINE
                && $userChannel->status !== UserChannel::STATUS_ENABLED,
            Response::HTTP_BAD_REQUEST,
            __('channel.Channel disabled')
        );

        // enable -> online
        // online -> 下線 -> enable
        $fromStatus = $userChannelAccount->status;
        abort_if(
            !is_int($fromStatus) || !is_int($toStatus),
            Response::HTTP_BAD_REQUEST
        );
    }

    public function dailyLimitUpdate(Request $request, UserChannelAccount $userChannelAccount, $id)
    {
        $this->validate($request, [
            'daily_limit'  => ['numeric'],
        ]);

        $userChannelAccount = $userChannelAccount->find($id);

        abort_if(
            $userChannelAccount->user->role !== User::ROLE_PROVIDER
                || ($userChannelAccount->user_id !== auth()->user()->getKey()
                    && $userChannelAccount->user->parent_id !== auth()->user()->getKey()),
            Response::HTTP_NOT_FOUND
        );

        DB::transaction(function () use ($request, $userChannelAccount) {
            $userChannelAccount->update(['daily_limit' => $request->daily_limit]);
        });

        return \App\Http\Resources\UserChannelAccount::make($userChannelAccount->load(
            'user.parent',
            'channelAmount.channel'
        ));
    }

    public function monthlyLimitUpdate(Request $request, UserChannelAccount $userChannelAccount, $id)
    {
        $this->validate($request, [
            'monthly_limit'  => ['numeric'],
        ]);

        $userChannelAccount = $userChannelAccount->find($id);

        abort_if(
            $userChannelAccount->user->role !== User::ROLE_PROVIDER
                || ($userChannelAccount->user_id !== auth()->user()->getKey()
                    && $userChannelAccount->user->parent_id !== auth()->user()->getKey()),
            Response::HTTP_NOT_FOUND
        );

        DB::transaction(function () use ($request, $userChannelAccount) {
            $userChannelAccount->update(['monthly_limit' => $request->monthly_limit]);
        });

        return \App\Http\Resources\UserChannelAccount::make($userChannelAccount->load(
            'user.parent',
            'channelAmount.channel'
        ));
    }

    public function balanceUpdate(Request $request, UserChannelAccount $userChannelAccount, $id)
    {
        $this->validate($request, [
            'balance'  => ['numeric'],
        ]);

        $userChannelAccount = $userChannelAccount->find($id);

        abort_if(
            $userChannelAccount->user->role !== User::ROLE_PROVIDER
                || ($userChannelAccount->user_id !== auth()->user()->getKey()
                    && $userChannelAccount->user->parent_id !== auth()->user()->getKey()),
            Response::HTTP_NOT_FOUND
        );

        DB::transaction(function () use ($request, $userChannelAccount) {
            $userChannelAccount->update(['balance' => $request->balance]);
        });

        return \App\Http\Resources\UserChannelAccount::make($userChannelAccount->load(
            'user.parent',
            'channelAmount.channel'
        ));
    }

    public function branchUpdate(Request $request, UserChannelAccount $userChannelAccount, $id)
    {
        $userChannelAccount = $userChannelAccount->find($id);

        abort_if(
            $userChannelAccount->user->role !== User::ROLE_PROVIDER
                || ($userChannelAccount->user_id !== auth()->user()->getKey()
                    && $userChannelAccount->user->parent_id !== auth()->user()->getKey()),
            Response::HTTP_NOT_FOUND
        );


        $arr = $userChannelAccount->detail;
        $arr['bank_card_branch'] = $request->bank_card_branch;



        DB::transaction(function () use ($request, $userChannelAccount, $arr) {
            $userChannelAccount->update(['detail' => $arr]);
        });

        return \App\Http\Resources\UserChannelAccount::make($userChannelAccount->load(
            'user.parent',
            'channelAmount.channel'
        ));
    }

    public function bankIdUpdate(Request $request, UserChannelAccount $userChannelAccount, $id)
    {
        $this->validate($request, [
            'bank_id'  => ['numeric'],
        ]);

        $userChannelAccount = $userChannelAccount->find($id);

        abort_if(
            $userChannelAccount->user->role !== User::ROLE_PROVIDER
                || ($userChannelAccount->user_id !== auth()->user()->getKey()
                    && $userChannelAccount->user->parent_id !== auth()->user()->getKey())
                || !empty($userChannelAccount->bank_id),
            Response::HTTP_NOT_FOUND
        );

        $bankData = Bank::find($request->bank_id);
        abort_if(empty($bankData), Response::HTTP_BAD_REQUEST, '銀行設定錯誤');

        DB::transaction(function () use ($bankData, $userChannelAccount) {
            $userChannelAccount->update(['bank_id' => $bankData->id]);
        });

        return \App\Http\Resources\UserChannelAccount::make($userChannelAccount->load(
            'user.parent',
            'channelAmount.channel'
        ));
    }

    public function createWithdrawAccount(Request $request)
    {
        $this->validate($request, [
            'device_name' => 'required',
            'channel_code' => 'required',
        ]);

        $device = ['user_id' => auth()->user()->getKey(), 'name' => $request->device_name];
        Device::insertIgnore($device);
        $device = Device::where($device)->firstOrFail();

        $userChannelAccount = null;

        $wallet = auth()->user()->wallet;

        if (auth()->user()->depositModeEnabled()) {
            $wallet = User::whereIsRoot()->whereAncestorOrSelf(auth()->user())->firstOrFail()->wallet;
        }

        $bankId = 0;
        if ($request->has('bank_name')) {
            $bank = Bank::firstWhere('name', $request->bank_name);
            abort_if(!$bank, Response::HTTP_BAD_REQUEST, '銀行設定錯誤');
            $bankId = $bank->id;
        }

        $account = $request->account;
        if ($request->has('bank_card_number')) {
            $account = $request->bank_card_number;
        }

        $detail = $request->only('account', 'bank_name', 'bank_card_number', 'bank_card_holder_name', 'bank_card_branch', 'mpin', 'mobile', 'receiver_name', 'pin', 'otp', 'pwd');

        $userChannelAccount = UserChannelAccount::create([
            'user_id'      => auth()->user()->getKey(),
            'channel_code' => $request->channel_code,
            'device_id'    => $device->getKey(),
            'wallet_id'    => $wallet->getKey(),
            'bank_id'      => $bankId,
            'status'       => UserChannelAccount::STATUS_DISABLE,
            'type'         => UserChannelAccount::TYPE_WITHDRAW,
            'account'      => $account,
            'detail'       => $detail,
            'balance'      => $request->input('balance'),
            'balance_limit' => $request->balance_limit,
            'is_auto' => $request->is_auto
        ]);

        abort_if(!$userChannelAccount, Response::HTTP_INTERNAL_SERVER_ERROR);

        return \App\Http\Resources\UserChannelAccount::make($userChannelAccount);
    }
}
