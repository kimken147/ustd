# Withdraw Controller Refactor Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Refactor ThirdParty and Merchant Withdraw Controllers to eliminate ~710 lines of duplicated code using Template Method Pattern.

**Architecture:** Create BaseWithdrawService with shared logic, AgencyWithdrawService and WithdrawService as concrete implementations. ThirdChannelDispatcher handles third-party channel selection. DTOs (WithdrawContext, WithdrawResult) unify request/response handling.

**Tech Stack:** Laravel 11, PHP 8.2+, PHPUnit

**Design Document:** `docs/plans/2026-01-18-withdraw-controller-refactor-design.md`

---

## Task 1: Create DTO Classes

**Files:**
- Create: `api/app/Services/Withdraw/DTO/WithdrawContext.php`
- Create: `api/app/Services/Withdraw/DTO/WithdrawResult.php`
- Create: `api/app/Services/Withdraw/DTO/ThirdPartyErrorResponse.php`

**Step 1: Create directory structure**

Run: `mkdir -p api/app/Services/Withdraw/DTO api/app/Services/Withdraw/Exceptions`

**Step 2: Create WithdrawContext**

```php
<?php

namespace App\Services\Withdraw\DTO;

use App\Models\Bank;
use App\Models\Channel;
use App\Models\User;
use App\Models\Wallet;
use App\Utils\BankCardTransferObject;

class WithdrawContext
{
    public const SOURCE_THIRD_PARTY = 'third_party';
    public const SOURCE_MERCHANT = 'merchant';

    public function __construct(
        public readonly User $merchant,
        public readonly Wallet $wallet,
        public readonly string $amount,
        public readonly BankCardTransferObject $bankCard,
        public readonly string $orderNumber,
        public readonly ?string $notifyUrl,
        public readonly string $source,
        public readonly ?string $usdtRate = null,
        public readonly ?string $binanceUsdtRate = null,
    ) {}

    public function isFromThirdParty(): bool
    {
        return $this->source === self::SOURCE_THIRD_PARTY;
    }

    public function isFromMerchant(): bool
    {
        return $this->source === self::SOURCE_MERCHANT;
    }

    public function isUsdt(): bool
    {
        return $this->bankCard->bankName === Channel::CODE_USDT;
    }

    public function getBank(): ?Bank
    {
        return Bank::where('name', $this->bankCard->bankName)
            ->orWhere('code', $this->bankCard->bankName)
            ->first();
    }
}
```

**Step 3: Create WithdrawResult**

```php
<?php

namespace App\Services\Withdraw\DTO;

use App\Models\Transaction;

class WithdrawResult
{
    public function __construct(
        public readonly Transaction $transaction,
        public readonly bool $success = true,
        public readonly ?string $message = null,
    ) {}

    public function getTransaction(): Transaction
    {
        return $this->transaction->refresh()->load([
            'from',
            'transactionFees',
            'fromChannelAccount',
        ]);
    }
}
```

**Step 4: Create ThirdPartyErrorResponse**

```php
<?php

namespace App\Services\Withdraw\DTO;

use App\Utils\ThirdPartyResponseUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ThirdPartyErrorResponse
{
    public function __construct(
        public readonly int $httpStatusCode,
        public readonly int $errorCode,
        public readonly string $message,
    ) {}

    public function toResponse(): JsonResponse
    {
        return response()->json([
            'http_status_code' => $this->httpStatusCode,
            'error_code' => $this->errorCode,
            'message' => $this->message,
        ]);
    }

    public static function badRequest(int $errorCode, string $message): self
    {
        return new self(Response::HTTP_BAD_REQUEST, $errorCode, $message);
    }

    public static function forbidden(int $errorCode, string $message): self
    {
        return new self(Response::HTTP_FORBIDDEN, $errorCode, $message);
    }

    public static function userNotFound(): self
    {
        return self::badRequest(
            ThirdPartyResponseUtil::ERROR_CODE_USER_NOT_FOUND,
            __('common.User not found')
        );
    }

    public static function invalidSign(): self
    {
        return self::badRequest(
            ThirdPartyResponseUtil::ERROR_CODE_INVALID_SIGN,
            __('common.Signature error')
        );
    }

    public static function invalidIp(): self
    {
        return self::badRequest(
            ThirdPartyResponseUtil::ERROR_CODE_INVALID_IP,
            __('common.Please contact admin to add IP to whitelist')
        );
    }

    public static function duplicateOrderNumber(): self
    {
        return self::badRequest(
            ThirdPartyResponseUtil::ERROR_CODE_DUPLICATE_ORDER_NUMBER,
            __('common.Duplicate number')
        );
    }

    public static function insufficientBalance(): self
    {
        return self::badRequest(
            ThirdPartyResponseUtil::ERROR_CODE_INSUFFICIENT_BALANCE,
            __('wallet.InsufficientAvailableBalance')
        );
    }

    public static function withdrawDisabled(): self
    {
        return self::badRequest(
            ThirdPartyResponseUtil::ERROR_CODE_WITHDRAW_DISABLED,
            __('user.Withdraw disabled')
        );
    }

    public static function agencyWithdrawDisabled(): self
    {
        return self::badRequest(
            ThirdPartyResponseUtil::ERROR_CODE_AGENCY_WITHDRAW_DISABLED,
            __('user.Agency withdraw disabled')
        );
    }

    public static function invalidMinAmount(string $amount): self
    {
        return self::badRequest(
            ThirdPartyResponseUtil::ERROR_CODE_INVALID_MIN_AMOUNT,
            __('common.Amount below minimum: :amount', ['amount' => $amount])
        );
    }

    public static function invalidMaxAmount(string $amount): self
    {
        return self::badRequest(
            ThirdPartyResponseUtil::ERROR_CODE_INVALID_MAX_AMOUNT,
            __('common.Amount above maximum: :amount', ['amount' => $amount])
        );
    }

    public static function decimalNotAllowed(): self
    {
        return self::badRequest(
            ThirdPartyResponseUtil::ERROR_CODE_INVALID_MIN_AMOUNT,
            __('common.Decimal amount not allowed')
        );
    }

    public static function bankNotSupported(): self
    {
        return self::badRequest(
            ThirdPartyResponseUtil::ERROR_CODE_BANK_NOT_FOUND,
            __('common.Bank not supported')
        );
    }

    public static function forbiddenCardHolder(): self
    {
        return self::badRequest(
            ThirdPartyResponseUtil::ERROR_CODE_FORBIDDEN_NAME,
            __('common.Card holder access forbidden')
        );
    }

    public static function missingParameter(string $attribute): self
    {
        return self::badRequest(
            ThirdPartyResponseUtil::ERROR_CODE_BAD_REQUEST,
            __('common.Information is incorrect: :attribute', ['attribute' => $attribute])
        );
    }

    public static function raceCondition(): self
    {
        return self::badRequest(
            ThirdPartyResponseUtil::ERROR_CODE_RACE_CONDITION,
            __('common.Conflict! Please try again later')
        );
    }
}
```

**Step 5: Commit**

```bash
git add api/app/Services/Withdraw/DTO/
git commit -m "feat(withdraw): add DTO classes for withdraw refactoring

- WithdrawContext: encapsulates withdraw request context
- WithdrawResult: encapsulates withdraw operation result
- ThirdPartyErrorResponse: standardized error responses for ThirdParty API"
```

---

## Task 2: Create WithdrawValidationException

**Files:**
- Create: `api/app/Services/Withdraw/Exceptions/WithdrawValidationException.php`

**Step 1: Create exception class**

```php
<?php

namespace App\Services\Withdraw\Exceptions;

use App\Services\Withdraw\DTO\ThirdPartyErrorResponse;
use Exception;
use Illuminate\Http\JsonResponse;

class WithdrawValidationException extends Exception
{
    public function __construct(
        private readonly ThirdPartyErrorResponse $errorResponse,
        private readonly bool $isThirdParty = false,
    ) {
        parent::__construct($errorResponse->message, $errorResponse->errorCode);
    }

    public static function fromThirdParty(ThirdPartyErrorResponse $response): self
    {
        return new self($response, true);
    }

    public static function fromMerchant(string $message, int $code = 400): self
    {
        return new self(
            ThirdPartyErrorResponse::badRequest($code, $message),
            false
        );
    }

    public function isThirdParty(): bool
    {
        return $this->isThirdParty;
    }

    public function toThirdPartyResponse(): JsonResponse
    {
        return $this->errorResponse->toResponse();
    }

    public function getErrorResponse(): ThirdPartyErrorResponse
    {
        return $this->errorResponse;
    }
}
```

**Step 2: Commit**

```bash
git add api/app/Services/Withdraw/Exceptions/
git commit -m "feat(withdraw): add WithdrawValidationException"
```

---

## Task 3: Create ThirdChannelDispatcher

**Files:**
- Create: `api/app/Services/Withdraw/ThirdChannelDispatcher.php`

**Step 1: Create ThirdChannelDispatcher**

```php
<?php

namespace App\Services\Withdraw;

use App\Models\FeatureToggle;
use App\Models\MerchantThirdChannel;
use App\Models\ThirdChannel;
use App\Models\Transaction;
use App\Models\TransactionNote;
use App\Models\User;
use App\Repository\FeatureToggleRepository;
use App\Utils\TransactionFactory;
use App\Utils\TransactionUtil;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
```

**Step 2: Commit**

```bash
git add api/app/Services/Withdraw/ThirdChannelDispatcher.php
git commit -m "feat(withdraw): add ThirdChannelDispatcher for third-party channel routing

Extracts third-party channel selection and dispatch logic from controllers"
```

---

## Task 4: Create BaseWithdrawService

**Files:**
- Create: `api/app/Services/Withdraw/BaseWithdrawService.php`

**Step 1: Create BaseWithdrawService**

```php
<?php

namespace App\Services\Withdraw;

use App\Models\Bank;
use App\Models\BannedRealname;
use App\Models\Channel;
use App\Models\FeatureToggle;
use App\Models\Transaction;
use App\Models\User;
use App\Repository\FeatureToggleRepository;
use App\Services\Withdraw\DTO\ThirdPartyErrorResponse;
use App\Services\Withdraw\DTO\WithdrawContext;
use App\Services\Withdraw\DTO\WithdrawResult;
use App\Services\Withdraw\Exceptions\WithdrawValidationException;
use App\Utils\BankCardTransferObject;
use App\Utils\BCMathUtil;
use App\Utils\FloatUtil;
use App\Utils\TransactionFactory;
use App\Utils\UsdtUtil;
use App\Utils\WalletUtil;
use App\Utils\WhitelistedIpManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FALaravel\Support\Authenticator;

abstract class BaseWithdrawService
{
    public function __construct(
        protected readonly FeatureToggleRepository $featureToggleRepository,
        protected readonly BCMathUtil $bcMath,
        protected readonly FloatUtil $floatUtil,
        protected readonly TransactionFactory $transactionFactory,
        protected readonly WalletUtil $walletUtil,
        protected readonly WhitelistedIpManager $whitelistedIpManager,
        protected readonly BankCardTransferObject $bankCardTransferObject,
        protected readonly ThirdChannelDispatcher $thirdChannelDispatcher,
        protected readonly UsdtUtil $usdtUtil,
    ) {}

    // ========== Abstract Methods (must be implemented by subclasses) ==========

    abstract protected function getSubType(): string;

    abstract protected function getMinAmountField(): string;

    abstract protected function getMaxAmountField(): string;

    abstract protected function calculateTotalCost(WithdrawContext $context): string;

    abstract protected function validateFeatureEnabled(WithdrawContext $context): void;

    abstract protected function getPaufenEnabled(User $merchant): bool;

    // ========== Public API ==========

    /**
     * Build context from ThirdParty API request
     */
    public function buildContextFromThirdParty(Request $request): WithdrawContext
    {
        $this->validateXToken($request);
        $this->validateRequiredAttributes($request);

        $merchant = $this->validateSignatureAndGetMerchant($request);
        $this->validateWhitelistedIp($merchant, $request);

        return new WithdrawContext(
            merchant: $merchant,
            wallet: $merchant->wallet,
            amount: $request->input('amount'),
            bankCard: $this->bankCardTransferObject->plain(
                $request->input('bank_name'),
                $request->input('bank_card_number'),
                $request->input('bank_card_holder_name') ?? '',
                $request->input('bank_province') ?? '',
                $request->input('bank_city') ?? ''
            ),
            orderNumber: $request->input('order_number'),
            notifyUrl: $request->input('notify_url'),
            source: WithdrawContext::SOURCE_THIRD_PARTY,
            usdtRate: $this->resolveUsdtRate($request),
            binanceUsdtRate: $this->resolveBinanceUsdtRate($request),
        );
    }

    /**
     * Build context from Merchant portal request
     */
    public function buildContextFromMerchant(Request $request, User $user): WithdrawContext
    {
        $this->validateXToken($request);

        $merchant = $user->realUser();

        abort_if(
            $merchant->role !== User::ROLE_MERCHANT,
            Response::HTTP_FORBIDDEN,
            __('permission.Denied')
        );

        $this->validate2FAIfEnabled($request, $user);

        return new WithdrawContext(
            merchant: $merchant,
            wallet: $user->wallet,
            amount: $request->input('amount'),
            bankCard: $this->resolveBankCard($request, $user),
            orderNumber: $this->resolveOrderNumber($request),
            notifyUrl: null,
            source: WithdrawContext::SOURCE_MERCHANT,
            usdtRate: $this->resolveUsdtRate($request),
            binanceUsdtRate: $this->resolveBinanceUsdtRate($request),
        );
    }

    /**
     * Execute the withdraw operation
     */
    public function execute(WithdrawContext $context): WithdrawResult
    {
        // 1. Validation phase
        $this->validateFeatureEnabled($context);
        $this->validateBannedRealname($context);
        $this->validateAmount($context);
        $this->validateDuplicateOrder($context);
        $this->validateBank($context);
        $this->validateBalance($context);

        // 2. Calculate total cost
        $totalCost = $this->calculateTotalCost($context);

        // 3. Create transaction (with third-party channel handling)
        $transaction = DB::transaction(function () use ($context, $totalCost) {
            $transaction = $this->createTransaction($context);

            $this->walletUtil->withdraw(
                $context->wallet,
                $totalCost,
                $transaction->order_number,
                'withdraw'
            );

            return $transaction;
        });

        // 4. Post-processing
        Cache::put('admin_withdraws_added_at', now(), now()->addSeconds(60));

        return new WithdrawResult($transaction);
    }

    // ========== Validation Methods ==========

    protected function validateXToken(Request $request): void
    {
        abort_if(
            $request->hasHeader('X-Token') && $request->header('X-Token') != config('app.x_token'),
            Response::HTTP_BAD_REQUEST
        );
    }

    protected function validateRequiredAttributes(Request $request): void
    {
        $requiredAttributes = [
            'username',
            'amount',
            'bank_card_number',
            'bank_name',
            'order_number',
            'sign',
        ];

        foreach ($requiredAttributes as $attribute) {
            if (empty($request->input($attribute))) {
                throw WithdrawValidationException::fromThirdParty(
                    ThirdPartyErrorResponse::missingParameter($attribute)
                );
            }
        }
    }

    protected function validateSignatureAndGetMerchant(Request $request): User
    {
        $merchant = User::where([
            ['username', $request->input('username')],
            ['role', User::ROLE_MERCHANT],
        ])->first();

        if (!$merchant) {
            throw WithdrawValidationException::fromThirdParty(
                ThirdPartyErrorResponse::userNotFound()
            );
        }

        $parameters = $request->except('sign');
        ksort($parameters);
        $sign = md5(urldecode(http_build_query($parameters) . '&secret_key=' . $merchant->secret_key));

        if (strcasecmp($sign, $request->input('sign'))) {
            throw WithdrawValidationException::fromThirdParty(
                ThirdPartyErrorResponse::invalidSign()
            );
        }

        return $merchant;
    }

    protected function validateWhitelistedIp(User $merchant, Request $request): void
    {
        if ($this->whitelistedIpManager->isNotAllowedToUseThirdPartyApi($merchant, $request)) {
            throw WithdrawValidationException::fromThirdParty(
                ThirdPartyErrorResponse::invalidIp()
            );
        }
    }

    protected function validate2FAIfEnabled(Request $request, User $user): void
    {
        if (!$user->withdraw_google2fa_enable) {
            return;
        }

        $request->validate([
            config('google2fa.otp_input') => 'required|string',
        ]);

        /** @var Authenticator $authenticator */
        $authenticator = app(Authenticator::class)->bootStateless($request);

        abort_if(
            !$authenticator->isAuthenticated(),
            Response::HTTP_BAD_REQUEST,
            __('google2fa.Invalid OTP')
        );
    }

    protected function validateBannedRealname(WithdrawContext $context): void
    {
        $holderName = $context->bankCard->bankCardHolderName;

        if (BannedRealname::where(['realname' => $holderName, 'type' => BannedRealname::TYPE_WITHDRAW])->exists()) {
            if ($context->isFromThirdParty()) {
                throw WithdrawValidationException::fromThirdParty(
                    ThirdPartyErrorResponse::forbiddenCardHolder()
                );
            }
            abort(Response::HTTP_BAD_REQUEST, '该持卡人禁止访问');
        }
    }

    protected function validateAmount(WithdrawContext $context): void
    {
        // Validate minimum amount (must be at least 1)
        if ($this->bcMath->lt($context->amount, 1)) {
            if ($context->isFromThirdParty()) {
                throw WithdrawValidationException::fromThirdParty(
                    ThirdPartyErrorResponse::invalidMinAmount('1')
                );
            }
            abort(Response::HTTP_BAD_REQUEST, '金额低于下限：1');
        }

        // Validate no decimal if feature enabled
        if (
            $this->featureToggleRepository->enabled(FeatureToggle::NO_FLOAT_IN_WITHDRAWS)
            && $this->floatUtil->numberHasFloat($context->amount)
        ) {
            if ($context->isFromThirdParty()) {
                throw WithdrawValidationException::fromThirdParty(
                    ThirdPartyErrorResponse::decimalNotAllowed()
                );
            }
            abort(Response::HTTP_BAD_REQUEST, '禁止提交小数点金额');
        }

        // Validate min amount from wallet settings
        $minAmount = $context->wallet->{$this->getMinAmountField()} ?? 0;
        if ($this->bcMath->gtZero($minAmount) && $this->bcMath->lt($context->amount, $minAmount)) {
            if ($context->isFromThirdParty()) {
                throw WithdrawValidationException::fromThirdParty(
                    ThirdPartyErrorResponse::invalidMinAmount($minAmount)
                );
            }
            abort(Response::HTTP_BAD_REQUEST, '金额低于下限：' . $minAmount);
        }

        // Validate max amount from wallet settings
        $maxAmount = $context->wallet->{$this->getMaxAmountField()} ?? 0;
        if ($this->bcMath->gtZero($maxAmount) && $this->bcMath->gt($context->amount, $maxAmount)) {
            if ($context->isFromThirdParty()) {
                throw WithdrawValidationException::fromThirdParty(
                    ThirdPartyErrorResponse::invalidMaxAmount($maxAmount)
                );
            }
            abort(Response::HTTP_BAD_REQUEST, '金额高于上限：' . $maxAmount);
        }
    }

    protected function validateDuplicateOrder(WithdrawContext $context): void
    {
        $exists = Transaction::whereIn('type', [
            Transaction::TYPE_PAUFEN_WITHDRAW,
            Transaction::TYPE_NORMAL_WITHDRAW,
        ])
            ->where('from_id', $context->merchant->getKey())
            ->where('order_number', $context->orderNumber)
            ->exists();

        if ($exists) {
            if ($context->isFromThirdParty()) {
                throw WithdrawValidationException::fromThirdParty(
                    ThirdPartyErrorResponse::duplicateOrderNumber()
                );
            }
            abort(Response::HTTP_BAD_REQUEST, '订单号：' . $context->orderNumber . '已存在');
        }
    }

    protected function validateBank(WithdrawContext $context): void
    {
        if (!$this->featureToggleRepository->enabled(FeatureToggle::WITHDRAW_BANK_NAME_MAPPING)) {
            return;
        }

        $bank = $context->getBank();
        $daifuBanks = Channel::where('type', Channel::TYPE_DEPOSIT_WITHDRAW)
            ->get()
            ->map(fn($channel) => $channel->deposit_account_fields['merchant_can_withdraw_banks'] ?? [])
            ->flatten();

        if ($daifuBanks->isEmpty()) {
            return; // No restriction if no banks configured
        }

        $inDaifuBank = $daifuBanks->map(fn($b) => strtoupper($b))
            ->contains(strtoupper($context->bankCard->bankName));

        if (!$inDaifuBank) {
            if ($context->isFromThirdParty()) {
                throw WithdrawValidationException::fromThirdParty(
                    ThirdPartyErrorResponse::bankNotSupported()
                );
            }
            abort(Response::HTTP_BAD_REQUEST, '不支援此银行');
        }
    }

    protected function validateBalance(WithdrawContext $context): void
    {
        $totalCost = $this->calculateTotalCost($context);

        if ($this->bcMath->lt($context->wallet->available_balance, $totalCost)) {
            if ($context->isFromThirdParty()) {
                throw WithdrawValidationException::fromThirdParty(
                    ThirdPartyErrorResponse::insufficientBalance()
                );
            }
            abort(Response::HTTP_BAD_REQUEST, __('wallet.InsufficientAvailableBalance'));
        }
    }

    // ========== Transaction Creation ==========

    protected function createTransaction(WithdrawContext $context): Transaction
    {
        $factory = $this->transactionFactory->fresh()
            ->bankCard($context->bankCard)
            ->orderNumber($context->orderNumber)
            ->notifyUrl($context->notifyUrl)
            ->amount($context->amount)
            ->subType($this->getSubType());

        if ($context->isUsdt() && $context->usdtRate) {
            $factory = $factory->usdtRate($context->usdtRate, $context->binanceUsdtRate);
        }

        $paufenEnabled = $this->getPaufenEnabled($context->merchant);
        $withdrawMethod = $paufenEnabled ? 'paufenWithdrawFrom' : 'normalWithdrawFrom';

        $bankCardData = [
            'bank_card_holder_name' => $context->bankCard->bankCardHolderName,
            'bank_card_number' => $context->bankCard->bankCardNumber,
            'bank_name' => $context->bankCard->bankName,
            'bank_province' => $context->bankCard->bankProvince ?? '',
            'bank_city' => $context->bankCard->bankCity ?? '',
            'amount' => $context->amount,
            'order_number' => $context->orderNumber,
        ];

        return $this->thirdChannelDispatcher->dispatch(
            merchant: $context->merchant,
            amount: $context->amount,
            orderNumber: $context->orderNumber,
            bankCardData: $bankCardData,
            onThirdChannelSuccess: fn($channelId) => $factory->thirdchannelWithdrawFrom(
                $context->merchant,
                $context->isFromMerchant(),
                null,
                $channelId
            ),
            onLocalFallback: fn() => $factory->$withdrawMethod(
                $context->merchant,
                $context->isFromMerchant()
            ),
        );
    }

    // ========== Helper Methods ==========

    protected function resolveBankCard(Request $request, User $user): BankCardTransferObject
    {
        return $this->bankCardTransferObject->plain(
            $request->input('bank_name'),
            $request->input('bank_card_number'),
            $request->input('bank_card_holder_name') ?? '',
            $request->input('bank_province') ?? '',
            $request->input('bank_city') ?? ''
        );
    }

    protected function resolveOrderNumber(Request $request): string
    {
        return $request->input('order_id')
            ?? chr(mt_rand(65, 90)) . chr(mt_rand(65, 90)) . chr(mt_rand(65, 90)) . date('YmdHis') . rand(100, 999);
    }

    protected function resolveUsdtRate(Request $request): ?string
    {
        if ($request->input('bank_name') !== Channel::CODE_USDT) {
            return null;
        }

        $binanceRate = $this->usdtUtil->getRate()['rate'];
        return $request->input('usdt_rate', $binanceRate);
    }

    protected function resolveBinanceUsdtRate(Request $request): ?string
    {
        if ($request->input('bank_name') !== Channel::CODE_USDT) {
            return null;
        }

        return $this->usdtUtil->getRate()['rate'];
    }
}
```

**Step 2: Commit**

```bash
git add api/app/Services/Withdraw/BaseWithdrawService.php
git commit -m "feat(withdraw): add BaseWithdrawService with shared withdraw logic

Template Method pattern base class containing:
- Context building for ThirdParty and Merchant sources
- All validation logic (amount, balance, bank, banned realname)
- Transaction creation with third-party channel dispatch
- Hook methods for subclass customization"
```

---

## Task 5: Create AgencyWithdrawService

**Files:**
- Create: `api/app/Services/Withdraw/AgencyWithdrawService.php`

**Step 1: Create AgencyWithdrawService**

```php
<?php

namespace App\Services\Withdraw;

use App\Models\FeatureToggle;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Withdraw\DTO\ThirdPartyErrorResponse;
use App\Services\Withdraw\DTO\WithdrawContext;
use App\Services\Withdraw\Exceptions\WithdrawValidationException;

class AgencyWithdrawService extends BaseWithdrawService
{
    protected function getSubType(): string
    {
        return Transaction::SUB_TYPE_AGENCY_WITHDRAW;
    }

    protected function getMinAmountField(): string
    {
        return 'agency_withdraw_min_amount';
    }

    protected function getMaxAmountField(): string
    {
        return 'agency_withdraw_max_amount';
    }

    protected function calculateTotalCost(WithdrawContext $context): string
    {
        $bank = $context->getBank();
        $needExtraWithdrawFee = $bank ? $bank->needExtraWithdrawFee : false;

        return $context->wallet->calculateTotalAgencyWithdrawAmount(
            $context->amount,
            $needExtraWithdrawFee
        );
    }

    protected function validateFeatureEnabled(WithdrawContext $context): void
    {
        $featureEnabled = $this->featureToggleRepository->enabled(FeatureToggle::ENABLE_AGENCY_WITHDRAW);
        $merchantEnabled = $context->merchant->agency_withdraw_enable;

        if (!$featureEnabled || !$merchantEnabled) {
            if ($context->isFromThirdParty()) {
                throw WithdrawValidationException::fromThirdParty(
                    ThirdPartyErrorResponse::agencyWithdrawDisabled()
                );
            }
            abort(400, __('user.Agency withdraw disabled'));
        }
    }

    protected function getPaufenEnabled(User $merchant): bool
    {
        return $this->featureToggleRepository->enabled(FeatureToggle::FEATURE_PAUFEN_WITHDRAW_MATCHING_TIMED_OUT)
            && $merchant->paufen_agency_withdraw_enable;
    }
}
```

**Step 2: Commit**

```bash
git add api/app/Services/Withdraw/AgencyWithdrawService.php
git commit -m "feat(withdraw): add AgencyWithdrawService

Concrete implementation for agency withdraw operations"
```

---

## Task 6: Create WithdrawService

**Files:**
- Create: `api/app/Services/Withdraw/WithdrawService.php`

**Step 1: Create WithdrawService**

```php
<?php

namespace App\Services\Withdraw;

use App\Models\BankCard;
use App\Models\FeatureToggle;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Withdraw\DTO\ThirdPartyErrorResponse;
use App\Services\Withdraw\DTO\WithdrawContext;
use App\Services\Withdraw\Exceptions\WithdrawValidationException;
use App\Utils\BankCardTransferObject;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WithdrawService extends BaseWithdrawService
{
    protected function getSubType(): string
    {
        return Transaction::SUB_TYPE_WITHDRAW;
    }

    protected function getMinAmountField(): string
    {
        return 'withdraw_min_amount';
    }

    protected function getMaxAmountField(): string
    {
        return 'withdraw_max_amount';
    }

    protected function calculateTotalCost(WithdrawContext $context): string
    {
        $bank = $context->getBank();
        $needExtraWithdrawFee = $bank ? $bank->needExtraWithdrawFee : false;

        return $context->wallet->calculateTotalWithdrawAmount(
            $context->amount,
            $needExtraWithdrawFee
        );
    }

    protected function validateFeatureEnabled(WithdrawContext $context): void
    {
        if (!$context->merchant->withdraw_enable) {
            if ($context->isFromThirdParty()) {
                throw WithdrawValidationException::fromThirdParty(
                    ThirdPartyErrorResponse::withdrawDisabled()
                );
            }
            abort(400, __('user.Withdraw disabled'));
        }
    }

    protected function getPaufenEnabled(User $merchant): bool
    {
        return $this->featureToggleRepository->enabled(FeatureToggle::FEATURE_PAUFEN_WITHDRAW_MATCHING_TIMED_OUT)
            && $merchant->paufen_withdraw_enable;
    }

    /**
     * Build context from Merchant portal with BankCard model
     * (Merchant/WithdrawController uses saved bank cards)
     */
    public function buildContextFromMerchantWithBankCard(Request $request, User $user): WithdrawContext
    {
        $this->validateXToken($request);

        $merchant = $user->realUser();

        abort_if(
            $merchant->role !== User::ROLE_MERCHANT,
            Response::HTTP_FORBIDDEN,
            __('permission.Denied')
        );

        $this->validate2FAIfEnabled($request, $user);

        $bankCard = BankCard::where('user_id', $user->getKey())
            ->find($request->input('bank_card_id'));

        abort_if(!$bankCard, Response::HTTP_BAD_REQUEST, __('bank-card.Not owner'));
        abort_if(
            $bankCard->status !== BankCard::STATUS_REVIEW_PASSED,
            Response::HTTP_BAD_REQUEST,
            __('bank-card.Not reviewing passed')
        );

        return new WithdrawContext(
            merchant: $merchant,
            wallet: $user->wallet,
            amount: $request->input('amount'),
            bankCard: $this->bankCardTransferObject->model($bankCard),
            orderNumber: $this->resolveOrderNumber($request),
            notifyUrl: null,
            source: WithdrawContext::SOURCE_MERCHANT,
            usdtRate: $this->resolveUsdtRateForBankCard($bankCard, $request),
            binanceUsdtRate: $this->resolveBinanceUsdtRateForBankCard($bankCard),
        );
    }

    private function resolveUsdtRateForBankCard(BankCard $bankCard, Request $request): ?string
    {
        if ($bankCard->bank_name !== \App\Models\Channel::CODE_USDT) {
            return null;
        }

        $binanceRate = $this->usdtUtil->getRate()['rate'];
        return $request->input('usdt_rate', $binanceRate);
    }

    private function resolveBinanceUsdtRateForBankCard(BankCard $bankCard): ?string
    {
        if ($bankCard->bank_name !== \App\Models\Channel::CODE_USDT) {
            return null;
        }

        return $this->usdtUtil->getRate()['rate'];
    }
}
```

**Step 2: Commit**

```bash
git add api/app/Services/Withdraw/WithdrawService.php
git commit -m "feat(withdraw): add WithdrawService

Concrete implementation for regular withdraw operations
Includes buildContextFromMerchantWithBankCard for saved bank card support"
```

---

## Task 7: Refactor ThirdParty/AgencyWithdrawController

**Files:**
- Modify: `api/app/Http/Controllers/ThirdParty/AgencyWithdrawController.php`

**Step 1: Refactor controller**

```php
<?php

namespace App\Http\Controllers\ThirdParty;

use App\Http\Controllers\Controller;
use App\Http\Resources\ThirdParty\Withdraw;
use App\Services\Withdraw\AgencyWithdrawService;
use App\Services\Withdraw\Exceptions\WithdrawValidationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AgencyWithdrawController extends Controller
{
    public function store(Request $request, AgencyWithdrawService $service)
    {
        try {
            $context = $service->buildContextFromThirdParty($request);
            $result = $service->execute($context);

            return Withdraw::make($result->getTransaction())
                ->additional([
                    'http_status_code' => 201,
                    'message' => __('common.Submit successful'),
                ])
                ->response()
                ->setStatusCode(Response::HTTP_OK);
        } catch (WithdrawValidationException $e) {
            return $e->toThirdPartyResponse();
        }
    }
}
```

**Step 2: Commit**

```bash
git add api/app/Http/Controllers/ThirdParty/AgencyWithdrawController.php
git commit -m "refactor(withdraw): simplify ThirdParty/AgencyWithdrawController

Delegate all logic to AgencyWithdrawService
Reduced from ~500 lines to ~25 lines"
```

---

## Task 8: Refactor ThirdParty/WithdrawController

**Files:**
- Modify: `api/app/Http/Controllers/ThirdParty/WithdrawController.php`

**Step 1: Refactor controller**

```php
<?php

namespace App\Http\Controllers\ThirdParty;

use App\Http\Controllers\Controller;
use App\Http\Resources\ThirdParty\Withdraw;
use App\Services\Withdraw\Exceptions\WithdrawValidationException;
use App\Services\Withdraw\WithdrawService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WithdrawController extends Controller
{
    public function store(Request $request, WithdrawService $service)
    {
        try {
            $context = $service->buildContextFromThirdParty($request);
            $result = $service->execute($context);

            return Withdraw::make($result->getTransaction())
                ->additional([
                    'http_status_code' => 201,
                    'message' => __('common.Submit successful'),
                ])
                ->response()
                ->setStatusCode(Response::HTTP_OK);
        } catch (WithdrawValidationException $e) {
            return $e->toThirdPartyResponse();
        }
    }
}
```

**Step 2: Commit**

```bash
git add api/app/Http/Controllers/ThirdParty/WithdrawController.php
git commit -m "refactor(withdraw): simplify ThirdParty/WithdrawController

Delegate all logic to WithdrawService
Reduced from ~370 lines to ~25 lines"
```

---

## Task 9: Refactor Merchant/AgencyWithdrawController

**Files:**
- Modify: `api/app/Http/Controllers/Merchant/AgencyWithdrawController.php`

**Step 1: Refactor controller**

```php
<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Services\Withdraw\AgencyWithdrawService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AgencyWithdrawController extends Controller
{
    public function store(Request $request, AgencyWithdrawService $service)
    {
        $this->validate($request, [
            'bank_card_number' => 'required|max:50',
            'bank_card_holder_name' => 'max:50',
            'bank_name' => 'required|max:50',
            'amount' => 'required|numeric|min:1',
        ]);

        $context = $service->buildContextFromMerchant($request, auth()->user());
        $service->execute($context);

        return response()->noContent(Response::HTTP_CREATED);
    }
}
```

**Step 2: Commit**

```bash
git add api/app/Http/Controllers/Merchant/AgencyWithdrawController.php
git commit -m "refactor(withdraw): simplify Merchant/AgencyWithdrawController

Delegate all logic to AgencyWithdrawService
Reduced from ~370 lines to ~20 lines"
```

---

## Task 10: Refactor Merchant/WithdrawController (store method only)

**Files:**
- Modify: `api/app/Http/Controllers/Merchant/WithdrawController.php`

**Step 1: Refactor store method (keep other methods unchanged)**

Replace the `store` method with:

```php
public function store(
    Request $request,
    WithdrawService $service
) {
    $this->validate($request, [
        'bank_card_id' => 'required',
        'amount' => 'required|numeric|min:1',
    ]);

    $context = $service->buildContextFromMerchantWithBankCard($request, auth()->user());
    $result = $service->execute($context);

    abort_if(!$result->transaction, Response::HTTP_BAD_REQUEST, '代付失败');

    return Withdraw::make($result->getTransaction());
}
```

Also update the use statements at the top:

```php
use App\Services\Withdraw\WithdrawService;
```

**Step 2: Commit**

```bash
git add api/app/Http/Controllers/Merchant/WithdrawController.php
git commit -m "refactor(withdraw): simplify Merchant/WithdrawController store method

Delegate store logic to WithdrawService
Reduced store method from ~280 lines to ~15 lines
Other methods (index, show, update, exportCsv) unchanged"
```

---

## Task 11: Final Verification and Cleanup

**Step 1: Verify all files exist**

Run: `ls -la api/app/Services/Withdraw/`

Expected output should show:
- `BaseWithdrawService.php`
- `AgencyWithdrawService.php`
- `WithdrawService.php`
- `ThirdChannelDispatcher.php`
- `DTO/` directory
- `Exceptions/` directory

**Step 2: Check for syntax errors**

Run: `php -l api/app/Services/Withdraw/*.php api/app/Services/Withdraw/DTO/*.php api/app/Services/Withdraw/Exceptions/*.php`

Expected: No syntax errors

**Step 3: Final commit with summary**

```bash
git add -A
git commit -m "refactor(withdraw): complete withdraw controller refactoring

Summary of changes:
- Created BaseWithdrawService with Template Method pattern
- Created AgencyWithdrawService and WithdrawService
- Created ThirdChannelDispatcher for third-party channel routing
- Created DTO classes (WithdrawContext, WithdrawResult, ThirdPartyErrorResponse)
- Created WithdrawValidationException for error handling
- Refactored all 4 withdraw controllers to use new services

Code reduction: ~710 lines (47% reduction)
- ThirdParty/AgencyWithdrawController: 500 -> 25 lines
- ThirdParty/WithdrawController: 370 -> 25 lines
- Merchant/AgencyWithdrawController: 370 -> 20 lines
- Merchant/WithdrawController (store): 280 -> 15 lines"
```

---

## Post-Implementation Notes

### Testing Recommendations

After implementation, create tests for:

1. **Unit Tests:**
   - `WithdrawContextTest` - test DTO methods
   - `ThirdPartyErrorResponseTest` - test factory methods
   - `ThirdChannelDispatcherTest` - mock third-party APIs

2. **Feature Tests:**
   - `ThirdPartyAgencyWithdrawTest` - test API endpoint
   - `ThirdPartyWithdrawTest` - test API endpoint
   - `MerchantAgencyWithdrawTest` - test portal endpoint
   - `MerchantWithdrawTest` - test portal endpoint

### Rollback Plan

If issues arise, the original controllers are preserved in git history. To rollback:

```bash
git revert HEAD~N  # where N is number of commits to revert
```
