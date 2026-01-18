import {
  Create,
  SaveButton,
  useForm,
} from '@refinedev/antd';
import {
  Form,
  Input,
  InputNumber,
  Radio,
  TimePicker,
} from 'antd';
import { useCreate, useNavigation } from "@refinedev/core";
import dayjs from "dayjs";
import { FC } from "react";
import { Helmet } from "react-helmet";

const TransactionRewardCreate: FC = () => {
    const title = "建立交易奖励";
    const { form } = useForm();
    const { mutateAsync: create } = useCreate();
    const { goBack } = useNavigation();
    return (
        <>
            <Helmet>
                <title>{title}</title>
            </Helmet>
            <Create
                title={title}
                footerButtons={
                    <>
                        <SaveButton onClick={form.submit}>提交</SaveButton>
                    </>
                }
            >
                <Form
                    form={form}
                    initialValues={{
                        reward_unit: 1,
                    }}
                    onFinish={async (values: any) => {
                        await create({
                            resource: "transaction-rewards",
                            values: {
                                ...values,
                                started_at: dayjs(values.started_at).format("HH:mm"),
                                ended_at: dayjs(values.ended_at).format("HH:mm"),
                            },
                            successNotification: {
                                type: "success",
                                message: "建立成功",
                            },
                        });
                        goBack();
                    }}
                >
                    <Form.Item label="开始时间" name={"started_at"} rules={[{ required: true }]} trigger="onSelect">
                        <TimePicker showSecond={false} format={"HH:mm"} />
                    </Form.Item>
                    <Form.Item label="结束时间" name={"ended_at"} rules={[{ required: true }]} trigger="onSelect">
                        <TimePicker showSecond={false} format={"HH:mm"} />
                    </Form.Item>
                    <Form.Item label="金额区间（格式 : 100~200)" name={"amount"} rules={[{ required: true }]}>
                        <Input />
                    </Form.Item>
                    <Form.Item label="模式" name={"reward_unit"}>
                        <Radio.Group>
                            <Radio value={1}>单笔奖励</Radio>
                            <Radio value={2}>% 奖励</Radio>
                        </Radio.Group>
                    </Form.Item>
                    <Form.Item label="奖励佣金" name={"reward_amount"} rules={[{ required: true }]}>
                        <InputNumber className="w-full" />
                    </Form.Item>
                </Form>
            </Create>
        </>
    );
};

export default TransactionRewardCreate;
