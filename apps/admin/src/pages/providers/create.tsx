import { SaveOutlined } from "@ant-design/icons";
import {
    Button,
    Col,
    ColProps,
    Create,
    Divider,
    Form,
    Input,
    InputNumber,
    Row,
    Select,
    Spin,
    Switch,
    TextField,
    useForm,
} from "@pankod/refine-antd";
import { useCreate, useList, useNavigation } from "@pankod/refine-core";
import { useNavigate } from "@pankod/refine-react-router-v6";
import useSelector from "hooks/useSelector";
import { Provider } from "interfaces/provider";
import { User } from "interfaces/user";
import { ChannelGroup } from "interfaces/userChannel";
import { FC } from "react";
import { Helmet } from "react-helmet";
import {useTranslation} from "react-i18next";

const ProvidersCreate: FC = () => {
    const { t } = useTranslation('providers');
    const colProps: ColProps = {
        xs: 24,
        md: 12,
        lg: 8,
    };
    // const { data: channelGroups, isLoading: isChannelGroupLoading } = useChannelGroup();
    const { data: channelGroups, isLoading: isChannelGroupLoading } = useSelector<ChannelGroup>({
        filters: [
            {
                field: "is_provider",
                value: 1,
                operator: "eq",
            },
        ],
        resource: "channel-groups",
    });
    const { mutateAsync: create } = useCreate<Provider>();
    const { data: users } = useList<User>({
        resource: "users",
        config: {
            filters: [
                {
                    operator: "eq",
                    value: 2,
                    field: "role",
                },
                {
                    operator: "eq",
                    value: 1,
                    field: "agent_enable",
                },
            ],
        },
    });
    const { form } = useForm();
    const navigate = useNavigate();
    const { showUrl } = useNavigation();
    if (isChannelGroupLoading) return <Spin />;
    return (
        <>
            <Helmet>
                <title>{t('titles.create')}</title>
            </Helmet>
            <Create
                title={t('titles.create')}
                footerButtons={() => (
                    <>
                        <Button type="primary" icon={<SaveOutlined />} onClick={form.submit}>
                            {t('actions.submit')}
                        </Button>
                    </>
                )}
            >
                <Form
                    form={form}
                    onFinish={async (values) => {
                        const res = await create({
                            resource: "providers",
                            values,
                            successNotification: {
                                message: t('messages.createSuccess'),
                                type: "success",
                            },
                        });
                        navigate(
                            {
                                pathname: showUrl("providers", res.data.id),
                            },
                            {
                                state: res.data,
                            },
                        );
                    }}
                    layout="vertical"
                    initialValues={{
                        agent_enable: false,
                        credit_mode_enable: false,
                        deposit_enable: true,
                        deposit_mode_enable: false,
                        google2fa_enable: false,
                        paufen_deposit_enable: true,
                        transaction_enable: true,
                        withdraw_enable: false,
                        withdraw_fee: 0,
                    }}
                >
                    <h2 className="text-xl">{t('sections.accountInfo')}</h2>
                    <Row gutter={16}>
                        <Col {...colProps}>
                            <Form.Item label={t('fields.name')} name={"name"} rules={[{ required: true }]}>
                                <Input />
                            </Form.Item>
                        </Col>
                        <Col {...colProps}>
                            <Form.Item label={t('fields.username')} name={"username"} rules={[{ required: true }]}>
                                <Input autoComplete="new-password" />
                            </Form.Item>
                        </Col>
                        <Col {...colProps}>
                            <Form.Item label={t('fields.password')} name={"password"} rules={[{ required: true }]}>
                                <Input.Password autoComplete="new-password" />
                            </Form.Item>
                        </Col>
                        <Col {...colProps}>
                            <Form.Item label={t('fields.agentId')} name={"agent_id"}>
                                <Select
                                    options={users?.data.map((user) => ({
                                        label: user.name,
                                        value: user.id,
                                    }))}
                                    allowClear
                                    optionFilterProp="label"
                                    showSearch
                                />
                            </Form.Item>
                        </Col>
                        <Col {...colProps}>
                            <Form.Item label={t('fields.phone')} name={"phone"}>
                                <Input />
                            </Form.Item>
                        </Col>
                        <Col {...colProps}>
                            <Form.Item label={t('fields.contact')} name={"contact"}>
                                <Input.TextArea />
                            </Form.Item>
                        </Col>
                    </Row>
                    <Divider />
                    <h2 className="text-xl">{t('sections.functionSwitches')}</h2>
                    <Row gutter={16}>
                        <Col {...colProps}>
                            <Form.Item label={t('switches.agentEnable')} name={"agent_enable"} valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </Col>
                        <Col {...colProps}>
                            <Form.Item label={t('switches.google2faEnable')} name={"google2fa_enable"} valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </Col>
                        <Col {...colProps}>
                            <Form.Item label={t('switches.transactionEnable')} name={"transaction_enable"} valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </Col>
                        <Col {...colProps}>
                            <Form.Item label={t('switches.depositEnable')} name={"deposit_enable"} valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </Col>
                        {/* <Col {...colProps}>
                            <Form.Item label="信用模式" name={"deposit_mode_enable"}>
                                <Switch />
                            </Form.Item>
                        </Col> */}
                        <Col {...colProps}>
                            <Form.Item label={t('switches.paufenDepositEnable')} name={"paufen_deposit_enable"} valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </Col>
                        <Col {...colProps}>
                            <Form.Item label={t('switches.withdrawEnable')} name={"withdraw_enable"} valuePropName="checked">
                                <Switch />
                            </Form.Item>
                        </Col>
                    </Row>
                    {/* <Divider />
                    <h2 className="text-xl">钱包相关</h2>
                    <Row gutter={16}>
                        <Col {...colProps}>
                            <Form.Item label="提现手续费" name={"withdraw_fee"}>
                                <InputNumber className="w-[300px]" />
                            </Form.Item>
                        </Col>
                    </Row> */}
                    <Divider />
                    <h2 className="text-xl">{t('sections.channelInfo')}</h2>
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
                                    <Row gutter={16}>
                                        <Col xs={24} md={8}>
                                            <Form.Item label={t('channel.name')}>
                                                <TextField value={channelGroups?.[index].name} />
                                            </Form.Item>
                                        </Col>
                                        <Col xs={24} md={8}>
                                            <Row gutter={16}>
                                                <Col span={11}>
                                                    <Form.Item label={t('channel.amountLimit')} name={[name, "min_amount"]}>
                                                        <InputNumber className="w-full" />
                                                    </Form.Item>
                                                </Col>
                                                <Col span={2}>
                                                    <Form.Item label=" ">~</Form.Item>
                                                </Col>
                                                <Col span={11}>
                                                    <Form.Item label=" " name={[name, "max_amount"]}>
                                                        <InputNumber className="w-full" />
                                                    </Form.Item>
                                                </Col>
                                            </Row>
                                        </Col>
                                        <Col xs={24} md={8}>
                                            <Form.Item label={t('channel.feePercent')} name={[name, "fee_percent"]}>
                                                <InputNumber className="w-full" />
                                            </Form.Item>
                                        </Col>
                                    </Row>
                                </div>
                            ));
                        }}
                    </Form.List>
                </Form>
            </Create>
        </>
    );
};

export default ProvidersCreate;
