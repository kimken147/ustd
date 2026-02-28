<?php

namespace App\Services\Transaction;

use App\Utils\BCMathUtil;

class TransactionFeeCalculator
{
    private readonly BCMathUtil $bcMath;

    public function __construct(BCMathUtil $bcMath)
    {
        $this->bcMath = $bcMath;
    }

    /**
     * 計算提現各層級的利潤和手續費分配。
     *
     * 輸入：root→leaf 的費用金額陣列。
     * agent profit = subMinZero(fee[idx+1], fee[idx])，末端 user fee = 自身費用。
     *
     * @param array $feeAmounts root→leaf 的費用金額陣列
     * @return array [['profit' => string, 'fee' => string], ...]
     */
    public function allocateWithdrawFees(array $feeAmounts): array
    {
        $lastIdx = count($feeAmounts) - 1;
        $result = [];

        foreach ($feeAmounts as $idx => $fee) {
            if ($idx !== $lastIdx) {
                $result[] = [
                    'profit' => $this->bcMath->subMinZero($feeAmounts[$idx + 1], $feeAmounts[$idx]),
                    'fee' => '0',
                ];
            } else {
                $result[] = [
                    'profit' => '0',
                    'fee' => $fee,
                ];
            }
        }

        return $result;
    }

    /**
     * 計算三方通道手續費。
     *
     * 邏輯：sum([mulPercent(amount, percent), withdrawFee])
     */
    public function calculateThirdChannelFee(string $amount, string $daifuFeePercent, string $withdrawFee): string
    {
        return $this->bcMath->sum([
            $this->bcMath->mulPercent($amount, $daifuFeePercent),
            $withdrawFee,
        ]);
    }

    /**
     * 計算提現系統利潤。
     *
     * 無三方 → 直接回傳 topLevelFee；有三方 → subMinZero(topLevelFee, thirdChannelFee)
     */
    public function calculateWithdrawSystemProfit(string $topLevelFee, ?string $thirdChannelFee = null): string
    {
        if ($thirdChannelFee === null) {
            return $topLevelFee;
        }

        return $this->bcMath->subMinZero($topLevelFee, $thirdChannelFee);
    }

    /**
     * 計算碼商各層級的利潤和手續費分配。
     *
     * 方向：parent% - child%（碼商方向）。
     * 末端碼商：profit = mulPercent(floating, percent)，fee = gtZero(percent) ? subMinZero(floating, profit) : 0
     *
     * @param string $floatingAmount 浮動金額
     * @param array $feePercents root→leaf 的費率陣列
     * @return array [['profit' => string, 'fee' => string], ...]
     */
    public function allocateProviderFees(string $floatingAmount, array $feePercents): array
    {
        $lastIdx = count($feePercents) - 1;
        $result = [];

        foreach ($feePercents as $idx => $percent) {
            if ($idx !== $lastIdx) {
                $profitFeePercent = $this->bcMath->subMinZero($feePercents[$idx], $feePercents[$idx + 1]);
                $result[] = [
                    'profit' => $this->bcMath->mulPercent($floatingAmount, $profitFeePercent),
                    'fee' => '0',
                ];
            } else {
                $profitFeePercent = $feePercents[$idx];
                $profit = $this->bcMath->mulPercent($floatingAmount, $profitFeePercent);
                $result[] = [
                    'profit' => $profit,
                    'fee' => $this->bcMath->gtZero($profitFeePercent)
                        ? $this->bcMath->subMinZero($floatingAmount, $profit)
                        : '0',
                ];
            }
        }

        return $result;
    }

    /**
     * 計算商戶各層級的利潤和手續費分配。
     *
     * 方向：child% - parent%（商戶方向，與碼商相反）。
     * 末端商戶：fee = mulPercent(amount, percent)，profit = 0
     *
     * @param string $amount 交易金額
     * @param array $feePercents root→leaf 的費率陣列
     * @return array [['profit' => string, 'fee' => string], ...]
     */
    public function allocateMerchantFees(string $amount, array $feePercents): array
    {
        $lastIdx = count($feePercents) - 1;
        $result = [];

        foreach ($feePercents as $idx => $percent) {
            if ($idx !== $lastIdx) {
                $profitFeePercent = $this->bcMath->subMinZero($feePercents[$idx + 1], $feePercents[$idx]);
                $result[] = [
                    'profit' => $this->bcMath->mulPercent($amount, $profitFeePercent),
                    'fee' => '0',
                ];
            } else {
                $result[] = [
                    'profit' => '0',
                    'fee' => $this->bcMath->mulPercent($amount, $feePercents[$idx]),
                ];
            }
        }

        return $result;
    }

    /**
     * 計算 Paufen 系統利潤。
     *
     * 邏輯：subMinZero(mulPercent(amount, subMinZero(merchant%, provider%)), absDelta(amount, floatingAmount))
     */
    public function calculatePaufenSystemProfit(
        string $amount,
        string $floatingAmount,
        string $topMerchantPercent,
        string $topProviderPercent
    ): string {
        $systemProfitFeePercent = $this->bcMath->subMinZero($topMerchantPercent, $topProviderPercent);

        return $this->bcMath->subMinZero(
            $this->bcMath->mulPercent($amount, $systemProfitFeePercent),
            $this->bcMath->absDelta($amount, $floatingAmount)
        );
    }

    /**
     * 按比例縮放父交易的費用分配到子交易。
     *
     * 每個 fee row 的 profit/actual_profit/fee/actual_fee 乘以 childRatio。
     * 使用 bcMath->div() 計算 ratio 避免 PHP float 精度問題。
     *
     * @param array $parentFees [['profit' => string, 'actual_profit' => string, 'fee' => string, 'actual_fee' => string], ...]
     * @param string $childAmount 子交易金額
     * @param string $parentAmount 父交易金額
     * @return array 縮放後的費用陣列
     */
    public function scaleFeesProportionally(array $parentFees, string $childAmount, string $parentAmount): array
    {
        $childRatio = $this->bcMath->div($childAmount, $parentAmount);
        $result = [];

        foreach ($parentFees as $parentFee) {
            $result[] = [
                'profit' => $this->bcMath->mul($parentFee['profit'], $childRatio),
                'actual_profit' => $this->bcMath->mul($parentFee['actual_profit'], $childRatio),
                'fee' => $this->bcMath->mul($parentFee['fee'], $childRatio),
                'actual_fee' => $this->bcMath->mul($parentFee['actual_fee'], $childRatio),
            ];
        }

        return $result;
    }
}
