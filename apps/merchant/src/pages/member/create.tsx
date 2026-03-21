import { SaveOutlined } from "@ant-design/icons";
import { Button, Col, Divider, Form, Input, Row, Spin, Typography } from "antd";
import { Create, useForm } from "@refinedev/antd";
import { useCreate, useList, useTranslate } from "@refinedev/core";
import { useNavigate } from "react-router";
import { ChannelGroup } from "@morgan-ustd/shared";
import { Member } from "interfaces/member";
import { FC } from "react";
import { Helmet } from "react-helmet";

const MemberCreate: FC = () => {
    const t = useTranslate();
    const title = t('member.buttons.create');
    const { form } = useForm();
    const { mutateAsync: create } = useCreate<Member>();
    const { query } = useList<ChannelGroup>({
        resource: "channel-groups",
    });
    const isLoading = query.isLoading;
    const navigate = useNavigate();
    if (isLoading) return <Spin />;

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
                            {t('submit')}
                        </Button>
                    </>
                )}
            >
                <Form
                    layout="vertical"
                    form={form}
                    onFinish={async (values) => {
                        const res = await create({
                            resource: "members",
                            values,
                            successNotification: {
                                message: t('member.messages.createSuccess'),
                                type: "success",
                            },
                        });
                        navigate(`/members/${res.data.id}`, {
                            state: res.data,
                        });
                    }}
                >
                    <Typography.Title level={4}>{t('member.fields.accountInfo')}</Typography.Title>
                    <Row gutter={16}>
                        <Col xs={24} md={12}>
                            <Form.Item label={t('member.fields.merchantName')} name={"name"} rules={[{ required: true }]}>
                                <Input />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label={t('member.fields.phone')} name={"phone"}>
                                <Input />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label={t('member.fields.loginAccount')} name={"username"} rules={[{ required: true }]}>
                                <Input />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label={t('member.fields.contact')} name={"contact"}>
                                <Input.TextArea />
                            </Form.Item>
                        </Col>
                    </Row>
                    <Divider />
                </Form>
            </Create>
        </>
    );
};

export default MemberCreate;
