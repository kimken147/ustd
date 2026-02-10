<?php

namespace App\Services\Transaction;

use App\Models\Bank;
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

    public function __construct(
        BCMathUtil $bcMath,
        FeatureToggleRepository $featureToggleRepository,
        bool $cancelPaufen
    ) {
        $this->bcMath = $bcMath;
        $this->featureToggleRepository = $featureToggleRepository;
        $this->cancelPaufen = $cancelPaufen;
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

        TransactionFee::insertOnDuplicateKey($transactionFees->toArray());
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

        $lastProviderIdx = count($users) - 1;

        $bank = Bank::firstWhere(
            "name",
            $transaction->from_channel_account["bank_name"]
        );

        $withdrawFeeSet = $users->map(function (User $endUser) use (
            $transaction,
            $agency,
            $bank,
            $type
        ) {
            throw_if(
                !$endUser->wallet,
                new RuntimeException("Wallet not found " . $endUser->getKey())
            );

            $fee = 0;
            $needExtraWithdrawFee = $bank ? $bank->needExtraWithdrawFee : false;
            if ($agency) {
                $fee = $endUser->wallet->calculateTotalAgencyWithdrawFee(
                    $transaction->amount,
                    $needExtraWithdrawFee
                );
            } else {
                $fee = $endUser->wallet->calculateTotalWithdrawFee(
                    $transaction->amount,
                    $needExtraWithdrawFee,
                 );
            }

            return $fee;
        });

        foreach ($users as $idx => $user) {
            // agents
            if ($idx !== $lastProviderIdx) {
                $withdrawProfit = $this->bcMath->subMinZero(
                    $withdrawFeeSet[$idx + 1],
                    $withdrawFeeSet[$idx]
                );

                $transaction->transactionFees()->create([
                    "user_id" => $user->getKey(),
                    "account_mode" => $user->account_mode,
                    "profit" => $withdrawProfit,
                    "actual_profit" => 0,
                    "fee" => 0,
                    "actual_fee" => 0,
                ]);
            } else {
                $transaction->transactionFees()->create([
                    "user_id" => $user->getKey(),
                    "account_mode" => $user->account_mode,
                    "profit" => 0,
                    "actual_profit" => 0,
                    "fee" => $withdrawFeeSet[$idx],
                    "actual_fee" => 0,
                ]);
            }
        }

        // 系統利潤
        $systemProfit = $withdrawFeeSet->first(); // 總代的提現手續費就是系統利潤

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

            $thridChannelFee = $this->bcMath->sum([
                $this->bcMath->mulPercent(
                    $transaction->amount,
                    $merchantThirdChannel->daifu_fee_percent
                ), // 代付手續費為 0.X% + 單筆N元
                $merchantThirdChannel->withdraw_fee,
            ]);
            $transaction->transactionFees()->create([
                "user_id" => 0,
                "thirdchannel_id" => $transaction->thirdChannel->id,
                "profit" => 0,
                "actual_profit" => 0,
                "fee" => $thridChannelFee,
                "actual_fee" => 0,
            ]);
            $systemProfit = $this->bcMath->subMinZero(
                $systemProfit,
                $thridChannelFee
            );
        }

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
            $lastProviderIdx = count($providers) - 1;

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

            foreach ($providers as $idx => $provider) {
                // agents
                if ($idx !== $lastProviderIdx) {
                    $profitFeePercent = $this->bcMath->subMinZero(
                        $providerFeePercentSet[$idx],
                        $providerFeePercentSet[$idx + 1]
                    );

                    $transactionFeeValues[] = [
                        "user_id" => $provider->getKey(),
                        "account_mode" => $provider->account_mode,
                        "thirdchannel_id" => null,
                        "transaction_id" => $transaction->getKey(),
                        "profit" => $this->bcMath->mulPercent(
                            $transaction->floating_amount,
                            $profitFeePercent
                        ),
                        "actual_profit" => 0,
                        "fee" => 0,
                        "actual_fee" => 0,
                    ];
                } else {
                    $profitFeePercent = $providerFeePercentSet[$idx];

                    $transactionFeeValues[] = [
                        "user_id" => $provider->getKey(),
                        "account_mode" => $provider->account_mode,
                        "thirdchannel_id" => null,
                        "transaction_id" => $transaction->getKey(),
                        "profit" => $this->bcMath->mulPercent(
                            $transaction->floating_amount,
                            $profitFeePercent
                        ),
                        "actual_profit" => 0,
                        // 信用線手續費為 0
                        "fee" => $this->bcMath->gtZero($profitFeePercent)
                            ? $this->bcMath->subMinZero(
                                $transaction->floating_amount,
                                $this->bcMath->mulPercent(
                                    $transaction->floating_amount,
                                    $profitFeePercent
                                )
                            )
                            : 0,
                        "actual_fee" => 0,
                    ];
                }
            }
        }

        // 計算商戶手續費
        $merchants = $this->ancestorsAndSelf($transaction->to, true);
        $lastMerchantIdx = count($merchants) - 1;

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

        foreach ($merchants as $idx => $merchant) {
            // agents
            if ($idx !== $lastMerchantIdx) {
                $profitFeePercent = $this->bcMath->subMinZero(
                    $merchantFeePercentSet[$idx + 1],
                    $merchantFeePercentSet[$idx]
                );

                $transactionFeeValues[] = [
                    "user_id" => $merchant->getKey(),
                    "account_mode" => $merchant->account_mode,
                    "thirdchannel_id" => null,
                    "transaction_id" => $transaction->getKey(),
                    "profit" => $this->bcMath->mulPercent(
                        $transaction->amount,
                        $profitFeePercent
                    ),
                    "actual_profit" => 0,
                    "fee" => 0,
                    "actual_fee" => 0,
                ];
            } else {
                $profitFeePercent = $merchantFeePercentSet[$idx];

                $transactionFeeValues[] = [
                    "user_id" => $merchant->getKey(),
                    "account_mode" => $merchant->account_mode,
                    "thirdchannel_id" => null,
                    "transaction_id" => $transaction->getKey(),
                    "profit" => 0,
                    "actual_profit" => 0,
                    "fee" => $this->bcMath->mulPercent(
                        $transaction->amount,
                        $profitFeePercent
                    ),
                    "actual_fee" => 0,
                ];
            }
        }

        // 計算系統手續費
        $systemProfitFeePercent = $this->bcMath->subMinZero(
            $merchantFeePercentSet[0],
            $providerFeePercentSet[0]
        );

        $transactionFeeValues[] = [
            "user_id" => 0, // system
            "account_mode" => null,
            "thirdchannel_id" => null,
            "transaction_id" => $transaction->getKey(),
            "profit" => $this->bcMath->subMinZero(
                $this->bcMath->mulPercent(
                    $transaction->amount,
                    $systemProfitFeePercent
                ),
                $this->bcMath->absDelta(
                    $transaction->amount,
                    $transaction->floating_amount
                )
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
        $childRatio = $transaction->amount / $parent->amount;

        foreach ($parent->transactionFees as $parentTransactionFee) {
            $transaction->transactionFees()->create([
                "user_id" => $parentTransactionFee->user_id,
                "account_mode" => $parentTransactionFee->account_mode,
                "profit" => $this->bcMath->mul(
                    $parentTransactionFee->profit,
                    $childRatio
                ),
                "actual_profit" => $this->bcMath->mul(
                    $parentTransactionFee->acutal_profit,
                    $childRatio
                ),
                "fee" => $this->bcMath->mul(
                    $parentTransactionFee->fee,
                    $childRatio
                ),
                "actual_fee" => $this->bcMath->mul(
                    $parentTransactionFee->actual_fee,
                    $childRatio
                ),
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
