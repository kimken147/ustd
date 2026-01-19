import { DownloadOutlined, EditOutlined, EyeOutlined } from "@ant-design/icons";
import {
    Badge,
    Button,
    Descriptions,
    Divider,
    Form,
    Grid,
    Input,
    Modal,
    Space,
    Switch,
    Table,
    Typography,
} from "antd";
import { Show, TextField, useForm, useModal } from "@refinedev/antd";
import { useApiUrl, useCustom, useGetIdentity, useLogout, useTranslate } from "@refinedev/core";
import { useLocation, useNavigate } from "react-router";
import { axiosInstance } from "@refinedev/simple-rest";
import useUpdateModal from "hooks/useUpdateModal";
import { UserChannel } from "interfaces/user";
import numeral from "numeral";
import { FC, useState } from "react";
import { Helmet } from "react-helmet";

const HomePage: FC = () => {
    const t = useTranslate();
    const title = t("home.title");
    const { state } = useLocation();
    const { form } = useForm();
    const navigate = useNavigate();
    const { data, isLoading, refetch } = useGetIdentity<Profile>();
    const apiUrl = useApiUrl();
    const { mutate: logout } = useLogout();

    const breakpoint = Grid.useBreakpoint();

    const {
        show: showUpdateModal,
        modalProps,
        Modal: UpdateModal,
    } = useUpdateModal({
        formItems: [
            {
                label: t("home.fields.oldPassword"),
                name: "old_password",
                rules: [
                    {
                        required: true,
                    },
                ],
                children: <Input.Password />,
            },
            {
                label: t("home.fields.newPassword"),
                name: "new_password",
                rules: [
                    {
                        required: true,
                    },
                ],
                children: <Input.Password />,
            },
            {
                label: t("home.fields.confirmPassword"),
                name: "confirm_password",
                rules: [
                    { required: true, message: t("home.fields.confirmPassword") },
                    ({ getFieldValue }) => ({
                        validator(_, value) {
                            if (!value || getFieldValue("new_password") === value) {
                                return Promise.resolve();
                            }
                            return Promise.reject(new Error(t("home.error.confirmPassword")));
                        },
                    }),
                ],
                children: <Input.Password />,
            },
            {
                label: t("home.fields.oneTimePassword"),
                name: "one_time_password",
                children: <Input />,
                rules: [
                    {
                        required: true,
                    },
                ],
            },
        ],
    });
    const [secret, setSecret] = useState("");
    const { modalProps: oneTimePasswordModalProps, show, close } = useModal();
    const { result: secretKey, query: secretKeyQuery } = useCustom<{ secret_key: string }>({
        url: `${apiUrl}/secret-key?one_time_password=null`,
        method: "get",
        queryOptions: {
            enabled: false,
        },
    });
    const refetchSecretKey = secretKeyQuery.refetch;
    const user = {
        ...(state as Profile),
        ...data,
        secret: secretKey?.data?.secret_key || secret,
    };
    if (isLoading) return null;
    const isSub = user.role === 5;
    return (
        <>
            <Helmet>
                <title>{title}</title>
            </Helmet>
            <Show title={title} headerButtons={() => null}>
                <Descriptions column={{ xs: 1, md: 2, lg: 4 }} bordered title={t("home.subTitle.userInfo")}>
                    <Descriptions.Item label={t("home.fields.username")}>{user?.name}</Descriptions.Item>
                    <Descriptions.Item label={t("home.fields.password")}>
                        <Button
                            icon={<EditOutlined />}
                            onClick={() => {
                                const items = ["old_password", "new_password", "confirm_password"];
                                if (user.google2fa_enable) {
                                    items.push("one_time_password");
                                }
                                showUpdateModal({
                                    filterFormItems: items,
                                    title: t("home.fields.changePassword"),
                                    customMutateConfig: {
                                        url: `${apiUrl}/change-password`,
                                        method: "post",
                                    },
                                    successMessage: t("success"),
                                    onSuccess() {
                                        logout();
                                    },
                                });
                            }}
                        >
                            {t("home.values.changePassword")}
                        </Button>
                    </Descriptions.Item>
                    {!isSub && !process.env.REACT_APP_HIDE_MERCHANT_SECRET ? (
                        <>
                            <Descriptions.Item label={t("home.fields.secretKey")}>
                                {user.secret ? (
                                    <TextField value={user.secret} strong />
                                ) : (
                                    <Button
                                        icon={<EyeOutlined />}
                                        onClick={async () => {
                                            if (user.google2fa_enable) {
                                                show();
                                            } else {
                                                await refetchSecretKey();
                                            }
                                        }}
                                    >
                                        {t("home.fields.secretKey")}
                                    </Button>
                                )}
                            </Descriptions.Item>
                            <Descriptions.Item label={t("home.fields.apiDocument")}>
                                <a href={process.env.REACT_APP_API_FILE} target="_blank" rel="noreferrer">
                                    <Button icon={<DownloadOutlined />}>{t("home.values.download")}</Button>
                                </a>
                            </Descriptions.Item>
                        </>
                    ) : null}

                    <Descriptions.Item label={t("home.fields.accountStatus")}>
                        <Space>
                            <Badge status={user?.status ? "success" : "error"} />
                            {user?.status ? t("enable") : t("disable")}
                        </Space>
                    </Descriptions.Item>
                    {user.role !== 5 ? (
                        <>
                            <Descriptions.Item label={t("home.fields.agentFunctionStatus")}>
                                <Space>
                                    <Badge status={user?.agent_enable ? "success" : "error"} />
                                    {user?.agent_enable ? t("enable") : t("disable")}
                                </Space>
                            </Descriptions.Item>
                            <Descriptions.Item label={t("home.fields.googleVerificationStatus")}>
                                <Switch
                                    checked={user?.google2fa_enable}
                                    onChange={(checked) =>
                                        UpdateModal.confirm({
                                            title: t("home.tips.confirmChangeGoogleVerification"),
                                            id: 0,
                                            values: {
                                                google2fa_enable: checked,
                                            },
                                            customMutateConfig: {
                                                url: `${apiUrl}/self`,
                                                method: "post",
                                            },
                                            onSuccess() {
                                                refetch();
                                            },
                                        })
                                    }
                                />
                            </Descriptions.Item>
                            <Descriptions.Item label={t("home.fields.withrawVerificationStatus")}>
                                <Switch
                                    checked={user?.withdraw_google2fa_enable}
                                    onChange={(checked) =>
                                        UpdateModal.confirm({
                                            title: t("home.tips.confirmChangeWithdrawVerification"),
                                            id: 0,
                                            values: {
                                                withdraw_google2fa_enable: checked,
                                            },
                                            customMutateConfig: {
                                                url: `${apiUrl}/self`,
                                                method: "post",
                                            },
                                            onSuccess() {
                                                refetch();
                                            },
                                        })
                                    }
                                />
                            </Descriptions.Item>
                            <Descriptions.Item label={t("home.fields.verificationKey")}>
                                <Space>
                                    <Button
                                        danger
                                        type="primary"
                                        onClick={() =>
                                            UpdateModal.confirm({
                                                title: t("home.tips.confirmChangeGoogleSecertKey"),
                                                id: 0,
                                                customMutateConfig: {
                                                    method: "put",
                                                    url: `${apiUrl}/google2fa_secret`,
                                                },
                                                onSuccess: (data) => {
                                                    navigate(`/home`, {
                                                        state: {
                                                            ...user,
                                                            ...data,
                                                        },
                                                    });
                                                },
                                            })
                                        }
                                    >
                                        {t("home.values.resetVerificationKey")}
                                    </Button>
                                    {user?.google2fa_secret ? (
                                        <TextField value={user?.google2fa_secret} copyable />
                                    ) : null}
                                </Space>
                            </Descriptions.Item>
                        </>
                    ) : null}

                    {user?.google2fa_qrcode ? (
                        <Descriptions.Item label={t("home.fields.google2faQRCode")}>
                            <div
                                dangerouslySetInnerHTML={{
                                    __html: user?.google2fa_qrcode,
                                }}
                            />
                        </Descriptions.Item>
                    ) : null}
                </Descriptions>
                <Divider />
                <Descriptions column={{ xs: 1, md: 3 }} bordered title={t("home.subTitle.balance")}>
                    <Descriptions.Item label={t("home.fields.balance")}>{user?.wallet?.balance ?? 0}</Descriptions.Item>
                    <Descriptions.Item label={t("home.fields.frozenBalance")}>
                        {user?.wallet?.frozen_balance ?? 0}
                    </Descriptions.Item>
                    <Descriptions.Item label={t("home.fields.availableBalance")}>
                        {user?.wallet?.available_balance ?? 0}
                    </Descriptions.Item>
                    <Descriptions.Item label={t("home.fields.withdrawFee")}>{`${user?.withdraw_fee}/${t(
                        "home.fields.perTrans",
                    )}`}</Descriptions.Item>
                    <Descriptions.Item label={t("home.fields.payoutFee")}>{`${numeral(
                        user?.agency_withdraw_fee || 0,
                    ).format("0.00")}% + ${user?.agency_withdraw_fee_dollar || 0}/${t(
                        "home.fields.perTrans",
                    )}`}</Descriptions.Item>
                </Descriptions>
                <Divider />
                <Typography.Title level={5}>{t("home.subTitle.channel")}</Typography.Title>
                <div
                    style={{
                        overflowX: "auto",
                        maxWidth: breakpoint.xs || breakpoint.sm || breakpoint.md ? "calc(100vw - 72px)" : "auto",
                    }}
                >
                    <Table dataSource={user?.user_channels} rowKey="id" pagination={false}>
                        <Table.Column<UserChannel>
                            title={t("home.fields.channelName")}
                            render={(value, record) => {
                                return `${t(`channels.${record.code}`)} ${record.amount_description}`;
                            }}
                        />
                        <Table.Column title={t("home.fields.ratePer")} dataIndex={"fee_percent"} />
                        <Table.Column
                            title={t("status")}
                            dataIndex={"status"}
                            render={(value) => (
                                <Space>
                                    <Badge status={value ? "success" : "error"} />
                                    <TextField value={value ? t("enable") : t("disable")} />
                                </Space>
                            )}
                        />
                    </Table>
                </div>
            </Show>
            <Modal {...modalProps} />
            <Modal {...oneTimePasswordModalProps} title={t("home.fields.verificationKey")} onOk={form.submit}>
                <Form
                    form={form}
                    onFinish={async (values: any) => {
                        const res = await axiosInstance.get<IRes<{ secret_key: string }>>(
                            `${apiUrl}/secret-key?one_time_password=${values.one_time_password}`,
                        );
                        close();
                        setSecret(res.data.data.secret_key);
                    }}
                >
                    <Form.Item
                        label={t("home.fields.oneTimePassword")}
                        name={"one_time_password"}
                        rules={[{ required: true }]}
                    >
                        <Input />
                    </Form.Item>
                </Form>
            </Modal>
        </>
    );
};

export default HomePage;
