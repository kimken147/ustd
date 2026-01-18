import { SelectProps, Select as AntdSelect } from "@pankod/refine-antd";
import { useTranslate } from "@pankod/refine-core";
import { SelectOption } from "@morgan-ustd/shared";

function useTransactionCallbackStatus() {
    const t = useTranslate();
    const Status = {
        未通知: 0,
        通知中: 1,
        已通知: 2,
        成功: 3,
        失败: 4,
    };

    const getStatusText = (status: number) => {
        switch (status) {
            case Status.未通知:
                return t("collection.callbackStatus.notNotified");
            case Status.通知中:
                return t("collection.callbackStatus.wait");
            case Status.已通知:
                return t("collection.callbackStatus.sending");
            case Status.成功:
                return t("collection.callbackStatus.success");
            case Status.失败:
                return t("collection.callbackStatus.fail");
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
