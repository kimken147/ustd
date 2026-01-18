import { UserChannelStatus, UserChannelType } from "interfaces/userChannel";

export const getChannelTypeText = (type: UserChannelType) => {
    if (type === UserChannelType.收出款) return "收出款";
    else if (type === UserChannelType.收款) return "收款";
    else return "出款";
};

export const getChannelAccountStatus = (status: string) => {
    if (status === "pass") return "通過";
    else if (status === "unverified") return "未認證";
    else if (status === "fail") return "失敗／凍結";
};

export const getChannelStatusText = (status: UserChannelStatus) => {
    if (status === UserChannelStatus.強制下線) return "強制下線";
    else if (status === UserChannelStatus.下線) return "下線";
    else return "上線";
};
