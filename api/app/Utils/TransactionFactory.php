<?php

namespace App\Utils;

use App\Exceptions\RaceConditionException;
use App\Models\Bank;
use App\Models\Channel;
use App\Models\DevicePayingTransaction;
use App\Models\FeatureToggle;
use App\Models\Transaction;
use App\Models\TransactionNote;
use App\Models\User;
use App\Models\UserChannelAccount;
use App\Repository\FeatureToggleRepository;
use App\Services\Transaction\TransactionFeeService;
use App\Utils\BCMathUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class TransactionFactory
{
    public $amount;
    public $bankCard;
    private $bcMath;
    public $clientIpv4;
    public $floatingAmount;
    public $note;
    public $notifyUrl;
    public $orderNumber;
    public $parent;
    private $realName;
    private $subType;
    private $usdtRate;
    private $binanceUsdtRate;
    private $toData = [];
    private $userChannelAccountUtil;
    private $featureToggleRepository;
    private $transactionFeeService;

    public function __construct(
        BCMathUtil $bcMath,
        UserChannelAccountUtil $userChannelAccountUtil,
        FeatureToggleRepository $featureToggleRepository,
        TransactionFeeService $transactionFeeService
    ) {
        $this->bcMath = $bcMath;
        $this->userChannelAccountUtil = $userChannelAccountUtil;
        $this->featureToggleRepository = $featureToggleRepository;
        $this->transactionFeeService = $transactionFeeService;
    }

    public function usdtRate($usdtRate, $binanceUsdtRate)
    {
        $this->usdtRate = $usdtRate;
        $this->binanceUsdtRate = $binanceUsdtRate;

        return $this;
    }

    public function amount($amount)
    {
        $this->amount = $amount;

        return $this;
    }

    public function bankCard(BankCardTransferObject $bankCardTransferObject)
    {
        $this->bankCard = $bankCardTransferObject;

        return $this;
    }

    public function clientIpv4(?string $clientIpv4)
    {
        $this->clientIpv4 = $clientIpv4;

        return $this;
    }

    public function floatingAmount($floatingAmount)
    {
        $this->floatingAmount = $floatingAmount;

        return $this;
    }

    public function fresh()
    {
        return new self(
            $this->bcMath,
            $this->userChannelAccountUtil,
            $this->featureToggleRepository,
            $this->transactionFeeService
        );
    }

    public function toData($data)
    {
        $this->toData = array_merge($this->toData, array_filter($data)); // 过滤掉空资料

        return $this;
    }

    public function normalDepositTo(User $provider)
    {
        $this->throwIfAnyMissing(["amount", "bankCard"]);

        try {
            DB::beginTransaction();

            $transaction = Transaction::create([
                "from_id" => 0,
                "to_id" => $provider->getKey(),
                "to_wallet_id" => $provider->wallet->getKey(),
                "locked_by_id" => null,
                "client_ipv4" => $this->clientIpv4,
                "type" => Transaction::TYPE_NORMAL_DEPOSIT,
                "status" => Transaction::STATUS_PAYING,
                "notify_status" => Transaction::NOTIFY_STATUS_NONE,
                "from_account_mode" => null,
                "to_account_mode" => $provider->account_mode,
                "from_channel_account" => $this->bankCard->toFromChannelAccount(),
                "to_channel_account" => $this->toData ?? [],
                "amount" => $this->amount,
                "floating_amount" => $this->amount,
                "actual_amount" => 0,
                "channel_code" => null,
                "order_number" => $this->orderNumber,
                "note" => $this->note,
                "notify_url" => $this->notifyUrl,
                "usdt_rate" => $this->usdtRate ?? 0,
                "from_device_name" => null,
                "certificate_file_path" => null,
                "notified_at" => null,
                "matched_at" => null,
                "confirmed_at" => null,
                "locked_at" => null,
            ]);

            if ($this->note) {
                TransactionNote::create([
                    "transaction_id" => $transaction->getKey(),
                    "user_id" => $provider->realUser()->getKey(),
                    "note" => $this->note,
                ]);
            }

            $this->transactionFeeService->createDepositFees($transaction, $provider);

            DB::commit();
        } catch (RuntimeException $e) {
            DB::rollback();

            return null;
        }

        return $transaction;
    }

    /**
     * @param  array  $attributes
     * @throws Throwable
     */
    private function throwIfAnyMissing(array $attributes)
    {
        foreach ($attributes as $attribute) {
            if ($attribute === "bankCard") {
                if (
                    is_null($this->bankCard) ||
                    !($this->bankCard instanceof BankCardTransferObject)
                ) {
                    throw new RuntimeException("bankCard can not be empty");
                }

                foreach ($this->bankCard as $key => $bankCardProperty) {
                    if (in_array($key, ["bankProvince", "bankCity"])) {
                        // 开户省份 开户市不检查
                        continue;
                    }
                    throw_if(
                        is_null($bankCardProperty),
                        new RuntimeException($attribute . " can not be empty")
                    );
                }
            } else {
                throw_if(
                    is_null($this->$attribute),
                    new RuntimeException($attribute . " can not be empty")
                );
            }
        }
    }

    /** @deprecated Use TransactionFeeService::createDepositFees() */
    public function createDepositFees(...$args)
    {
        return $this->transactionFeeService->createDepositFees(...$args);
    }

    public function normalWithdrawFrom(
        User $user,
        $agency = false,
        ?Transaction $parent = null,
        $type = "balance"
    ) {
        $this->throwIfAnyMissing(["amount", "bankCard"]);

        try {
            DB::beginTransaction();

            $transaction = Transaction::create([
                "parent_id" => optional($this->parent)->getKey(),
                "from_id" => $user->getKey(),
                "from_wallet_id" => $user->wallet->getKey(),
                "to_id" => 0,
                "locked_by_id" => null,
                "client_ipv4" => $this->clientIpv4,
                "type" => Transaction::TYPE_NORMAL_WITHDRAW,
                "sub_type" => $this->subType,
                "status" =>
                !$this->parent && $user->withdraw_review_enable
                    ? Transaction::STATUS_PENDING_REVIEW
                    : Transaction::STATUS_PAYING,
                "notify_status" => Transaction::NOTIFY_STATUS_NONE,
                "from_account_mode" => $user->account_mode,
                "to_account_mode" => null,
                "from_channel_account" => $this->bankCard->toFromChannelAccount(),
                "to_channel_account" => $this->toData ?? [],
                "amount" => $this->amount,
                "floating_amount" => $this->amount,
                "actual_amount" => 0,
                "usdt_rate" => $this->usdtRate ?? 0,
                "channel_code" => null,
                "order_number" => $this->orderNumber,
                "note" => $this->note,
                "notify_url" => $this->notifyUrl,
                "from_device_name" => null,
                "certificate_file_path" => null,
                "notified_at" => null,
                "matched_at" => null,
                "confirmed_at" => null,
                "locked_at" => null,
            ]);

            $this->transactionFeeService->createWithdrawFees(
                $transaction,
                $user,
                $agency,
                $parent,
                $type
            );

            DB::commit();
        } catch (RuntimeException $e) {
            DB::rollback();

            return null;
        }

        return $transaction;
    }

    public function paufenWithdrawFrom(
        User $merchant,
        $agency = false,
        ?Transaction $parent = null
    ) {
        $this->throwIfAnyMissing(["amount", "bankCard"]);

        try {
            DB::beginTransaction();

            $transaction = Transaction::create([
                "parent_id" => optional($this->parent)->getKey(),
                "from_id" => $merchant->getKey(),
                "from_wallet_id" => $merchant->wallet->getKey(),
                "to_id" => null,
                "locked_by_id" => null,
                "client_ipv4" => $this->clientIpv4,
                "type" => Transaction::TYPE_PAUFEN_WITHDRAW,
                "sub_type" => $this->subType,
                "status" =>
                !$this->parent && $merchant->withdraw_review_enable
                    ? Transaction::STATUS_PENDING_REVIEW
                    : Transaction::STATUS_MATCHING,
                "notify_status" => Transaction::NOTIFY_STATUS_NONE,
                "from_account_mode" => $merchant->account_mode,
                "to_account_mode" => null,
                "from_channel_account" => $this->bankCard->toFromChannelAccount(),
                "to_channel_account" => $this->toData ?? [],
                "amount" => $this->amount,
                "floating_amount" => $this->amount,
                "actual_amount" => 0,
                "usdt_rate" => $this->usdtRate ?? 0,
                "channel_code" => null,
                "order_number" => $this->orderNumber,
                "note" => $this->note,
                "notify_url" => $this->notifyUrl,
                "from_device_name" => null,
                "certificate_file_path" => null,
                "notified_at" => null,
                "matched_at" => null,
                "confirmed_at" => null,
                "locked_at" => null,
            ]);

            $this->transactionFeeService->createWithdrawFees(
                $transaction,
                $merchant,
                $agency,
                $parent
            );

            DB::commit();
        } catch (RuntimeException $e) {
            DB::rollback();
            return null;
        }

        return $transaction;
    }

    public function thirdchannelWithdrawFrom(
        User $user,
        $agency = false,
        ?Transaction $parent = null,
        $thirdchannel_id = null
    ) {
        $this->throwIfAnyMissing(["amount", "bankCard"]);

        try {
            DB::beginTransaction();

            $transaction = Transaction::create([
                "parent_id" => optional($this->parent)->getKey(),
                "from_id" => $user->getKey(),
                "from_wallet_id" => $user->wallet->getKey(),
                "to_id" => 0,
                "locked_by_id" => null,
                "client_ipv4" => $this->clientIpv4,
                "type" => Transaction::TYPE_NORMAL_WITHDRAW,
                "sub_type" => $this->subType,
                "status" => Transaction::STATUS_THIRD_PAYING,
                "notify_status" => Transaction::NOTIFY_STATUS_NONE,
                "from_account_mode" => $user->account_mode,
                "to_account_mode" => null,
                "from_channel_account" => $this->bankCard->toFromChannelAccount(),
                "to_channel_account" => $this->toData ?? [],
                "amount" => $this->amount,
                "floating_amount" => $this->amount,
                "actual_amount" => 0,
                "usdt_rate" => $this->usdtRate ?? 0,
                "channel_code" => null,
                "order_number" => $this->orderNumber,
                "note" => $this->note,
                "notify_url" => $this->notifyUrl,
                "from_device_name" => null,
                "certificate_file_path" => null,
                "notified_at" => null,
                "matched_at" => null,
                "confirmed_at" => null,
                "locked_at" => null,
                "thirdchannel_id" => $thirdchannel_id ?? null,
            ]);

            $this->transactionFeeService->createWithdrawFees($transaction, $user, $agency, $parent);

            DB::commit();
        } catch (RuntimeException $e) {
            DB::rollback();

            return null;
        }

        return $transaction;
    }

    /**
     * Create an internal transfer transaction.
     *
     * @param UserChannelAccount|null $account Target channel account
     */
    public function internalTransferFrom(?UserChannelAccount $account = null): ?Transaction
    {
        $this->throwIfAnyMissing(["amount", "bankCard"]);

        try {
            DB::beginTransaction();

            $data = [
                "from_id" => 0,
                "from_wallet_id" => 0,
                "to_id" => 0,
                "to_channel_account_id" => null,
                "type" => Transaction::TYPE_INTERNAL_TRANSFER,
                "status" => Transaction::STATUS_MATCHING,
                "notify_status" => Transaction::NOTIFY_STATUS_NONE,
                "to_account_mode" => null,
                "from_channel_account" => $this->bankCard->toFromChannelAccount(false),
                "to_channel_account" => [],
                "amount" => $this->amount,
                "floating_amount" => $this->amount,
                "actual_amount" => 0,
                "usdt_rate" => $this->usdtRate ?? 0,
                "channel_code" => null,
                "order_number" => $this->orderNumber,
                "note" => $this->note,
            ];

            if ($account) {
                $data["to_id"] = $account->user_id;
                $data["to_channel_account_id"] = $account->id;
                $data["to_channel_account"] = array_merge($account->detail, [
                    "channel_code" => $account->channel_code,
                ]);
                $data["status"] = Transaction::STATUS_PAYING;
                $data["matched_at"] = now();
            }

            $transaction = Transaction::create($data);

            DB::commit();
        } catch (RuntimeException $e) {
            DB::rollback();

            return null;
        }

        return $transaction;
    }

    public function createThirdchannel(
        User $user,
    ) {
        $this->throwIfAnyMissing(["amount", "bankCard"]);
        $transactionParams = [
            "parent_id" => optional($this->parent)->getKey(),
            "from_id" => $user->getKey(),
            "from_wallet_id" => $user->wallet->getKey(),
            "to_id" => 0,
            "locked_by_id" => null,
            "client_ipv4" => $this->clientIpv4,
            "type" => Transaction::TYPE_NORMAL_WITHDRAW,
            "sub_type" => $this->subType,
            "status" => Transaction::STATUS_THIRD_PAYING,
            "notify_status" => Transaction::NOTIFY_STATUS_NONE,
            "from_account_mode" => $user->account_mode,
            "to_account_mode" => null,
            "from_channel_account" => $this->bankCard->toFromChannelAccount(),
            "to_channel_account" => $this->toData ?? [],
            "amount" => $this->amount,
            "floating_amount" => $this->amount,
            "actual_amount" => 0,
            "usdt_rate" => $this->usdtRate ?? 0,
            "channel_code" => null,
            "order_number" => $this->orderNumber,
            "note" => $this->note,
            "notify_url" => $this->notifyUrl,
            "from_device_name" => null,
            "certificate_file_path" => null,
            "notified_at" => null,
            "matched_at" => null,
            "confirmed_at" => null,
            "locked_at" => null,
        ];
        return Transaction::create($transactionParams);
    }

    public function changeToPaufenWithdraw(Transaction $transaction, User $user, $agency = true)
    {
        $transaction->type = Transaction::TYPE_PAUFEN_WITHDRAW;
        $transaction->to_id = null;
        $transaction->status = !$this->parent && $user->withdraw_review_enable
            ? Transaction::STATUS_PENDING_REVIEW
            : Transaction::STATUS_MATCHING;
        $transaction->save();
    }

    public function changeToNormalWithdraw(Transaction $transaction, User $user, $agency = true)
    {
        $transaction->type = Transaction::TYPE_NORMAL_WITHDRAW;
        $transaction->status = !$this->parent && $user->withdraw_review_enable
            ? Transaction::STATUS_PENDING_REVIEW
            : Transaction::STATUS_PAYING;
        $transaction->save();
    }

    public function assignThirdChannel(Transaction $transaction, $thirdChannelId, User $user, $agency = true)
    {
        if (!$transaction->thirdchannel_id) {
            $updated = $transaction->update([
                'thirdchannel_id' => $thirdChannelId
            ]);
            if ($updated) {
                $transaction->thirdchannel_id = $thirdChannelId;
                $this->transactionFeeService->createWithdrawFees($transaction, $user, $agency);
            } else {
                return false;
            }
            return $transaction;
        }

        return false;
    }

    public function assignThirdChannelV2(Transaction $transaction, $thirdChannelId)
    {
        if (!$transaction->thirdchannel_id) {
            $updated = $transaction->update([
                'thirdchannel_id' => $thirdChannelId
            ]);
            if ($updated) {
                $transaction->thirdchannel_id = $thirdChannelId;
            } else {
                return false;
            }
            return $transaction;
        }
        return false;
    }

    public function changeToThirdChannelPending(Transaction $transaction)
    {
        $updated = $transaction->update([
            'status' => Transaction::STATUS_THIRD_PAYING
        ]);
        if ($updated) {
            $transaction->status = Transaction::STATUS_THIRD_PAYING;
        }
        return $transaction;
    }

    /** @deprecated Use TransactionFeeService::createWithdrawFees() */
    public function createWithdrawFees(...$args)
    {
        return $this->transactionFeeService->createWithdrawFees(...$args);
    }

    public function note(?string $note)
    {
        $this->note = $note;

        return $this;
    }

    public function notifyUrl(?string $notifyUrl)
    {
        $this->notifyUrl = $notifyUrl;

        return $this;
    }

    public function orderNumber(string $orderNumber)
    {
        $this->orderNumber = $orderNumber;

        return $this;
    }

    public function parent(?Transaction $transaction)
    {
        $this->parent = $transaction;

        return $this;
    }

    public function paufenDepositTo(
        User $provider,
        Transaction $transaction,
        ?Transaction $parent = null
    ) {
        return DB::transaction(function () use (
            $provider,
            $transaction,
            $parent
        ) {
            $success =
                Transaction::where("id", $transaction->getKey())
                ->whereNull("to_id")
                ->whereNull("locked_at")
                ->where("status", Transaction::STATUS_MATCHING)
                ->where("type", Transaction::TYPE_PAUFEN_WITHDRAW)
                ->update([
                    "to_id" => $provider->getKey(),
                    "to_wallet_id" => $provider->wallet->getKey(),
                    "to_account_mode" => $provider->account_mode,
                    "status" => Transaction::STATUS_PAYING,
                    "matched_at" => now(),
                ]) === 1;

            throw_if(
                !$success &&
                    $transaction->refresh()->status !==
                    Transaction::STATUS_MATCHING,
                new RaceConditionException()
            );

            throw_if(!$success, new RuntimeException("Unknown"));

            // 前面建立出款時已經有記錄系統利潤
            $this->transactionFeeService->createDepositFees($transaction, $provider, false, $parent);

            return $transaction;
        });
    }

    public function paufenDepositToAccount(
        UserChannelAccount $account,
        Transaction $transaction,
        ?Transaction $parent = null
    ) {
        return DB::transaction(function () use (
            $account,
            $transaction,
            $parent
        ) {
            $featureToggleRepository = app(FeatureToggleRepository::class);

            throw_if(
                // 如果啟用了 記錄收款帳號額度(RECORD_USER_CHANNEL_ACCOUNT_BALANCE) 才需要驗證
                $featureToggleRepository->enabled(
                    FeatureToggle::RECORD_USER_CHANNEL_ACCOUNT_BALANCE,
                    false
                ) && $account->balance < $transaction->floating_amount,
                new InsufficientAvailableBalance()
            );

            $provider = $account->user;
            $channelCode = $account->channel_code;
            $fromChannelAccount = $transaction->from_channel_account;

            $bank = Bank::where("name", $fromChannelAccount["bank_name"])
                ->orWhere("code", $fromChannelAccount["bank_name"])
                ->first();
            if (strtoupper($channelCode) === strtoupper(Channel::CODE_MAYA)) {
                if (
                    strtoupper($bank->name) === strtoupper($channelCode) ||
                    strtoupper($bank->name) ===
                    strtoupper("PayMaya / Maya Wallet")
                ) {
                    $fromChannelAccount["extra_withdraw_fee"] = 0;
                }
            } else {
                if (strtoupper($bank->name) === strtoupper($channelCode)) {
                    $fromChannelAccount["extra_withdraw_fee"] = 15;
                } else {
                    $fromChannelAccount["extra_withdraw_fee"] = 0;
                }
            }
            $success =
                Transaction::where("id", $transaction->getKey())
                ->whereNull("to_id")
                ->whereNull("locked_at")
                ->where("status", Transaction::STATUS_MATCHING)
                ->where("type", Transaction::TYPE_PAUFEN_WITHDRAW)
                ->update([
                    "to_id" => $provider->getKey(),
                    "to_wallet_id" => $provider->wallet->getKey(),
                    "to_channel_account_id" => $account->id,
                    "to_channel_account" => array_merge($account->detail, [
                        "channel_code" => $account->channel_code,
                    ]),
                    "from_channel_account" => $fromChannelAccount,
                    "to_account_mode" => $provider->account_mode,
                    "status" => Transaction::STATUS_PAYING,
                    "matched_at" => now(),
                ]) === 1;

            throw_if(
                !$success &&
                    $transaction->refresh()->status !==
                    Transaction::STATUS_MATCHING,
                new RaceConditionException()
            );

            throw_if(!$success, new RuntimeException("Unknown"));

            // 前面建立出款時已經有記錄系統利潤
            $this->transactionFeeService->createDepositFees($transaction, $provider, false, $parent);

            // 要累積帳號的出款日/月限額

            $amount = $this->bcMath->add(
                $transaction->floating_amount,
                data_get($fromChannelAccount, "extra_withdraw_fee", 0)
            );
            $this->userChannelAccountUtil->updateTotal(
                $account->id,
                $amount,
                true
            );

            return $transaction;
        });
    }

    // 跑分代收配到收款卡，更新收款卡資訊
    public function paufenTransactionFrom(
        UserChannelAccount $providerUserChannelAccount,
        Transaction $transaction
    ) {
        $transaction = DB::transaction(function () use (
            $providerUserChannelAccount,
            $transaction
        ) {
            $bcMath = app(BCMathUtil::class);

            // 確認不會超過限額檢查
            $defaultDailyLimit = 0;
            $dailyLimitEnabled = $this->featureToggleRepository->enabled(
                FeatureToggle::USER_CHANNEL_ACCOUNT_DAILY_LIMIT
            );
            if ($dailyLimitEnabled) {
                $defaultDailyLimit = $this->featureToggleRepository->valueOf(
                    FeatureToggle::USER_CHANNEL_ACCOUNT_DAILY_LIMIT
                );
            }

            $defaultMonthlyLimit = 0;
            $monthlyLimitEnabled = $this->featureToggleRepository->enabled(
                FeatureToggle::USER_CHANNEL_ACCOUNT_MONTHLY_LIMIT
            );
            if ($monthlyLimitEnabled) {
                $defaultMonthlyLimit = $this->featureToggleRepository->valueOf(
                    FeatureToggle::USER_CHANNEL_ACCOUNT_MONTHLY_LIMIT
                );
            }

            $account = UserChannelAccount::find(
                $providerUserChannelAccount->id
            );
            $amount = Transaction::where(
                "from_channel_account_id",
                $account->id
            )
                ->where("status", Transaction::STATUS_PAYING)
                ->where("created_at", ">=", now()->subMinutes(15))
                ->sum("amount"); // 取得收款帳號目前等待付款的金額

            if (
                $account->balance_limit != 0 &&
                $bcMath->sum([
                    $account->balance,
                    $amount,
                    $transaction->amount,
                ]) > $account->balance_limit
            ) {
                throw new RuntimeException("{$account->account}餘度不足");
            }

            $dailyLimit =
                $dailyLimitEnabled && $account->daily_status
                ? $account->daily_limit
                : $defaultDailyLimit;
            if (
                $dailyLimit &&
                $bcMath->sum([
                    $account->daily_total,
                    $amount,
                    $transaction->amount,
                ]) > $dailyLimit
            ) {
                throw new RuntimeException("{$account->account}日收餘度不足");
            }

            $monthlyLimit =
                $monthlyLimitEnabled && $account->monthly_status
                ? $account->monthly_limit
                : $defaultMonthlyLimit;
            if (
                $monthlyLimit &&
                $bcMath->sum([
                    $account->monthly_total,
                    $amount,
                    $transaction->amount,
                ]) > $monthlyLimit
            ) {
                throw new RuntimeException("{$account->account}月收餘度不足");
            }

            $updatedRow = Transaction::where([
                ["id", $transaction->getKey()],
                ["status", Transaction::STATUS_MATCHING],
            ])->update([
                "from_id" => $providerUserChannelAccount->user_id,
                "from_wallet_id" => $providerUserChannelAccount->wallet_id,
                "from_channel_account_id" => $providerUserChannelAccount->getKey(),
                "from_account_mode" =>
                $providerUserChannelAccount->user->account_mode,
                "from_channel_account" => array_merge(
                    $providerUserChannelAccount->detail,
                    ["account" => $providerUserChannelAccount->account]
                ),
                "status" => Transaction::STATUS_PAYING,
                "matched_at" => now(),
                "from_channel_account_hash_id" =>
                $providerUserChannelAccount->name,
                "from_device_name" => optional(
                    $providerUserChannelAccount->device
                )->name,
            ]);

            if ($updatedRow !== 1) {
                throw new RuntimeException("Conflict");
            }

            return $transaction->refresh();
        });

        if (
            !$this->featureToggleRepository->enabled(
                FeatureToggle::CANCEL_PAUFEN_MECHANISM
            )
        ) {
            DevicePayingTransaction::create([
                "device_id" => $providerUserChannelAccount->device_id,
                "user_channel_account_id" => $providerUserChannelAccount->getKey(),
                "amount" => $transaction->floating_amount,
                "transaction_id" => $transaction->getKey(),
                "created_at" => now(),
                "updated_at" => now(),
            ]);
        }

        $this->transactionFeeService->createPaufenTransactionFees(
            $transaction,
            $providerUserChannelAccount->channelAmount->channelGroup
        );

        return $transaction;
    }

    /** @deprecated Use TransactionFeeService::createPaufenTransactionFees() */
    public function createPaufenTransactionFees(...$args)
    {
        return $this->transactionFeeService->createPaufenTransactionFees(...$args);
    }

    // 跑分代收建立訂單，但是還沒配到收款卡
    public function paufenTransactionTo(User $merchant, Channel $channel)
    {
        $this->throwIfAnyMissing(["amount", "clientIpv4"]);

        $to = array_merge(
            [
                UserChannelAccount::DETAIL_KEY_REAL_NAME => $this->realName,
                "query" => json_encode(request()->all()),
                "binance_usdt_rate" => $this->binanceUsdtRate,
            ],
            $this->toData
        );
        return DB::transaction(function () use ($merchant, $channel, $to) {
            $transaction = Transaction::create([
                "from_id" => null,
                "to_id" => $merchant->getKey(),
                "to_wallet_id" => $merchant->wallet->getKey(),
                "locked_by_id" => null,
                "client_ipv4" => $this->clientIpv4,
                "type" => Transaction::TYPE_PAUFEN_TRANSACTION,
                "status" => Transaction::STATUS_MATCHING,
                "notify_status" => Transaction::NOTIFY_STATUS_NONE,
                "from_account_mode" => null,
                "to_account_mode" => $merchant->account_mode,
                "from_channel_account" => [],
                "to_channel_account" => $to,
                "amount" => $this->amount,
                "floating_amount" => $this->floatingAmount
                    ? $this->floatingAmount
                    : $this->amount,
                "actual_amount" => 0,
                "channel_code" => $channel->getKey(),
                "order_number" => $this->orderNumber,
                "note" => $this->note,
                "notify_url" => $this->notifyUrl,
                "usdt_rate" => $this->usdtRate ?? 0,
                "from_device_name" => null,
                "certificate_file_path" => null,
                "notified_at" => null,
                "matched_at" => null,
                "confirmed_at" => null,
                "locked_at" => null,
            ]);

            if ($transaction->channel->note_enable) {
                if ($channel->country == "vn") {
                    $transaction->update([
                        "note" => Str::substr(
                            $transaction->system_order_number,
                            -6
                        ),
                    ]);
                } else {
                    $transaction->update([
                        "note" =>
                        str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT)
                    ]);
                }
            }

            return $transaction;
        });
    }

    public function realName(?string $realName)
    {
        $this->realName = $realName;

        return $this;
    }

    public function subType($subType)
    {
        $this->subType = $subType;

        return $this;
    }
}
