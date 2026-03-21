import { Descriptions, Divider } from "antd";
import { DateField, RefreshButton, Show, TextField } from "@refinedev/antd";
import { IResourceComponentsProps, useShow, useTranslate } from "@refinedev/core";
import { useLocation } from "react-router";
import useEnableStatusSelect from "hooks/useEnableStatusSwitch";
import { Member } from "interfaces/member";
import { Format } from "@morgan-ustd/shared";
import { FC } from "react";
import { Helmet } from "react-helmet";

const MemberShow: FC<IResourceComponentsProps<Member>> = () => {
    const t = useTranslate();
    const { state } = useLocation();
    const { Switch } = useEnableStatusSelect();
    const { query } = useShow<Member>();
    const { data, isLoading } = query;
    const record = { ...(state as Member), ...data?.data };
    return (
        <>
            <Helmet>
                <title>{t('member.show.title')}</title>
            </Helmet>
            <Show
                title={t('member.show.title')}
                isLoading={isLoading}
                headerButtons={() => (
                    <>
                        <RefreshButton>{t('member.buttons.refresh')}</RefreshButton>
                    </>
                )}
            >
                <Descriptions column={{ xs: 1, md: 2 }} bordered title={t('member.fields.accountInfo')}>
                    <Descriptions.Item label={t('member.fields.loginAccount')}>{record?.username}</Descriptions.Item>
                    <Descriptions.Item label={t('member.fields.google2faStatus')}>
                        <Switch checked={record?.google2fa_enable} disabled />
                    </Descriptions.Item>
                    <Descriptions.Item label={t('member.fields.lastLoginTime')}>
                        {record?.last_login_at ? <DateField value={record?.last_login_at} format={Format} /> : t('member.fields.none')}
                    </Descriptions.Item>
                    <Descriptions.Item label={t('member.fields.ip')}>{record?.last_login_ipv4 ?? t('member.fields.none')}</Descriptions.Item>
                    {record?.password ? (
                        <Descriptions.Item label={t('member.fields.password')}>
                            <TextField value={record.password} copyable />
                        </Descriptions.Item>
                    ) : null}
                    {record?.google2fa_secret ? (
                        <Descriptions.Item label={t('member.fields.google2faSecret')}>
                            <TextField value={record.google2fa_secret} copyable />
                        </Descriptions.Item>
                    ) : null}
                    {record?.google2fa_qrcode ? (
                        <Descriptions.Item label={t('member.fields.google2faQRCode')}>
                            <div
                                dangerouslySetInnerHTML={{
                                    __html: record?.google2fa_qrcode,
                                }}
                            />
                        </Descriptions.Item>
                    ) : null}
                </Descriptions>
                <Divider />
                <Descriptions column={{ xs: 1, md: 2 }} bordered title={t('member.fields.featureToggle')}>
                    <Descriptions.Item label={t('member.fields.accountStatus')}>
                        <Switch checked={!!record?.status} disabled />
                    </Descriptions.Item>
                    <Descriptions.Item label={t('member.fields.agentStatus')}>
                        <Switch checked={record?.agent_enable} disabled />
                    </Descriptions.Item>
                </Descriptions>
                <Divider />
                <Descriptions column={{ xs: 1, md: 3 }} bordered title={t('member.fields.walletInfo')}>
                    <Descriptions.Item label={t('member.fields.totalBalance')}>{record?.wallet?.balance}</Descriptions.Item>
                    <Descriptions.Item label={t('member.fields.availableBalance')}>{record?.wallet?.available_balance}</Descriptions.Item>
                    <Descriptions.Item label={t('member.fields.frozenBalance')}>{record?.wallet?.frozen_balance}</Descriptions.Item>
                </Descriptions>
                <Divider />
            </Show>
        </>
    );
};

export default MemberShow;
