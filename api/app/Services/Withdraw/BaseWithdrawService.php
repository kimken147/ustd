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
use App\DTOs\TransactionParams;
use App\Events\NewWithdrawCreated;
use App\Utils\BankCardTransferObject;
use App\Utils\BCMathUtil;
use App\Utils\FloatUtil;
use App\Utils\NotificationUtil;
use App\Utils\SignatureCalculator;
use App\Utils\TransactionFactory;
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
        protected readonly NotificationUtil $notificationUtil,
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
                $request->input('network'),
                $request->input('pay_address'),
                $request->input('payee_name') ?? '',
                $request->input('province') ?? '',
                $request->input('city') ?? ''
            ),
            orderNumber: $request->input('out_trade_no'),
            notifyUrl: $request->input('callback_url'),
            source: WithdrawContext::SOURCE_THIRD_PARTY,
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

        // 5. 即時通知（WebSocket + Telegram）
        $transaction->load('from');
        NewWithdrawCreated::dispatch($transaction);

        $toAccount = $transaction->to_channel_account ?? [];
        $this->notificationUtil->notifyNewWithdraw(
            $transaction->system_order_number ?? '',
            $transaction->from->username ?? '',
            $transaction->amount,
            $toAccount['bank_name'] ?? '',
            $transaction->sub_type
        );

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
            'merchant_id',
            'amount',
            'pay_address',
            'network',
            'out_trade_no',
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
            ['username', $request->input('merchant_id')],
            ['role', User::ROLE_MERCHANT],
        ])->first();

        if (!$merchant) {
            throw WithdrawValidationException::fromThirdParty(
                ThirdPartyErrorResponse::userNotFound()
            );
        }

        $parameters = $request->except('sign');

        if (!SignatureCalculator::verify($parameters, $merchant->secret_key, $request->input('sign'))) {
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
        $params = new TransactionParams(
            amount: $context->amount,
            bankCard: $context->bankCard,
            notifyUrl: $context->notifyUrl,
            orderNumber: $context->orderNumber,
            subType: $this->getSubType(),
        );

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
            onThirdChannelSuccess: fn($channelId) => $this->transactionFactory->thirdchannelWithdrawFrom(
                $params,
                $context->merchant,
                $this->getSubType() === Transaction::SUB_TYPE_AGENCY_WITHDRAW,
                null,
                $channelId
            ),
            onLocalFallback: fn() => $this->transactionFactory->$withdrawMethod(
                $params,
                $context->merchant,
                $this->getSubType() === Transaction::SUB_TYPE_AGENCY_WITHDRAW
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

}
