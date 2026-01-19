import { SaveOutlined } from "@ant-design/icons";
import {
    Button,
    Col,
    Descriptions,
    Divider,
    Form,
    Input,
    InputNumber,
    Row,
    Spin,
    Typography,
} from "antd";
import { Create, useForm } from "@refinedev/antd";
import { useCreate, useTranslate } from "@refinedev/core";
import { useNavigate } from "react-router";
import ContentHeader from "components/contentHeader";
import useProfile from "hooks/useProfile";
import useSelector from "hooks/useSelector";
import { Bank } from "@morgan-ustd/shared";
import { FC } from "react";
import { Helmet } from "react-helmet";

const WithdrawCreate: FC = () => {
    const t = useTranslate();
    const title = t("withdraw.create.titles.withdraw");
    const navigate = useNavigate();
    const goBack = () => navigate(-1);
    const { data: profile } = useProfile();
    const { form } = useForm();
    const { Select: BankCardSelect } = useSelector<Bank>({
        resource: "bank-cards",
        filters: [
            {
                field: "status[]",
                value: 2,
                operator: "eq",
            },
        ],
    });
    const { mutateAsync: create, mutation } = useCreate();
    const isPending = mutation.isPending;
    if (!profile) return <Spin />;
    const colSpan = profile.withdraw_google2fa_enable ? 8 : 12;
    return (
        <>
            <Helmet>
                <title>{title}</title>
            </Helmet>
            <Create
                footerButtons={() => (
                    <>
                        <Button type="primary" icon={<SaveOutlined />} onClick={form.submit} disabled={isPending}>
                            {t("submit")}
                        </Button>
                    </>
                )}
                title={<ContentHeader title={title} />}
            >
                <Descriptions column={{ xs: 1, md: 3 }} bordered title={t("home.fields.balance")}>
                    <Descriptions.Item label={t("home.fields.balance")}>{profile?.wallet.balance}</Descriptions.Item>
                    <Descriptions.Item label={t("home.fields.availableBalance")}>
                        {profile?.wallet.available_balance}
                    </Descriptions.Item>
                    <Descriptions.Item label={t("home.fields.frozenBalance")}>
                        {profile?.wallet.frozen_balance}
                    </Descriptions.Item>
                </Descriptions>
                <Divider />
                <Typography.Title level={5}>{title}</Typography.Title>
                <Form
                    form={form}
                    initialValues={{
                        amount: 1,
                    }}
                    onFinish={async (values) => {
                        if (isPending) return;
                        await create({
                            resource: "withdraws",
                            values,
                            successNotification: {
                                message: t("withdraw.create.message.success"),
                                type: "success",
                            },
                        });
                        goBack();
                    }}
                >
                    <Row gutter={16}>
                        <Col span={colSpan}>
                            <Form.Item
                                label={t("withdraw.fields.bankCard")}
                                name={"bank_card_id"}
                                rules={[{ required: true }]}
                            >
                                <BankCardSelect />
                            </Form.Item>
                        </Col>
                        <Col span={colSpan}>
                            <Form.Item label={t("amount")} name={"amount"} rules={[{ required: true }]}>
                                <InputNumber className="w-full" />
                            </Form.Item>
                        </Col>
                        {profile.withdraw_google2fa_enable ? (
                            <Col span={colSpan}>
                                <Form.Item
                                    label={t("withdraw.create.fields.code")}
                                    name={"one_time_password"}
                                    rules={[{ required: true }]}
                                >
                                    <Input />
                                </Form.Item>
                            </Col>
                        ) : null}
                    </Row>
                </Form>
            </Create>
        </>
    );
};

export default WithdrawCreate;
