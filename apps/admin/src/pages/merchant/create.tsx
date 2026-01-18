import { SaveOutlined } from '@ant-design/icons';
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
  Space,
  Spin,
  Switch,
  TextField,
  Typography,
  useForm,
} from '@pankod/refine-antd';
import {
  useCreate,
  useList,
  useNavigation,
  useResource,
} from '@pankod/refine-core';
import { useNavigate } from '@pankod/refine-react-router-v6';
import useChannelGroup from 'hooks/useChannelGroup';
import { Merchant, User } from '@morgan-ustd/shared';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';

const MerchantCreate: FC = () => {
  const { t } = useTranslation('merchant');
  const colProps: ColProps = { xs: 24, md: 12, lg: 8 };

  const { data: users } = useList<User>({
    resource: 'users',
    config: {
      filters: [
        { operator: 'eq', value: 3, field: 'role' },
        { operator: 'eq', value: 1, field: 'agent_enable' },
      ],
    },
  });

  const { formProps } = useForm();
  const { mutateAsync: create } = useCreate<Merchant>();
  const { resourceName } = useResource();
  const { data: channelGroups, isLoading: isChannelGroupLoading } =
    useChannelGroup();
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
          <Space>
            <Button
              icon={<SaveOutlined />}
              type="primary"
              onClick={formProps.form?.submit}
            >
              {t('actions.submit')}
            </Button>
          </Space>
        )}
      >
        <Form
          {...formProps}
          layout="vertical"
          initialValues={{
            status: true,
            agency_withdraw_enable: true,
            withdraw_enable: true,
            transaction_enable: true,
            withdraw_min_amount: 0,
            withdraw_max_amount: 0,
            withdraw_fee_percent: 0,
            withdraw_fee: 0,
            additional_withdraw_fee: 0,
            agency_withdraw_min_amount: 0,
            agency_withdraw_max_amount: 0,
            agency_withdraw_fee: 0,
            agency_withdraw_fee_dollar: 0,
            additional_agency_withdraw_fee: 0,
            withdraw_google2fa_enable: false,
          }}
          onFinish={async values => {
            const res = await create({
              resource: resourceName,
              values,
              successNotification: {
                message: t('messages.createSuccess'),
                type: 'success',
              },
            });
            navigate(
              { pathname: showUrl('merchants', res.data.id) },
              { state: res.data }
            );
          }}
        >
          <h2 className="mb-4">{t('sections.accountInfo')}</h2>
          <Row gutter={16}>
            <Col {...colProps}>
              <Form.Item
                label={t('fields.name')}
                name={'name'}
                rules={[{ required: true }]}
              >
                <Input />
              </Form.Item>
            </Col>
            <Col {...colProps}>
              <Form.Item
                label={t('fields.username')}
                name={'username'}
                rules={[{ required: true }]}
              >
                <Input />
              </Form.Item>
            </Col>
            <Col {...colProps}>
              <Form.Item label={t('fields.agentId')}>
                <Select
                  options={users?.data.map(user => ({
                    label: user.name,
                    value: user.id,
                  }))}
                  allowClear
                  showSearch
                  optionFilterProp="label"
                />
              </Form.Item>
            </Col>
            <Col {...colProps}>
              <Form.Item label={t('fields.phone')} name="phone">
                <Input />
              </Form.Item>
            </Col>
            <Col {...colProps}>
              <Form.Item label={t('fields.contact')} name="contact">
                <Input.TextArea />
              </Form.Item>
            </Col>
          </Row>

          <Divider />
          <h2 className="mb-4">{t('sections.functionSwitches')}</h2>
          <Row gutter={16}>
            <Col {...colProps}>
              <Form.Item
                label={t('switches.agentEnable')}
                name={'agent_enable'}
                valuePropName="checked"
              >
                <Switch />
              </Form.Item>
            </Col>
            <Col {...colProps}>
              <Form.Item
                label={t('switches.google2faEnable')}
                name="google2fa_enable"
                valuePropName="checked"
              >
                <Switch />
              </Form.Item>
            </Col>
            <Col {...colProps}>
              <Form.Item
                label={t('switches.withdrawGoogle2faEnable')}
                name={'withdraw_google2fa_enable'}
                valuePropName="checked"
              >
                <Switch />
              </Form.Item>
            </Col>
            <Col {...colProps}>
              <Form.Item
                label={t('switches.transactionEnable')}
                name={'transaction_enable'}
                valuePropName="checked"
              >
                <Switch />
              </Form.Item>
            </Col>
            <Col {...colProps}>
              <Form.Item
                label={t('switches.withdrawEnable')}
                name={'withdraw_enable'}
                valuePropName="checked"
              >
                <Switch />
              </Form.Item>
            </Col>
            <Col {...colProps}>
              <Form.Item
                label={t('switches.agencyWithdrawEnable')}
                name={'agency_withdraw_enable'}
                valuePropName="checked"
              >
                <Switch />
              </Form.Item>
            </Col>
          </Row>

          <Divider />
          <h2 className="mb-4">{t('sections.walletInfo')}</h2>
          <Row gutter={16}>
            <Col xs={24} md={8}>
              <Form.Item
                label={t('withdraw.withdrawMinAmount')}
                name={'withdraw_min_amount'}
                rules={[{ required: true }]}
              >
                <InputNumber className="w-full" />
              </Form.Item>
            </Col>
            <Col xs={24} md={8}>
              <Form.Item
                label={t('withdraw.withdrawMaxAmount')}
                name={'withdraw_max_amount'}
                rules={[{ required: true }]}
              >
                <InputNumber className="w-full" />
              </Form.Item>
            </Col>
          </Row>
          <Row gutter={16}>
            <Col {...colProps}>
              <Form.Item
                label={t('withdraw.withdrawFeeAmount')}
                name={'withdraw_fee'}
                rules={[{ required: true }]}
              >
                <InputNumber className="w-full" />
              </Form.Item>
            </Col>
            <Col xs={24} md={8}>
              <Form.Item
                label={t('withdraw.withdrawFeePercent')}
                name={'withdraw_fee_percent'}
                rules={[{ required: true }]}
              >
                <InputNumber className="w-full" />
              </Form.Item>
            </Col>
            <Col {...colProps}>
              <Form.Item
                label={t('withdraw.additionalWithdrawFee')}
                name={'additional_withdraw_fee'}
                rules={[{ required: true }]}
              >
                <InputNumber className="w-full" />
              </Form.Item>
            </Col>
          </Row>
          <Row gutter={16}>
            <Col {...colProps}>
              <Form.Item
                label={t('withdraw.agencyMinAmount')}
                name={'agency_withdraw_min_amount'}
                rules={[{ required: true }]}
              >
                <InputNumber className="w-full" />
              </Form.Item>
            </Col>
            <Col {...colProps}>
              <Form.Item
                label={t('withdraw.agencyMaxAmount')}
                name={'agency_withdraw_max_amount'}
                rules={[{ required: true }]}
              >
                <InputNumber className="w-full" />
              </Form.Item>
            </Col>
          </Row>
          <Row gutter={16}>
            <Col {...colProps}>
              <Form.Item
                label={t('withdraw.agencyFeeAmount')}
                name={'agency_withdraw_fee_dollar'}
                rules={[{ required: true }]}
              >
                <InputNumber className="w-full" />
              </Form.Item>
            </Col>
            <Col {...colProps}>
              <Form.Item
                label={t('withdraw.agencyFeePercent')}
                name={'agency_withdraw_fee'}
                rules={[{ required: true }]}
              >
                <InputNumber className="w-full" />
              </Form.Item>
            </Col>
            <Col {...colProps}>
              <Form.Item
                label={t('withdraw.additionalAgencyFee')}
                name={'additional_agency_withdraw_fee'}
                rules={[{ required: true }]}
              >
                <InputNumber className="w-full" />
              </Form.Item>
            </Col>
          </Row>

          <Divider />
          <Typography.Title level={4}>
            {t('sections.channelInfo')}
          </Typography.Title>
          <Form.List
            name={'user_channels'}
            initialValue={channelGroups?.map(channelGroup => ({
              channel_group_id: channelGroup.id,
            }))}
          >
            {fields =>
              fields.map(({ key, name }, index) => (
                <div key={key}>
                  <Form.Item name={[name, 'channel_group_id']} hidden />
                  <Row gutter={16}>
                    <Col xs={24} md={8}>
                      <Form.Item label={t('channel.name')}>
                        <TextField value={channelGroups?.[index].name} />
                      </Form.Item>
                    </Col>
                    <Col xs={24} md={8}>
                      <Row gutter={16}>
                        <Col span={11}>
                          <Form.Item
                            label={t('channel.amountLimit')}
                            name={[name, 'min_amount']}
                          >
                            <InputNumber className="w-full" />
                          </Form.Item>
                        </Col>
                        <Col span={2}>
                          <Form.Item label=" ">~</Form.Item>
                        </Col>
                        <Col span={11}>
                          <Form.Item label=" " name={[name, 'max_amount']}>
                            <InputNumber className="w-full" />
                          </Form.Item>
                        </Col>
                      </Row>
                    </Col>
                    <Col xs={24} md={8}>
                      <Form.Item
                        label={t('channel.feePercent')}
                        name={[name, 'fee_percent']}
                      >
                        <InputNumber className="w-full" />
                      </Form.Item>
                    </Col>
                  </Row>
                </div>
              ))
            }
          </Form.List>
        </Form>
      </Create>
    </>
  );
};

export default MerchantCreate;
