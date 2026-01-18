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
