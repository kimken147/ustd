import { SaveOutlined } from "@ant-design/icons";
import { Button, Col, Create, Divider, Form, Input, Row, Spin, Typography, useForm } from "@pankod/refine-antd";
import { useCreate, useList } from "@pankod/refine-core";
import { useNavigate } from "@pankod/refine-react-router-v6";
import { ChannelGroup } from "interfaces/channelGroup";
import { Member } from "interfaces/member";
import { FC } from "react";
import { Helmet } from "react-helmet";

const MemberCreate: FC = () => {
    const { form } = useForm();
    const { mutateAsync: create } = useCreate<Member>();
    const { isLoading } = useList<ChannelGroup>({
        resource: "channel-groups",
    });
    // const channelGroups = data?.data;
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
                    {/* <Typography.Title level={4}>通道</Typography.Title>
                    <Form.List
                        name={"user_channels"}
                        initialValue={channelGroups?.map((channelGroup) => ({
                            channel_group_id: channelGroup.id,
                        }))}
                    >
                        {(fields) => {
                            return fields.map(({ key, name }, index) => (
                                <div key={key}>
                                    <Form.Item name={[name, "channel_group_id"]} hidden></Form.Item>
                                    <Row>
                                        <Col xs={24} md={12}>
                                            <Form.Item label="通道">
                                                <TextField value={channelGroups?.[index].name} />
                                            </Form.Item>
                                        </Col>
                                        <Col xs={24} md={12}>
                                            <Form.Item label="费率(%)" name={[name, "fee_percent"]}>
                                                <InputNumber className="w-full" />
                                            </Form.Item>
                                        </Col>
                                    </Row>
                                </div>
                            ));
                        }}
                    </Form.List> */}
                </Form>
            </Create>
        </>
    );
};

export default MemberCreate;
