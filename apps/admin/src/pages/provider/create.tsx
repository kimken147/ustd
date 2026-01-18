import {
  Create,
  useForm,
} from '@refinedev/antd';
import {
  Col,
  Form,
  Input,
  Row,
  Button,
} from 'antd';
import { useCreate, useNavigation, useResource } from "@refinedev/core";
import { generateUsername } from "friendly-username-generator";
import useProvider from "hooks/useProvider";
import { FC } from "react";

type FormParams = {
    status: boolean;
    transaction_enable: boolean;
    deposit_enable: boolean;
    paufen_deposit_enable: boolean;
    username: string;
    name: string;
};

const ProviderCreate: FC = (props) => {
    const { form } = useForm({
        action: "create",
    });
    const { refetch } = useProvider();
    const { mutateAsync } = useCreate<FormParams>();
    const { resourceName } = useResource();
    const { list } = useNavigation();
    return (
        <Create
            title="建立群组"
            footerButtons={() => (
                <>
                    <Button
                        onClick={() => {
                            list("providers");
                        }}
                    >
                        取消
                    </Button>
                    <Button type="primary" onClick={form.submit}>
                        提交
                    </Button>
                </>
            )}
        >
            <Form
                form={form}
                onFinish={async (values) => {
                    await mutateAsync({
                        resource: resourceName,
                        values: {
                            status: true,
                            transaction_enable: true,
                            deposit_enable: true,
                            paufen_deposit_enable: true,
                            username: generateUsername({
                                useHyphen: false,
                            }),
                            ...values,
                        },
                        successNotification: {
                            message: "新增群組成功",
                            type: "success",
                        },
                    });
                    await refetch();
                    list("providers");
                }}
            >
                <Row gutter={16}>
                    {/* <Col span={12}>
                        <Form.Item
                            name={"username"}
                            label="群组名称"
                            rules={[
                                {
                                    required: true,
                                },
                                () => ({
                                    validator(_, value) {
                                        if (/^[A-Za-z][A-Za-z0-9_]{4,15}$/.test(value)) {
                                            return Promise.resolve();
                                        } else {
                                            return Promise.reject(new Error("帐号需在4~15字元长度，且为英文开头"));
                                        }
                                    },
                                }),
                            ]}
                        >
                            <Input />
                        </Form.Item>
                    </Col> */}
                    <Col xs={24} md={12}>
                        <Form.Item name={"name"} label="名称" rules={[{ required: true }]}>
                            <Input />
                        </Form.Item>
                    </Col>
                </Row>
            </Form>
        </Create>
    );
};

export default ProviderCreate;
