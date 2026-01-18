import { MinusCircleOutlined } from "@ant-design/icons";
import {
  Create,
  SaveButton,
  TextField,
  useForm,
} from '@refinedev/antd';
import {
  Button,
  Col,
  Descriptions,
  Divider,
  Form,
  InputNumber,
  Row,
  Select,
  Typography,
} from 'antd';
import { useApiUrl, useNavigation, useNotification, useShow } from "@refinedev/core";
import { useParams } from "react-router-dom";
import useUpdateModal from "hooks/useUpdateModal";
import { Withdraw } from "@morgan-ustd/shared";
import { FC } from "react";
import { Helmet } from "react-helmet";

const ChildWithdrawCreate: FC = () => {
    const apiUrl = useApiUrl();
    const { id } = useParams();
    const { open } = useNotification();
    const { queryResult } = useShow<Withdraw>({
        resource: "withdraws",
        id,
    });
    const { list } = useNavigation();
    const { data, isLoading } = queryResult;
    const record = data?.data;
    const { form } = useForm();
    const { Modal } = useUpdateModal();
    return (
        <Create
            title="代付订单拆单"
            isLoading={isLoading}
            footerButtons={() => (
                <>
                    <SaveButton onClick={form.submit}>提交</SaveButton>
                </>
            )}
        >
            <Helmet>
                <title>代付订单拆单</title>
            </Helmet>
            <Descriptions column={{ xs: 1, md: 2 }} bordered>
                <Descriptions.Item label="代付类型">
                    <TextField value={record?.type === 2 ? "跑分代付" : "手动代付"} />
                </Descriptions.Item>
                <Descriptions.Item label="订单金额">
                    <TextField value={record?.amount} />
                </Descriptions.Item>
            </Descriptions>
            <Divider />
            <Typography.Title level={5}>建立子订单</Typography.Title>
            <Form
                layout="vertical"
                className="mt-4"
                form={form}
                onFinish={async (values) => {
                    Modal.confirm({
                        title: "是否确认建立拆单",
                        id: record?.id ?? 0,
                        values,
                        customMutateConfig: {
                            url: `${apiUrl}/withdraws/${record?.id}/child-withdraws`,
                            method: "post",
                        },
                        onSuccess() {
                            open?.({
                                type: "success",
                                message: "建立拆单成功",
                            });
                            list("withdraws");
                        },
                    });
                }}
            >
                <Form.List
                    rules={[
                        {
                            validator: async (_, values) => {
                                if (!values || values.length < 2) {
                                    return Promise.reject(new Error("拆单至少需要2笔"));
                                }
                            },
                        },
                    ]}
                    name={"child_withdraws"}
                    initialValue={[{}, {}]}
                >
                    {(fields, { add, remove }, { errors }) => {
                        return (
                            <>
                                {fields.map(({ key, name }, index) => (
                                    <Row key={key} gutter={16}>
                                        <Col xs={24} md={10}>
                                            <Form.Item name={[name, "type"]} label="类型" rules={[{ required: true }]}>
                                                <Select
                                                    options={[
                                                        {
                                                            label: "手动代付",
                                                            value: 4,
                                                        },
                                                        {
                                                            label: "跑分代付",
                                                            value: 2,
                                                        },
                                                    ]}
                                                />
                                            </Form.Item>
                                        </Col>
                                        <Col xs={24} md={10}>
                                            <Form.Item
                                                label="金额"
                                                name={[name, "amount"]}
                                                rules={[{ required: true }]}
                                            >
                                                <InputNumber className="w-full" />
                                            </Form.Item>
                                        </Col>
                                        <Col xs={24} md={4}>
                                            <Form.Item label=" ">
                                                <MinusCircleOutlined
                                                    onClick={() => remove(index)}
                                                    className="text-xl"
                                                />
                                            </Form.Item>
                                        </Col>
                                        <Form.Item name={[name, "to_id"]} hidden />
                                    </Row>
                                ))}
                                <Row gutter={16} align="middle">
                                    <Form.Item>
                                        <Button type="dashed" onClick={() => add()}>
                                            建立一笔
                                        </Button>
                                        <Form.ErrorList errors={errors} />
                                    </Form.Item>
                                </Row>
                            </>
                        );
                    }}
                </Form.List>
            </Form>
        </Create>
    );
};

export default ChildWithdrawCreate;
