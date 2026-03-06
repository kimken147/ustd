<?php

namespace App\Services\Transaction;

use App\Models\ChannelGroup;
use App\Models\FeatureToggle;
use App\Models\MerchantThirdChannel;
use App\Models\Transaction;
use App\Models\TransactionFee;
use App\Models\User;
use App\Repository\FeatureToggleRepository;
use App\Utils\BCMathUtil;
use Illuminate\Http\Response;
use RuntimeException;

class TransactionFeeService
{
    private $bcMath;
    private $featureToggleRepository;
    private $cancelPaufen;
    private $calculator;

    public function __construct(
        BCMathUtil $bcMath,
        FeatureToggleRepository $featureToggleRepository,
        bool $cancelPaufen,
        TransactionFeeCalculator $calculator
    ) {
        $this->bcMath = $bcMath;
        $this->featureToggleRepository = $featureToggleRepository;
        $this->cancelPaufen = $cancelPaufen;
        $this->calculator = $calculator;
    }

    public function createDepositFees(
        $transaction,
        User $endUser,
        $withSystem = true,
        ?Transaction $parent = null
    ) {
        $users = $this->ancestorsAndSelf($endUser);

        $transactionFees = collect();

        foreach ($users as $user) {
            $transactionFees->add([
                "transaction_id" => $transaction->getKey(),
                "user_id" => $user->getKey(),
                "account_mode" => $user->account_mode,
                "profit" => 0,
                "actual_profit" => 0,
                "fee" => 0,
                "actual_fee" => 0,
                "deleted_at" => null,
            ]);
        }

        if ($withSystem) {
            $transactionFees->add([
                "transaction_id" => $transaction->getKey(),
                "user_id" => 0, // system
                "account_mode" => null,
                "profit" => 0,
                "actual_profit" => 0,
                "fee" => 0,
                "actual_fee" => 0,
                "deleted_at" => null,
            ]);
        }

        TransactionFee::insert($transactionFees->toArray());
    }

    public function createWithdrawFees(
        Transaction $transaction,
        User $endUser,
        $agency = false,
        ?Transaction $parent = null,
        $type = "balance"
    ) {
        if ($parent) {
            $this->createSeparateWithdrawFees(
                $transaction,
                $endUser,
                $parent
            );
            return;
        }

        $featureToggleRepository = app(FeatureToggleRepository::class);
        if (
            $featureToggleRepository->enabled(
                FeatureToggle::AGENT_WITHDRAW_PROFIT
            )
        ) {
            $users = $this->ancestorsAndSelf($endUser, true);
        } else {
            $users = collect([$endUser]); // 改成手續費全部給系統
        }

        $withdrawFeeSet = $users->map(function (User $endUser) use (
            $transaction,
            $agency,
            $type
        ) {
            throw_if(
                !$endUser->wallet,
                new RuntimeException("Wallet not found " . $endUser->getKey())
            );

            if ($agency) {
                return $endUser->wallet->calculateTotalAgencyWithdrawFee(
                    $transaction->amount
                );
            }

            return $endUser->wallet->calculateTotalWithdrawFee(
                $transaction->amount,
            );
        });

        $allocations = $this->calculator->allocateWithdrawFees($withdrawFeeSet->toArray());

        foreach ($users as $idx => $user) {
            $transaction->transactionFees()->create([
                "user_id" => $user->getKey(),
                "account_mode" => $user->account_mode,
                "profit" => $allocations[$idx]['profit'],
                "actual_profit" => 0,
                "fee" => $allocations[$idx]['fee'],
                "actual_fee" => 0,
            ]);
        }

        // 系統利潤
        $systemProfit = $withdrawFeeSet->first(); // 總代的提現手續費就是系統利潤
        $thirdChannelFee = null;

        if ($transaction->thirdchannel_id) {
            // 如果是使用三方提現，需要扣除三方手續費
            $merchantThirdChannel = MerchantThirdChannel::where(
                "thirdchannel_id",
                $transaction->thirdChannel->id
            )
                ->where("owner_id", $transaction->from_id)
                ->where("daifu_min", "<=", $transaction->amount)
                ->where("daifu_max", ">=", $transaction->amount)
                ->first();

            abort_if(
                !$merchantThirdChannel,
                Response::HTTP_BAD_REQUEST,
                "请检查三方通道是否设置"
            );

            $thirdChannelFee = $this->calculator->calculateThirdChannelFee(
                $transaction->amount,
                $merchantThirdChannel->daifu_fee_percent,
                $merchantThirdChannel->withdraw_fee
            );
            $transaction->transactionFees()->create([
                "user_id" => 0,
                "thirdchannel_id" => $transaction->thirdChannel->id,
                "profit" => 0,
                "actual_profit" => 0,
                "fee" => $thirdChannelFee,
                "actual_fee" => 0,
            ]);
        }

        $systemProfit = $this->calculator->calculateWithdrawSystemProfit($systemProfit, $thirdChannelFee);

        $transaction->transactionFees()->create([
            "user_id" => 0,
            "profit" => $systemProfit,
            "actual_profit" => 0,
            "fee" => 0,
            "actual_fee" => 0,
        ]);
    }

    public function createPaufenTransactionFees(
        Transaction $transaction,
        ChannelGroup $channelGroup
    ) {
        $transactionFeeValues = [];

        // 計算碼商手續費
        $providerFeePercentSet = [0];
        if ($transaction->thirdchannel_id) {
            # 如果是三方，則需計算三方代收費率
            $thirdChannel = $transaction->thirdChannel;
            $merchantThirdChannel = MerchantThirdChannel::where(
                "thirdchannel_id",
                $thirdChannel->id
            )
                ->where("owner_id", $transaction->to_id)
                ->where("deposit_min", "<=", $transaction->amount)
                ->where("deposit_max", ">=", $transaction->amount)
                ->first();

            abort_if(
                !$merchantThirdChannel,
                Response::HTTP_BAD_REQUEST,
                "请检查三方通道是否设置"
            );

            $providerFeePercentSet = [
                $merchantThirdChannel->deposit_fee_percent,
            ];
            $transactionFeeValues[] = [
                "user_id" => 0,
                "account_mode" => null,
                "thirdchannel_id" => $thirdChannel->id,
                "transaction_id" => $transaction->getKey(),
                "profit" => 0,
                "actual_profit" => 0,
                "fee" => $this->bcMath->mulPercent(
                    $transaction->floating_amount,
                    $merchantThirdChannel->deposit_fee_percent
                ),
                "actual_fee" => 0,
            ];
        } elseif (!$this->cancelPaufen) {
            // 非免簽模式，才需要計算碼商利潤
            $providers = $this->ancestorsAndSelf($transaction->from);

            $providerFeePercentSet = $providers->map(function (
                User $provider
            ) use ($channelGroup) {
                $providerUserChannel = $provider->userChannels
                    ->where("channel_group_id", $channelGroup->getKey())
                    ->first();

                throw_if(
                    !$providerUserChannel,
                    new RuntimeException("Provider user channel not found")
                );

                return $providerUserChannel->fee_percent;
            });

            $providerAllocations = $this->calculator->allocateProviderFees(
                $transaction->floating_amount,
                $providerFeePercentSet->toArray()
            );

            foreach ($providers as $idx => $provider) {
                $transactionFeeValues[] = [
                    "user_id" => $provider->getKey(),
                    "account_mode" => $provider->account_mode,
                    "thirdchannel_id" => null,
                    "transaction_id" => $transaction->getKey(),
                    "profit" => $providerAllocations[$idx]['profit'],
                    "actual_profit" => 0,
                    "fee" => $providerAllocations[$idx]['fee'],
                    "actual_fee" => 0,
                ];
            }
        }

        // 計算商戶手續費
        $merchants = $this->ancestorsAndSelf($transaction->to, true);

        $merchantFeePercentSet = $merchants->map(function (User $merchant) use (
            $channelGroup
        ) {
            $merchantUserChannel = $merchant->userChannels
                ->where("channel_group_id", $channelGroup->getKey())
                ->first();

            throw_if(
                !$merchantUserChannel,
                new RuntimeException("Merchant user channel not found")
            );

            return $merchantUserChannel->fee_percent;
        });

        $merchantAllocations = $this->calculator->allocateMerchantFees(
            $transaction->amount,
            $merchantFeePercentSet->toArray()
        );

        foreach ($merchants as $idx => $merchant) {
            $transactionFeeValues[] = [
                "user_id" => $merchant->getKey(),
                "account_mode" => $merchant->account_mode,
                "thirdchannel_id" => null,
                "transaction_id" => $transaction->getKey(),
                "profit" => $merchantAllocations[$idx]['profit'],
                "actual_profit" => 0,
                "fee" => $merchantAllocations[$idx]['fee'],
                "actual_fee" => 0,
            ];
        }

        // 計算系統手續費
        $transactionFeeValues[] = [
            "user_id" => 0, // system
            "account_mode" => null,
            "thirdchannel_id" => null,
            "transaction_id" => $transaction->getKey(),
            "profit" => $this->calculator->calculatePaufenSystemProfit(
                $transaction->amount,
                $transaction->floating_amount,
                $merchantFeePercentSet[0],
                $providerFeePercentSet[0]
            ),
            "actual_profit" => 0,
            "fee" => 0,
            "actual_fee" => 0,
        ];

        TransactionFee::insert($transactionFeeValues);
    }

    private function createSeparateWithdrawFees(
        Transaction $transaction,
        User $endUser,
        Transaction $parent
    ) {
        $parentFees = $parent->transactionFees->map(function ($fee) {
            return [
                'profit' => $fee->profit,
                'actual_profit' => $fee->acutal_profit,
                'fee' => $fee->fee,
                'actual_fee' => $fee->actual_fee,
            ];
        })->toArray();

        $scaledFees = $this->calculator->scaleFeesProportionally(
            $parentFees,
            $transaction->amount,
            $parent->amount
        );

        foreach ($parent->transactionFees->values() as $idx => $parentTransactionFee) {
            $transaction->transactionFees()->create([
                "user_id" => $parentTransactionFee->user_id,
                "account_mode" => $parentTransactionFee->account_mode,
                "profit" => $scaledFees[$idx]['profit'],
                "actual_profit" => $scaledFees[$idx]['actual_profit'],
                "fee" => $scaledFees[$idx]['fee'],
                "actual_fee" => $scaledFees[$idx]['actual_fee'],
                "deleted_at" => $parentTransactionFee->deleted_at,
            ]);
        }
    }

    private function ancestorsAndSelf(User $user, $withUserChannel = false)
    {
        $users = User::query();

        if ($withUserChannel) {
            $users->with("userChannels");
        }

        return $users->defaultOrder()->ancestorsAndSelf($user);
    }
}
