import { Descriptions, Divider } from "antd";
import { DateField, RefreshButton, Show, TextField } from "@refinedev/antd";
import { IResourceComponentsProps, useShow } from "@refinedev/core";
import { useLocation } from "react-router-dom";
import useEnableStatusSelect from "hooks/useEnableStatusSwitch";
import { Member } from "interfaces/member";
import { Format } from "@morgan-ustd/shared";
import { FC } from "react";
import { Helmet } from "react-helmet";

const MemberShow: FC<IResourceComponentsProps<Member>> = () => {
    const { state } = useLocation();
    const { Switch } = useEnableStatusSelect();
    const { queryResult } = useShow<Member>();
    const { data, isLoading } = queryResult;
    const record = { ...(state as Member), ...data?.data };
    return (
        <>
            <Helmet>
                <title>下级帐号详细</title>
            </Helmet>
            <Show
                title="下级帐号详细"
                isLoading={isLoading}
                headerButtons={() => (
                    <>
                        <RefreshButton>刷新</RefreshButton>
                    </>
                )}
            >
                <Descriptions column={{ xs: 1, md: 2 }} bordered title="帐号相关">
                    <Descriptions.Item label="登录帐号">{record?.username}</Descriptions.Item>
                    <Descriptions.Item label="谷歌验证码启用开关">
                        <Switch checked={record?.google2fa_enable} disabled />
                    </Descriptions.Item>
                    <Descriptions.Item label="最后登录时间">
                        {record?.last_login_at ? <DateField value={record?.last_login_at} format={Format} /> : "无"}
                    </Descriptions.Item>
                    <Descriptions.Item label="IP">{record?.last_login_ipv4 ?? "无"}</Descriptions.Item>
                    {record?.password ? (
                        <Descriptions.Item label="密码">
                            <TextField value={record.password} copyable />
                        </Descriptions.Item>
                    ) : null}
                    {record?.google2fa_secret ? (
                        <Descriptions.Item label="谷歌验证码密钥">
                            <TextField value={record.google2fa_secret} copyable />
                        </Descriptions.Item>
                    ) : null}
                    {record?.google2fa_qrcode ? (
                        <Descriptions.Item label="谷歌验证码 QR Code">
                            <div
                                dangerouslySetInnerHTML={{
                                    __html: record?.google2fa_qrcode,
                                }}
                            />
                        </Descriptions.Item>
                    ) : null}
                </Descriptions>
                <Divider />
                <Descriptions column={{ xs: 1, md: 2 }} bordered title="功能开关">
                    <Descriptions.Item label="帐号启用开关">
                        <Switch checked={!!record?.status} disabled />
                    </Descriptions.Item>
                    <Descriptions.Item label="代理功能启用开关">
                        <Switch checked={record?.agent_enable} disabled />
                    </Descriptions.Item>
                </Descriptions>
                <Divider />
                <Descriptions column={{ xs: 1, md: 3 }} bordered title="钱包相关">
                    <Descriptions.Item label="总余额">{record?.wallet?.balance}</Descriptions.Item>
                    <Descriptions.Item label="可用余额">{record?.wallet?.available_balance}</Descriptions.Item>
                    <Descriptions.Item label="冻结余额">{record?.wallet?.frozen_balance}</Descriptions.Item>
                </Descriptions>
                <Divider />
            </Show>
        </>
    );
};

export default MemberShow;
