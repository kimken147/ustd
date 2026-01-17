import { DateField, Descriptions, Divider, RefreshButton, Show, TextField } from "@pankod/refine-antd";
import { IResourceComponentsProps, useShow } from "@pankod/refine-core";
import { useLocation } from "@pankod/refine-react-router-v6";
import useEnableStatusSelect from "hooks/useEnableStatusSwitch";
import { Member } from "interfaces/member";
import { Format } from "lib/date";
import { FC } from "react";
import { Helmet } from "react-helmet";

const MemberShow: FC<IResourceComponentsProps<Member>> = () => {
    const { state } = useLocation();
    const { Switch } = useEnableStatusSelect();
    const { queryResult } = useShow<Member>();
    // const { mutateAsync: update } = useUpdate();
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
                    {/* <Descriptions.Item label="提现开关">
                        <Switch checked={record?.withdraw_enable} disabled />
                    </Descriptions.Item>
                    <Descriptions.Item label="交易开关">
                        <Switch checked={record?.transaction_enable} disabled />
                    </Descriptions.Item> */}
                </Descriptions>
                <Divider />
                <Descriptions column={{ xs: 1, md: 3 }} bordered title="钱包相关">
                    <Descriptions.Item label="总余额">{record?.wallet?.balance}</Descriptions.Item>
                    <Descriptions.Item label="可用余额">{record?.wallet?.available_balance}</Descriptions.Item>
                    <Descriptions.Item label="冻结余额">{record?.wallet?.frozen_balance}</Descriptions.Item>
                    {/* <Descriptions.Item label="下发手续费">{record?.withdraw_fee}元/笔</Descriptions.Item>
                    <Descriptions.Item label="代付手续费">
                        {record?.agency_withdraw_fee || "0.00"}% + {record?.agency_withdraw_fee_dollar}元/笔
                    </Descriptions.Item> */}
                </Descriptions>
                <Divider />
                {/* <Descriptions title="通道相关" column={{ xs: 1, md: 3 }} bordered>
                    {record?.user_channels?.map((userChannel) => (
                        <>
                            <Descriptions.Item label="通道">{userChannel.name}</Descriptions.Item>
                            <Descriptions.Item label="费率(%)">{userChannel.fee_percent || "无设置"}</Descriptions.Item>
                            <Descriptions.Item label="状态">
                                <Switch
                                    disabled
                                    checked={!!userChannel.status}
                                    onChange={async (value) => {
                                        await update({
                                            id: userChannel.id,
                                            resource: "member-user-channels",
                                            values: {
                                                status: value ? 1 : 0,
                                            },
                                            successNotification: {
                                                message: "修改成功",
                                                type: "success",
                                            },
                                        });
                                        refetch();
                                    }}
                                />
                            </Descriptions.Item>
                        </>
                    ))}
                </Descriptions> */}
            </Show>
        </>
    );
};

export default MemberShow;
