import { UserChannelStatus, UserChannelType } from "../interfaces";

export const getChannelTypeText = (type: UserChannelType) => {
    if (type === UserChannelType.收出款) return "收出款";
    else if (type === UserChannelType.收款) return "收款";
    else return "出款";
};

export const getChannelAccountStatus = (status: string) => {
    if (status === "pass") return "通过";
    else if (status === "unverified") return "未认证";
    else if (status === "fail") return "失败／冻结";
};

export const getChannelStatusText = (status: UserChannelStatus) => {
    if (status === UserChannelStatus.强制下线) return "强制下线";
    else if (status === UserChannelStatus.下线) return "下线";
    else return "上线";
};

export const ChannelCode = {
    USDT: "USDT",
};
