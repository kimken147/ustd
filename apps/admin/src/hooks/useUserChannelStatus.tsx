import { Select } from "antd";
import type { SelectProps } from "antd";
import { useTranslation } from "react-i18next";
import { UserChannelType } from "@morgan-ustd/shared";

const useUserChannelStatus = () => {
    const { t } = useTranslation("userChannel");
    const userChannelStatus = {
        收出款: 1,
        收款: 2,
        出款: 3,
    };
    const getChannelTypeText = (type: UserChannelType) => {
        switch (type) {
            case UserChannelType.收出款:
                return t("type.collectionAndPayout");
            case UserChannelType.收款:
                return t("type.collection");
            case UserChannelType.出款:
                return t("type.payout");
            default:
                return "";
        }
    };
    const selectProps: SelectProps = {
        options: [userChannelStatus.收出款, userChannelStatus.收款, userChannelStatus.出款].map((type) => ({
            label: getChannelTypeText(type),
            value: type,
        })),
    };

    const AntSelect = (props: SelectProps) => <Select {...selectProps} {...props} />;

    return {
        selectProps,
        getChannelTypeText,
        userChannelStatus,
        Select: AntSelect,
    };
};

export default useUserChannelStatus;
