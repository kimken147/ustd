import { Select as AntdSelect } from "antd";
import type { SelectProps } from "antd";
import { SelectOption } from "@morgan-ustd/shared";
import { useTranslation } from "react-i18next";

function useTransactionCallbackStatus() {
    const { t } = useTranslation('transaction');
    
    const Status = {
        未通知: 0,
        通知中: 1,
        已通知: 2,
        成功: 3,
        失败: 4,
        三方处理中: 11,
    };

    const getStatusText = (status: number) => {
        switch (status) {
            case Status.未通知:
                return t('callbackStatus.notNotified');
            case Status.通知中:
                return t('callbackStatus.notifying');
            case Status.已通知:
                return t('callbackStatus.notified');
            case Status.成功:
                return t('callbackStatus.success');
            case Status.失败:
                return t('callbackStatus.failed');
            case Status.三方处理中:
                return t('callbackStatus.thirdPartyProcessing');
            default:
                return "";
        }
    };

    const Select = (props: SelectProps) => {
        return (
            <AntdSelect
                options={Object.values(Status).map<SelectOption>((value) => ({
                    label: getStatusText(value),
                    value,
                }))}
                allowClear
                {...props}
            />
        );
    };

    return {
        Select,
        getStatusText,
        Status,
    };
}

export default useTransactionCallbackStatus;
