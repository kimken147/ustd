import { Select as AntdSelect } from "antd";
import type { SelectProps } from "antd";
import { useTranslation } from "react-i18next";

// 使用 common namespace 來獲取 walletHistory 翻譯
const TRANSLATION_NAMESPACE = "common";

type Options = NonNullable<SelectProps["options"]>;
type Option = Options[0];

const useUserWalletStatus = () => {
    const { t } = useTranslation(TRANSLATION_NAMESPACE);
    const userWalletStatus = {
        系统调整: 1,
        余额转赠: 2,
        入帐: 3,
        预扣: 4,
        预扣退款: 5,
        "快充奖励(红利)": 6,
        "交易奖励(红利)": 7,
        "入帐(扣冻结)": 8,
        "入帐(红利)": 9,
        "系统调整(红利)": 10,
        "系统调整(冻结)": 11,
        提现: 12,
        提现退款: 13,
        入帐退款: 14,
    };

    const getUserWalletStatusText = (type: number) => {
        switch (type) {
            case userWalletStatus.系统调整:
                return t("walletHistory.status.systemAdjustment");
            case userWalletStatus.余额转赠:
                return t("walletHistory.status.balanceTransfer");
            case userWalletStatus.入帐:
                return t("walletHistory.status.deposit");
            case userWalletStatus.预扣:
                return t("walletHistory.status.preDeduct");
            case userWalletStatus.预扣退款:
                return t("walletHistory.status.preDeductRefund");
            case userWalletStatus["快充奖励(红利)"]:
                return t("walletHistory.status.fastChargeRewardProfit");
            case userWalletStatus["交易奖励(红利)"]:
                return t("walletHistory.status.transactionRewardProfit");
            case userWalletStatus["入帐(扣冻结)"]:
                return t("walletHistory.status.depositDeductFrozen");
            case userWalletStatus["入帐(红利)"]:
                return t("walletHistory.status.depositProfit");
            case userWalletStatus["系统调整(红利)"]:
                return t("walletHistory.status.systemAdjustmentProfit");
            case userWalletStatus["系统调整(冻结)"]:
                return t("walletHistory.status.systemAdjustmentFrozen");
            case userWalletStatus.提现:
                return t("walletHistory.status.withdraw");
            case userWalletStatus.提现退款:
                return t("walletHistory.status.withdrawRefund");
            case userWalletStatus.入帐退款:
                return t("walletHistory.status.depositRefund");
            default:
                return "";
        }
    };

    const Select = (props: SelectProps) => {
        return (
            <AntdSelect
                options={Object.values(userWalletStatus).map<Option>((value) => ({
                    label: getUserWalletStatusText(value),
                    value,
                }))}
                allowClear
                {...props}
            />
        );
    };

    return {
        Select,
        getUserWalletStatusText,
        userWalletStatus,
    };
};

export default useUserWalletStatus;
