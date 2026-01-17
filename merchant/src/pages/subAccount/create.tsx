import { Create, Form, Input, SaveButton, useForm } from "@pankod/refine-antd";
import { useCreate, useNavigation, useTranslate } from "@pankod/refine-core";
import { useNavigate } from "@pankod/refine-react-router-v6";
import { SubAccount } from "interfaces/subAccount";
import { FC } from "react";
import { Helmet } from "react-helmet";

const SubAccountCreate: FC = () => {
    const t = useTranslate();
    const title = t("subAccount.buttons.create");
    const { form } = useForm();
    const { mutateAsync: create } = useCreate<SubAccount>();
    const { showUrl } = useNavigation();
    const navigate = useNavigate();
    return (
        <>
            <Helmet>
                <title>{title}</title>
            </Helmet>
            <Create title={title} footerButtons={() => <SaveButton onClick={form.submit}>{t("submit")}</SaveButton>}>
                <Form
                    form={form}
                    layout="vertical"
                    onFinish={async (values) => {
                        const res = await create({
                            values,
                            resource: "sub-accounts",
                            successNotification: {
                                type: "success",
                                message: t("success"),
                            },
                        });
                        navigate(
                            {
                                pathname: showUrl("sub-accounts", res.data.id),
                            },
                            {
                                state: res.data,
                            },
                        );
                    }}
                >
                    <Form.Item label={t("subAccount.fields.name")} name={"name"} rules={[{ required: true }]}>
                        <Input />
                    </Form.Item>
                    <Form.Item
                        label={t("subAccount.fields.id")}
                        name={"username"}
                        rules={[
                            { required: true },
                            () => ({
                                validator(_, value) {
                                    if (/^[A-Za-z][A-Za-z0-9_]{4,10}$/.test(value)) {
                                        return Promise.resolve();
                                    } else {
                                        return Promise.reject(new Error("帐号需在4~10字元长度，且为英文开头"));
                                    }
                                },
                            }),
                        ]}
                    >
                        <Input />
                    </Form.Item>
                </Form>
            </Create>
        </>
    );
};

export default SubAccountCreate;
