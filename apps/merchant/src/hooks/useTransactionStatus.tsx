import { Select as AntdSelect, SelectProps } from "@pankod/refine-antd";
import { useTranslate } from "@pankod/refine-core";

type Options = NonNullable<SelectProps["options"]>;
type Option = Options[0];

function useTransactionStatus() {
    const t = useTranslate();
    const Status = {
        已建立: 1,
        匹配中: 2,
        等待付款: 3,
        成功: 4,
        手动成功: 5,
        匹配超时: 6,
        付款超时: 7,
        失败: 8,
        三方处理中: 11,
    };

    const getStatusText = (status: number) => {
        switch (status) {
            case Status.已建立:
                return t("collection.status.established");
            case Status.匹配中:
                return t("collection.status.matching");
            case Status.等待付款:
                return t("collection.status.paying");
            case Status.成功:
                return t("collection.status.success");
            case Status.手动成功:
                return t("collection.status.manualSuccess");
            case Status.匹配超时:
                return t("collection.status.matchedTimeout");
            case Status.付款超时:
                return t("collection.status.paidTimeout");
            case Status.失败:
                return t("collection.status.fail");
            case Status.三方处理中:
                return t("collection.status.paying");
            default:
                return "";
        }
    };

    const Select = (props: SelectProps) => {
        return (
            <AntdSelect
                options={Object.values(Status).map<Option>((value) => ({
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

export default useTransactionStatus;
