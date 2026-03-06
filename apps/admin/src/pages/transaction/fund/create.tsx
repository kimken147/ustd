import {
  Create,
  SaveButton,
  useForm,
} from '@refinedev/antd';
import {
  Button,
  Col,
  Form,
  Input,
  InputNumber,
  Row,
} from 'antd';
import { MinusCircleOutlined } from '@ant-design/icons';
import { useCreate, useNavigation, useNotification } from "@refinedev/core";
import useSelector from "hooks/useSelector";
import { Bank, ProviderUserChannel as UserChannel } from "@morgan-ustd/shared";
import { FC, useState } from "react";
import { Helmet } from "react-helmet";
import { useTranslation } from 'react-i18next';

const FundCreate: FC = () => {
    const { t } = useTranslation('transaction');
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
            title={t('fund.create')}
            footerButtons={() => (
                <>
                    <SaveButton onClick={form.submit} loading={loading}>
                        {t('actions.submit')}
                    </SaveButton>
                </>
            )}
        >
            <Helmet>
                <title>{t('fund.create')}</title>
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
                        message: t('fund.createSuccess'),
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
                                                label={t('fields.paymentAccountNumber')}
                                                name={[name, "account_id"]}
                                                rules={[{ required: true }]}
                                            >
                                                <UserChannelAccountSelect />
                                            </Form.Item>
                                        </Col>
                                        <Col xs={24} md={12} lg={3}>
                                            <Form.Item label={t('fields.note')} name={[name, "note"]}>
                                                <Input placeholder={t('placeholders.optional')} />
                                            </Form.Item>
                                        </Col>
                                        <Col xs={24} md={12} lg={4}>
                                            <Form.Item
                                                label={t('fields.transferAmount')}
                                                name={[name, "amount"]}
                                                rules={[{ required: true }]}
                                            >
                                                <InputNumber className="w-full" />
                                            </Form.Item>
                                        </Col>
                                        <Col xs={24} md={12} lg={4}>
                                            <Form.Item
                                                label={t('fields.bankName')}
                                                name={[name, "bank_name"]}
                                                rules={[{ required: true }]}
                                            >
                                                <BankSelect />
                                            </Form.Item>
                                        </Col>
                                        <Col xs={24} md={12} lg={4}>
                                            <Form.Item
                                                label={t('fields.collectionAccount')}
                                                name={[name, "bank_card_number"]}
                                                rules={[{ required: true }]}
                                            >
                                                <Input />
                                            </Form.Item>
                                        </Col>
                                        <Col xs={24} md={12} lg={4}>
                                            <Form.Item
                                                label={t('fields.cardHolderName')}
                                                name={[name, "bank_card_holder_name"]}
                                                rules={[{ required: true }]}
                                            >
                                                <Input />
                                            </Form.Item>
                                        </Col>

                                        <Col xs={24} md={4} lg={1}>
                                            <Form.Item label=" ">
                                                <MinusCircleOutlined
                                                    onClick={() => remove(index)}
                                                    className="text-xl"
                                                />
                                            </Form.Item>
                                        </Col>
                                    </Row>
                                ))}
                                <Row gutter={16} align="middle">
                                    <Form.Item>
                                        <Button type="dashed" onClick={() => add()}>
                                            {t('buttons.createOne')}
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

export default FundCreate;
