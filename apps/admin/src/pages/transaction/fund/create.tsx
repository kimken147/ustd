import { Col, Create, Form, Input, InputNumber, Row, SaveButton, useForm } from "@pankod/refine-antd";
import { useCreate, useNavigation, useNotification } from "@pankod/refine-core";
import useSelector from "hooks/useSelector";
import { Bank, ProviderUserChannel as UserChannel } from "@morgan-ustd/shared";
import { FC, useState } from "react";
import { Helmet } from "react-helmet";

const FundCreate: FC = () => {
    const { form } = useForm();
    const { list } = useNavigation();
    const { open } = useNotification();
    const { Select: BankSelect } = useSelector<Bank>({
        resource: "banks",
        valueField: "name",
    });
    const { Select: UserChannelAccountSelect } = useSelector<UserChannel>({
        resource: "user-channel-accounts",
        labelRender(record) {
            return `${record.account}(${record.name})`;
        },
    });
    const { mutateAsync: create } = useCreate();

    const [loading, setLoading] = useState(false);

    return (
        <Create
            title="建立转账"
            footerButtons={() => (
                <>
                    <SaveButton onClick={form.submit} loading={loading}>
                        提交
                    </SaveButton>
                </>
            )}
        >
            <Helmet>
                <title>建立转账</title>
            </Helmet>
            <Form
                form={form}
                layout="vertical"
                onFinish={async (values: any) => {
                    if (loading) return;
                    if (!values.list?.length) return;
                    setLoading(true);
                    try {
                        for (let item of values.list) {
                            await create({
                                resource: "internal-transfers",
                                values: item,
                                successNotification: false,
                            });
                        }
                    } catch (error) {
                        throw error;
                    } finally {
                        setLoading(false);
                    }
                    open?.({
                        type: "success",
                        message: "建立转帐账号成功",
                    });
                    list("internal-transfers");
                }}
            >
                <Form.List name={"list"} initialValue={[{}]}>
                    {(fields, { add, remove }, { errors }) => {
                        return (
                            <>
                                {fields.map(({ key, name }, index) => (
                                    <Row gutter={16}>
                                        <Col xs={24} md={24} lg={1}>
                                            <Form.Item label=" ">{index + 1}</Form.Item>
                                        </Col>
                                        <Col xs={24} md={12} lg={3}>
                                            <Form.Item
                                                label="付款账号"
                                                name={[name, "account_id"]}
                                                rules={[{ required: true }]}
                                            >
                                                <UserChannelAccountSelect />
                                            </Form.Item>
                                        </Col>
                                        <Col xs={24} md={12} lg={3}>
                                            <Form.Item label="备注" name={[name, "note"]}>
                                                <Input placeholder="选填" />
                                            </Form.Item>
                                        </Col>
                                        <Col xs={24} md={12} lg={4}>
                                            <Form.Item
                                                label="转出金额"
                                                name={[name, "amount"]}
                                                rules={[{ required: true }]}
                                            >
                                                <InputNumber className="w-full" />
                                            </Form.Item>
                                        </Col>
                                        <Col xs={24} md={12} lg={4}>
                                            <Form.Item
                                                label="银行名称"
                                                name={[name, "bank_name"]}
                                                rules={[{ required: true }]}
                                            >
                                                <BankSelect />
                                            </Form.Item>
                                        </Col>
                                        <Col xs={24} md={12} lg={4}>
                                            <Form.Item
                                                label="收款账号"
                                                name={[name, "bank_card_number"]}
                                                rules={[{ required: true }]}
                                            >
                                                <Input />
                                            </Form.Item>
                                        </Col>
                                        <Col xs={24} md={12} lg={4}>
                                            <Form.Item
                                                label="持卡人姓名"
                                                name={[name, "bank_card_holder_name"]}
                                                rules={[{ required: true }]}
                                            >
                                                <Input />
                                            </Form.Item>
                                        </Col>

                                        {/* <Col xs={24} md={4} lg={1}>
                                            <Form.Item label=" ">
                                                <MinusCircleOutlined
                                                    onClick={() => remove(index)}
                                                    className="text-xl"
                                                />
                                            </Form.Item>
                                        </Col> */}
                                    </Row>
                                ))}
                                {/* <Row gutter={16} align="middle">
                                    <Form.Item>
                                        <Button type="dashed" onClick={() => add()}>
                                            建立一笔
                                        </Button>
                                        <Form.ErrorList errors={errors} />
                                    </Form.Item>
                                </Row> */}
                            </>
                        );
                    }}
                </Form.List>
            </Form>
        </Create>
    );
};

export default FundCreate;
