import { SaveOutlined } from "@ant-design/icons";
import { Button, Form, Input } from "antd";
import { Create, useForm } from "@refinedev/antd";
import { useCreate, useTranslate } from "@refinedev/core";
import { useNavigate } from "react-router";
import useSelector from "hooks/useSelector";
import { Bank } from "@morgan-ustd/shared";
import { FC } from "react";
import { Helmet } from "react-helmet";

const BankCardCreate: FC = () => {
    const t = useTranslate();
    const title = t("bankCard.buttons.create");
    const navigate = useNavigate();
    const goBack = () => navigate(-1);
    const { form } = useForm();
    const { mutateAsync: create } = useCreate();
    const { Select: BankSelect } = useSelector<Bank>({
        resource: "banks",
        valueField: "name",
    });
    return (
        <>
            <Helmet>
                <title>{title}</title>
            </Helmet>
            <Create
                title={title}
                footerButtons={() => (
                    <>
                        <Button type="primary" icon={<SaveOutlined />} onClick={form.submit}>
                            {t("submit")}
                        </Button>
                    </>
                )}
            >
                <Form
                    form={form}
                    onFinish={async (values) => {
                        await create({
                            resource: "bank-cards",
                            values,
                            successNotification: {
                                message: t("bankCard.messages.success"),
                                type: "success",
                            },
                        });
                        goBack();
                    }}
                >
                    <Form.Item
                        label={t("bankCard.fields.accountOwner")}
                        name={"bank_card_holder_name"}
                        rules={[{ required: true }]}
                    >
                        <Input />
                    </Form.Item>
                    <Form.Item
                        label={t("bankCard.fields.bankAccount")}
                        name={"bank_card_number"}
                        rules={[{ required: true }]}
                    >
                        <Input />
                    </Form.Item>
                    <Form.Item label={t("bankCard.fields.bankName")} name={"bank_name"} rules={[{ required: true }]}>
                        <BankSelect />
                    </Form.Item>
                </Form>
            </Create>
        </>
    );
};

export default BankCardCreate;
