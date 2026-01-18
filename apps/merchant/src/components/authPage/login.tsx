import { LoginFormTypes, useApiUrl, useCustomMutation, useLogin } from "@refinedev/core";
import { Card, Form, Layout, Input, Button, Row, Col } from "antd";
import { FC, useState } from "react";
import { ImageField } from "@refinedev/antd";

const LoginPage: FC = () => {
    const [form] = Form.useForm<LoginFormTypes>();
    const apiUrl = useApiUrl();
    const { mutate: login, isPending: isPendingLogin } = useLogin();
    const { mutateAsync, isPending: isPreLoginLoading } = useCustomMutation<IPreLoginRes>();
    const isLoading = isPendingLogin || isPreLoginLoading;
    const [isGoogleAuthOpen, setGoogleAuth] = useState<boolean | null>(null);
    return (
        <Layout
            style={{
                background: `radial-gradient(50% 50% at 50% 50%, #14cafe 0%, #0076d0 100%)`,
                backgroundSize: "cover",
            }}
        >
            <Row justify={"center"} align="middle" style={{ height: "100vh" }}>
                <Col xs={22}>
                    <Card
                        title={<ImageField value={process.env.REACT_APP_LOGO_SRC} width={238} preview={false} />}
                        headStyle={{ borderBottom: 0, textAlign: "center", fontSize: "2rem", padding: 20 }}
                        className={"max-w-[480px] m-auto bg-[rgb(0_0_0_/_45%)] border-0"}
                    >
                        <Form<LoginFormTypes>
                            layout="vertical"
                            form={form}
                            onFinish={async (values) => {
                                if (isGoogleAuthOpen) {
                                    login(values);
                                    return;
                                }
                                const res = await mutateAsync({
                                    url: `${apiUrl}/pre-login`,
                                    method: "post",
                                    values,
                                });
                                if (!res.data.google2fa_enable) {
                                    login({ ...values, googleAuth: "123" });
                                }
                                setGoogleAuth(res.data.google2fa_enable);
                            }}
                            requiredMark={false}
                            initialValues={{
                                remember: false,
                            }}
                        >
                            <Form.Item
                                className="form-item"
                                name="username"
                                label={"帐号"}
                                rules={[{ required: true, message: "请输入帐号" }]}
                            >
                                <Input size="large" />
                            </Form.Item>
                            <Form.Item
                                className="form-item"
                                name="password"
                                label={"密码"}
                                rules={[{ required: true, message: "请输入密码" }]}
                                style={{ marginBottom: "12px" }}
                            >
                                <Input type="password" size="large" />
                            </Form.Item>
                            <Form.Item
                                className="form-item"
                                name={"googleAuth"}
                                label="安全码"
                                rules={[{ required: isGoogleAuthOpen === true }]}
                                hidden={isGoogleAuthOpen === null || isGoogleAuthOpen === false}
                            >
                                <Input size="large" />
                            </Form.Item>
                            <Form.Item style={{ marginTop: "30px" }}>
                                <Button
                                    className="!bg-[#faad14]"
                                    type="primary"
                                    size="large"
                                    htmlType="submit"
                                    loading={isLoading}
                                    block
                                >
                                    登录
                                </Button>
                            </Form.Item>
                        </Form>
                    </Card>
                </Col>
            </Row>
        </Layout>
    );
};

export default LoginPage;
