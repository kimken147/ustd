import { useTranslation } from "react-i18next";
import { UserChannelStatus, UserChannelType } from "@morgan-ustd/shared";

function useChannelStatus() {
    const { t } = useTranslation("userChannel");
    
    const Status = {
        强制下线: 0,
        下线: 1,
        上线: 2,
    };

    const Type = {
        收出款: 1,
        收款: 2,
        出款: 3,
    };

    const getChannelStatusText = (status: UserChannelStatus) => {
        switch (status) {
            case Status.强制下线:
                return t("status.forcedOffline");
            case Status.下线:
                return t("status.offline");
            case Status.上线:
                return t("status.online");
            default:
                return "";
        }
    };

    const getChannelTypeText = (type: UserChannelType) => {
        switch (type) {
            case Type.收出款:
                return t("type.collectionAndPayout");
            case Type.收款:
                return t("type.collection");
            case Type.出款:
                return t("type.payout");
            default:
                return "";
        }
    };

    return {
        getChannelStatusText,
        getChannelTypeText,
        Status,
        Type,
    };
}

export default useChannelStatus;

