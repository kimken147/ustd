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
