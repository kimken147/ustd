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
  Select,
  Space,
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
    const { Select: UserChannelAccountSelect, data: userChannelAccounts } = useSelector<UserChannel>({
        resource: "user-channel-accounts",
        labelRender(record) {
            const type = record.address_type === 'child' ? `[${t('fund.childPrefix')}]` : `[${t('fund.masterPrefix')}]`;
            const name = record.user?.name ?? record.name ?? '';
            const addr = record.account ? `${record.account.slice(0, 8)}...${record.account.slice(-6)}` : '';
            return `${type} ${name} - ${addr}`;
        },
    });
    const { mutateAsync: create } = useCreate();

    const [loading, setLoading] = useState(false);

    const listValues = Form.useWatch("list", form);

    const getSelectedUserChannel = (index: number) => {
        if (!listValues || !listValues[index]) return undefined;
        const accountId = listValues[index].account_id;
        if (!accountId) return undefined;
        return userChannelAccounts?.find((item) => item.id === accountId);
    };

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
                                {fields.map(({ key, name }, index) => {
                                    const selectedUserChannel = getSelectedUserChannel(index);
                                    return (
                                        <div key={key}>
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
                                                <Col xs={24} md={12} lg={3}>
                                                    <Form.Item
                                                        label={t('fund.currency')}
                                                        name={[name, "currency"]}
                                                        initialValue="USDT"
                                                        rules={[{ required: true }]}
                                                    >
                                                        <Select
                                                            options={[
                                                                { label: t('fund.currencyUsdt'), value: 'USDT' },
                                                                { label: t('fund.currencyTrx'), value: 'TRX' },
                                                                { label: t('fund.currencyEth'), value: 'ETH' },
                                                                { label: t('fund.currencyBnb'), value: 'BNB' },
                                                            ]}
                                                        />
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
                                                        label={t('fields.chainNetwork')}
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

                                                <Col xs={24} md={4} lg={1}>
                                                    <Form.Item label=" ">
                                                        <MinusCircleOutlined
                                                            onClick={() => remove(index)}
                                                            className="text-xl"
                                                        />
                                                    </Form.Item>
                                                </Col>
                                            </Row>
                                            {selectedUserChannel && (() => {
                                                const selectedCurrency = listValues?.[index]?.currency || 'USDT';
                                                const isNative = selectedCurrency !== 'USDT';
                                                return (
                                                    <Row>
                                                        <Col offset={1}>
                                                            <Space className="text-sm mb-4">
                                                                <span>{t('fields.balance')}：{selectedUserChannel.balance ?? 0}</span>
                                                                <span style={!isNative ? { fontWeight: 'bold' } : undefined}>
                                                                    USDT：{selectedUserChannel.onchain_usdt_balance ?? '-'}
                                                                </span>
                                                                <span style={isNative ? { fontWeight: 'bold' } : undefined}>
                                                                    Gas：{selectedUserChannel.onchain_native_balance ?? '-'}
                                                                </span>
                                                            </Space>
                                                        </Col>
                                                    </Row>
                                                );
                                            })()}
                                        </div>
                                    );
                                })}
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
