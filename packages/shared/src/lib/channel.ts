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

export const SyncStatus: Record<string, string> = {
    handshake_fail: "初始失败请重试",
    success: "完成",
    need_mpin: "输入MPIN",
    mpin_processing: "等验MPIN",
    need_otp: "等待OTP",
    otp_fail: "OTP错误",
    device_fail: "设备失败请重试",
    fail: "失败请重试",
    account_unverified: "帐号未认证",
    mpin_fail: "MPIN错误",
    account_limited: "帐号被限制",
};

export const AccountStatus: Record<string, string> = {
    pass: "通过认证",
    fail: "失败/风控",
    unverified: "未认证",
};

export const ChannelCode = {
    GCash: "GCash",
    Maya: "MAYA",
    支轉支: "QR_ALIPAY",
    卡對卡: "BANK_CARD",
};
