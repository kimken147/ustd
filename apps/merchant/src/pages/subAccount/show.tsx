import { Button, Descriptions, Input, Space, Spin, Switch } from "antd";
import { DateField, Show, TextField } from "@refinedev/antd";
import { useApiUrl, useShow, useTranslate } from "@refinedev/core";
import { useLocation, useNavigate } from "react-router-dom";
import EditableForm from "components/EditableFormItem";
import useUpdateModal from "hooks/useUpdateModal";
import { SubAccount } from "interfaces/subAccount";
import { Format } from "@morgan-ustd/shared";
import { FC } from "react";
import { Helmet } from "react-helmet";

const SubAccountShow: FC = () => {
    const apiUrl = useApiUrl();
    const t = useTranslate();
    const navigate = useNavigate();
    const { state } = useLocation();
    const { queryResult } = useShow<SubAccount>();
    const { data, isLoading } = queryResult;
    const record = {
        ...(state as SubAccount),
        ...data?.data,
    };
    const { Modal: UpdateModal } = useUpdateModal();
    if (isLoading) return <Spin />;
    return (
        <>
            <Show title={t("subAccount.fields.info")} headerButtons={() => null}>
                <Helmet>
                    <title>{t("subAccount.fields.info")}</title>
                </Helmet>
                <Descriptions column={{ xs: 1, md: 2, lg: 3 }} bordered>
                    <Descriptions.Item label={t("subAccount.fields.name")}>
                        <EditableForm name={"name"} id={record.id}>
                            <Input defaultValue={record.name} />
                        </EditableForm>
                    </Descriptions.Item>
                    <Descriptions.Item label={t("subAccount.fields.id")}>
                        <EditableForm name={"username"} id={record.id}>
                            <Input defaultValue={record.username} />
                        </EditableForm>
                    </Descriptions.Item>
                    <Descriptions.Item label={t("subAccount.fields.status")}>
                        <Switch
                            checked={!!record.status}
                            onChange={(checked) =>
                                UpdateModal.confirm({
                                    title: t("subAccount.tips.changeStatus"),
                                    id: record.id,
                                    values: {
                                        status: +checked,
                                    },
                                })
                            }
                        />
                    </Descriptions.Item>
                    <Descriptions.Item label={t("google2faStatus")}>
                        <Switch
                            checked={record.google2fa_enable}
                            onChange={(checked) =>
                                UpdateModal.confirm({
                                    title: t("subAccount.tips.changeGoogle2faStatus"),
                                    id: record.id,
                                    values: {
                                        google2fa_enable: +checked,
                                    },
                                })
                            }
                        />
                    </Descriptions.Item>
                    <Descriptions.Item label={t("lastLoginAt")}>
                        {record.last_login_at ? <DateField value={record.last_login_at} format={Format} /> : ""}
                    </Descriptions.Item>
                    <Descriptions.Item label="IP">
                        <TextField value={record.last_login_ipv4 ?? ""} />
                    </Descriptions.Item>
                    <Descriptions.Item label={t("password")}>
                        <Space>
                            <Button
                                danger
                                type="primary"
                                onClick={() =>
                                    UpdateModal.confirm({
                                        title: t("subAccount.tips.resetPassword"),
                                        id: record.id,
                                        customMutateConfig: {
                                            url: `${apiUrl}/sub-accounts/${record.id}/password-resets`,
                                            method: "post",
                                        },
                                        onSuccess(data) {
                                            navigate(`/sub-accounts/show/${data?.id}`, {
                                                state: {
                                                    ...(state as SubAccount),
                                                    ...record,
                                                    ...data,
                                                },
                                                replace: true,
                                            });
                                        },
                                    })
                                }
                            >
                                {t("subAccount.fields.resetPassword")}
                            </Button>
                            {record.password ? <TextField value={record.password} copyable /> : null}
                        </Space>
                    </Descriptions.Item>
                    <Descriptions.Item label={t("google2faSecret")}>
                        <Space>
                            <Button
                                danger
                                type="primary"
                                onClick={() =>
                                    UpdateModal.confirm({
                                        title: t("subAccount.tips.resetGoogle2faSecret"),
                                        id: record.id,
                                        customMutateConfig: {
                                            url: `${apiUrl}/sub-accounts/${record.id}/google2fa-secret-resets`,
                                            method: "post",
                                        },
                                        onSuccess(data) {
                                            navigate(`/sub-accounts/show/${data?.id}`, {
                                                state: {
                                                    ...(state as any),
                                                    ...record,
                                                    ...data,
                                                },
                                                replace: true,
                                            });
                                        },
                                    })
                                }
                            >
                                {t("subAccount.fields.resetGoogle2faSecret")}
                            </Button>
                            {record.google2fa_secret ? <TextField value={record.google2fa_secret} copyable /> : null}
                        </Space>
                    </Descriptions.Item>
                    {record.google2fa_qrcode ? (
                        <Descriptions.Item label="谷歌验证码 QR Code">
                            <div
                                dangerouslySetInnerHTML={{
                                    __html: record?.google2fa_qrcode,
                                }}
                            />
                        </Descriptions.Item>
                    ) : null}
                </Descriptions>
            </Show>
        </>
    );
};

export default SubAccountShow;
