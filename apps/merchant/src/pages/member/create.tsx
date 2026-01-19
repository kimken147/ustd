import { SaveOutlined } from "@ant-design/icons";
import { Button, Col, Divider, Form, Input, Row, Spin, Typography } from "antd";
import { Create, useForm } from "@refinedev/antd";
import { useCreate, useList } from "@refinedev/core";
import { useNavigate } from "react-router";
import { ChannelGroup } from "@morgan-ustd/shared";
import { Member } from "interfaces/member";
import { FC } from "react";
import { Helmet } from "react-helmet";

const MemberCreate: FC = () => {
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
                <title>建立下级帐号</title>
            </Helmet>
            <Create
                title="建立下级帐号"
                footerButtons={() => (
                    <>
                        <Button type="primary" icon={<SaveOutlined />} onClick={form.submit}>
                            提交
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
                                message: "建立下级帐号成功",
                                type: "success",
                            },
                        });
                        navigate(`/members/show/${res.data.id}`, {
                            state: res.data,
                        });
                    }}
                >
                    <Typography.Title level={4}>帐号相关</Typography.Title>
                    <Row gutter={16}>
                        <Col xs={24} md={12}>
                            <Form.Item label="商户名称" name={"name"} rules={[{ required: true }]}>
                                <Input />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="电话" name={"phone"}>
                                <Input />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="登录帐号" name={"username"} rules={[{ required: true }]}>
                                <Input />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item label="其他联络方式" name={"contact"}>
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
