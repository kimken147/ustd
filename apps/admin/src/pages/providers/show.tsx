import { EditOutlined } from '@ant-design/icons';
import {
  DateField,
  RefreshButton,
  Show,
  ShowButton,
  TextField,
  useForm,
} from '@refinedev/antd';
import {
  Button,
  Col,
  ColProps,
  Descriptions,
  Divider,
  Form,
  Input,
  InputNumber,
  Modal,
  Row,
  Space,
  Spin,
  Switch,
  Typography,
} from 'antd';
import {
  IResourceComponentsProps,
  useApiUrl,
  useCan,
  useShow,
  useUpdate,
} from '@refinedev/core';
import {
  NavLink,
  useLocation,
  useNavigate,
} from 'react-router-dom';
import EditableForm from 'components/EditableFormItem';
import useUpdateModal from 'hooks/useUpdateModal';
import { Provider, UserChannel } from 'interfaces/provider';
import { FC, useState } from 'react';
import { Helmet } from 'react-helmet';
import { updateMerchantFormItems } from './list';
import { useTranslation } from 'react-i18next';

const getColProps = (props: ColProps) => {
  return {
    sm: 24,
    md: 12,
    ...props,
  };
};

const ChannelForm: FC<{ record: UserChannel; disabled?: boolean }> = ({
  record,
  disabled,
}) => {
  const { t } = useTranslation('providers');
  const { t: tc } = useTranslation(); // common namespace
  const { formProps, form } = useForm();
  const colProps: ColProps = {
    sm: 24,
    md: 12,
  };
  const { Modal } = useUpdateModal();
  const [isEditing, setEditing] = useState(false);
  return (
    <Form
      {...formProps}
      initialValues={{
        ...record,
        fee_percent: record.fee_percent ?? 0,
      }}
      layout="vertical"
      onFinish={async (values: any) => {
        Modal.confirm({
          id: record.id,
          values: {
            ...record,
            ...values,
            status:
              (values.status as any) === true || values.status === 1 ? 1 : 0,
            min_amount: values.min_amount?.toString(),
            max_amount: values.max_amount?.toString(),
            fixed: false,
          },
          title: t('confirmation.updateChannel'),
          resource: 'user-channels',
          onSuccess() {
            setEditing(false);
          },
        });
      }}
    >
      <Row gutter={16} className="w-full">
        <Col {...colProps} lg={4}>
          <Form.Item label={t('channel.name')}>
            <TextField value={record.name} />
          </Form.Item>
        </Col>
        <Col {...colProps} lg={8}>
          <Row gutter={8}>
            <Col span={11}>
              <Form.Item label={t('channel.amountLimit')} name={'min_amount'}>
                <InputNumber disabled={!isEditing} className="w-full" />
              </Form.Item>
            </Col>
            <Col span={2} className="flex justify-center items-center">
              <TextField value={'~'} />
            </Col>
            <Col span={11}>
              <Form.Item label=" " name={'max_amount'}>
                <InputNumber disabled={!isEditing} className="w-full" />
              </Form.Item>
            </Col>
          </Row>
        </Col>
        <Col {...colProps} lg={4}>
          <Form.Item label={t('channel.feePercent')} name={'fee_percent'}>
            <InputNumber className="w-full" disabled={!isEditing} />
          </Form.Item>
        </Col>
        <Col {...colProps} lg={4}>
          <Row gutter={8}>
            <Col span={12}>
              <Form.Item
                label={t('channel.status')}
                name={'status'}
                valuePropName="checked"
              >
                <Switch disabled={!isEditing} />
              </Form.Item>
            </Col>
            {/* <Col span={12}>
                                <Form.Item label="实名制" name={"real_name_enable"} valuePropName="checked">
                                    <Switch disabled={!isEditing} />
                                </Form.Item>
                            </Col> */}
          </Row>
        </Col>
        <Col {...colProps} lg={4} className="flex items-center">
          {isEditing ? (
            <Row className="w-full" gutter={8}>
              <Col span={12}>
                <Button
                  onClick={() => {
                    form.resetFields();
                    setEditing(false);
                  }}
                  block
                >
                  {tc('cancel')}
                </Button>
              </Col>
              <Col span={12}>
                <Button block type="primary" htmlType="submit">
                  {tc('submit')}
                </Button>
              </Col>
            </Row>
          ) : (
            <Button
              type="primary"
              disabled={disabled}
              block
              onClick={() => setEditing(true)}
            >
              {tc('edit')}
            </Button>
          )}
        </Col>
      </Row>
    </Form>
  );
};

const PaymentForm: FC<{
  record: Provider;
  minField: keyof Provider;
  maxField: keyof Provider;
  feeField?: keyof Provider;
  withdrawField?: keyof Provider;
  disabled?: boolean;
  label: string;
  hideStatus?: boolean;
}> = ({
  record,
  disabled,
  minField,
  maxField,
  label,
  feeField,
  withdrawField,
}) => {
  const { t } = useTranslation('providers');
  const { t: tc } = useTranslation(); // common namespace
  const { formProps, form } = useForm();
  const { Modal } = useUpdateModal();
  const [isEditing, setEditing] = useState(false);

  return (
    <Form
      {...formProps}
      initialValues={{
        ...record,
      }}
      layout="vertical"
      onFinish={async (values: any) => {
        Modal.confirm({
          id: record.id,
          values: {
            ...values,
          },
          title: t('confirmation.updateWithdraw'),
          onSuccess() {
            setEditing(false);
          },
        });
      }}
    >
      <Row gutter={16}>
        <Col {...getColProps({ lg: 4 })}>
          <Form.Item label=" ">
            <TextField value={label} />
          </Form.Item>
        </Col>
        <Col {...getColProps({ lg: 8 })}>
          <Row gutter={8}>
            <Col span={11}>
              <Form.Item label={t('withdraw.amountLimit')} name={minField}>
                <InputNumber disabled={!isEditing} className="w-full" />
              </Form.Item>
            </Col>
            <Col span={2} className="flex justify-center items-center">
              <TextField value={'~'} />
            </Col>
            <Col span={11}>
              <Form.Item label=" " name={maxField}>
                <InputNumber disabled={!isEditing} className="w-full" />
              </Form.Item>
            </Col>
          </Row>
        </Col>
        <Col {...getColProps({ lg: 4 })}>
          {feeField ? (
            <Form.Item label={t('withdraw.feePerTransaction')} name={feeField}>
              <InputNumber disabled={!isEditing} className="w-full" />
            </Form.Item>
          ) : null}
        </Col>
        <Col {...getColProps({ lg: 4 })}>
          {withdrawField ? (
            <Form.Item
              label={t('withdraw.status')}
              name={withdrawField}
              valuePropName="checked"
            >
              <Switch disabled={!isEditing} />
            </Form.Item>
          ) : null}
        </Col>
        <Col {...getColProps({ lg: 4 })} className="flex items-center">
          {isEditing ? (
            <Row className="w-full" gutter={8}>
              <Col span={12}>
                <Button
                  onClick={() => {
                    form.resetFields();
                    setEditing(false);
                  }}
                  block
                >
                  {tc('cancel')}
                </Button>
              </Col>
              <Col span={12}>
                <Button block type="primary" htmlType="submit">
                  {tc('submit')}
                </Button>
              </Col>
            </Row>
          ) : (
            <Button
              type="primary"
              disabled={disabled}
              block
              onClick={() => setEditing(true)}
            >
              {tc('edit')}
            </Button>
          )}
        </Col>
      </Row>
    </Form>
  );
};

const ProviderShow: FC<IResourceComponentsProps<Provider>> = () => {
  const { t } = useTranslation('providers');
  const { t: tc } = useTranslation(); // common namespace
  const { data: canEdit } = useCan({
    resource: 'merchants',
    action: '4',
  });
  const apiUrl = useApiUrl();
  const navigate = useNavigate();

  const { state } = useLocation();
  const { queryResult } = useShow<Provider>();
  const { data, isLoading } = queryResult;
  const record = {
    ...(state as Provider),
    ...data?.data,
  };

  const { mutateAsync: updateProvider } = useUpdate<Provider>();
  const {
    show,
    Modal: UpdateModal,
    modalProps,
  } = useUpdateModal<Provider>({
    formItems: updateMerchantFormItems(t),
    transferFormValues(record) {
      const values = { ...record };
      if (values.balance_delta) {
        values.balance_delta =
          values.type === 'add' ? values.balance_delta : -values.balance_delta;
      }
      if (values.frozen_balance_delta) {
        values.frozen_balance_delta =
          values.type === 'add'
            ? values.frozen_balance_delta
            : -values.frozen_balance_delta;
      }
      return values;
    },
  });

  const updateProviderWithConfirm = (
    record: Provider,
    field: keyof Provider,
    value: any
  ) => {
    Modal.confirm({
      title: tc('notifications.editSuccess', { resource: t('name') }),
      onOk: async () => {
        await updateProvider({
          id: record.id,
          values: {
            id: record.id,
            [field]: +value,
          },
          resource: 'providers',
          successNotification: {
            message: tc('notifications.editSuccess', { resource: t('name') }),
            type: 'success',
          },
        });
      },
    });
  };

  if (isLoading || !record)
    return (
      <div className="flex items-center justify-center h-full">
        <Spin />
      </div>
    );
  return (
    <>
      <Helmet>
        <title>{t('titles.show')}</title>
      </Helmet>
      <Show
        isLoading={isLoading}
        title={`${t('name')}: ${record?.name}`}
        headerButtons={() => (
          <>
            <RefreshButton></RefreshButton>
          </>
        )}
      >
        <Descriptions
          title={t('sections.accountInfo')}
          bordered
          column={{ xs: 1, md: 2, lg: 3 }}
          size="small"
        >
          <Descriptions.Item label={t('fields.name')}>
            <EditableForm
              id={record?.id || 0}
              name="name"
              disabled={!canEdit?.can}
            >
              <Input
                defaultValue={record?.name}
                className="w-full !text-stone-500"
              />
            </EditableForm>
          </Descriptions.Item>
          <Descriptions.Item label={t('fields.username')}>
            <EditableForm
              id={record?.id}
              name="username"
              disabled={!canEdit?.can}
            >
              <Input
                defaultValue={record?.username}
                disabled
                className="w-full !text-stone-500"
              />
            </EditableForm>
          </Descriptions.Item>
          <Descriptions.Item label={t('fields.password')}>
            <Space>
              <Button
                danger
                type="primary"
                disabled={!canEdit?.can}
                onClick={() => {
                  UpdateModal.confirm({
                    title: t('confirmation.resetPassword'),
                    id: '',
                    customMutateConfig: {
                      url: `${apiUrl}/providers/${record.id}/password-resets`,
                      method: 'post',
                    },
                    onSuccess: data => {
                      navigate(`/providers/show/${data?.id}`, {
                        state: {
                          ...(state as Provider),
                          ...data,
                          ...record,
                        },
                        replace: true,
                      });
                    },
                  });
                }}
              >
                {t('resetPassword', {
                  ns: 'common',
                })}
              </Button>
              {record?.password ? (
                <TextField value={record.password} copyable />
              ) : null}
            </Space>
          </Descriptions.Item>
          <Descriptions.Item label={t('fields.registerTime')}>
            {record?.created_at ? (
              <DateField
                value={record?.created_at}
                format="YYYY-MM-DD HH:mm:ss"
              />
            ) : (
              '无'
            )}
          </Descriptions.Item>
          <Descriptions.Item label={t('fields.lastLoginTime')}>
            {record?.last_login_at ? (
              <DateField
                value={record?.last_login_at}
                format="YYYY-MM-DD HH:mm:ss"
              />
            ) : (
              '-'
            )}
          </Descriptions.Item>
          <Descriptions.Item label={t('info.lastLoginIp')}>
            {record?.last_login_ipv4 ? (
              <TextField value={record?.last_login_ipv4} />
            ) : (
              '-'
            )}
          </Descriptions.Item>
          <Descriptions.Item label={t('fields.agentId')}>
            <Space>
              {record.agent?.id && (
                <ShowButton icon={null} recordItemId={record.agent?.id}>
                  {record.agent.name}
                </ShowButton>
              )}
            </Space>
            {/* {record?.agent ? <TextField value={record.agent.name} /> : "无"} */}
          </Descriptions.Item>
          <Descriptions.Item label={t('fields.phone')}>
            <EditableForm
              id={record?.id || 0}
              name="phone"
              disabled={!canEdit?.can}
            >
              <Input
                defaultValue={record?.phone}
                className="w-full !text-stone-500"
              />
            </EditableForm>
          </Descriptions.Item>
          <Descriptions.Item label={t('fields.contact')}>
            <EditableForm
              id={record?.id || 0}
              name="contact"
              disabled={!canEdit?.can}
            >
              <Input
                defaultValue={record?.contact}
                className="w-full !text-stone-500"
              />
            </EditableForm>
          </Descriptions.Item>
          <Descriptions.Item label={t('fields.google2faSecret')}>
            <Space>
              <Button
                disabled={!canEdit?.can}
                danger
                type="primary"
                onClick={() =>
                  UpdateModal.confirm({
                    title: t('confirmation.resetGoogle2faSecret'),
                    id: 0,
                    customMutateConfig: {
                      method: 'post',
                      url: `${apiUrl}/providers/${record.id}/google2fa-secret-resets`,
                    },
                    onSuccess: data => {
                      navigate(`/providers/show/${data?.id}`, {
                        state: {
                          ...(state as Provider),
                          ...record,
                          ...data,
                        },
                        replace: true,
                      });
                    },
                  })
                }
              >
                {t('actions.resetGoogle2faSecret')}
              </Button>
              {record.google2fa_secret ? (
                <TextField value={record.google2fa_secret} copyable />
              ) : null}
            </Space>
          </Descriptions.Item>
          {record.google2fa_qrcode ? (
            <Descriptions.Item label={`${t('fields.google2fa')} QRCode`}>
              <div
                dangerouslySetInnerHTML={{
                  __html: record?.google2fa_qrcode,
                }}
              />
            </Descriptions.Item>
          ) : null}
        </Descriptions>
        <Divider />
        <Descriptions
          bordered
          size="small"
          column={{ xs: 1, md: 2, lg: 3 }}
          title={t('sections.functionSwitches')}
          className="mt-10"
        >
          <Descriptions.Item label={t('sections.accountEnableSwitch')}>
            <Switch
              disabled={!canEdit?.can}
              checked={!!record?.status}
              onChange={value =>
                updateProviderWithConfirm(record, 'status', +value)
              }
            />
          </Descriptions.Item>
          <Descriptions.Item label={t('switches.transactionEnable')}>
            <Switch
              disabled={!canEdit?.can}
              defaultChecked={record?.transaction_enable}
              onChange={value =>
                updateProviderWithConfirm(record, 'transaction_enable', +value)
              }
            />
          </Descriptions.Item>
          <Descriptions.Item label={t('switches.agentEnable')}>
            <Switch
              disabled={!canEdit?.can}
              defaultChecked={record?.agent_enable}
              onChange={value =>
                updateProviderWithConfirm(record, 'agent_enable', +value)
              }
            />
          </Descriptions.Item>
          <Descriptions.Item label={t('switches.google2fa')}>
            <Switch
              disabled={!canEdit?.can}
              defaultChecked={record?.google2fa_enable}
              onChange={value =>
                updateProviderWithConfirm(record, 'google2fa_enable', +value)
              }
            />
          </Descriptions.Item>
          {/* <Descriptions.Item label="信用模式">
                        <Switch
                            disabled={!canEdit?.can}
                            defaultChecked={record?.credit_mode_enable}
                            onChange={(value) => updateProviderWithConfirm(record, "credit_mode_enable", +value)}
                        />
                    </Descriptions.Item> */}
          <Descriptions.Item label={t('switches.depositEnable')}>
            <Switch
              disabled={!canEdit?.can}
              defaultChecked={record?.deposit_enable}
              onChange={value =>
                updateProviderWithConfirm(record, 'deposit_enable', +value)
              }
            />
          </Descriptions.Item>
          {/* <Descriptions.Item label="站内转点">
                        <Switch
                            disabled={!canEdit?.can}
                            defaultChecked={record?.balance_transfer_enable}
                            onChange={(value) => updateProviderWithConfirm(record, "balance_transfer_enable", +value)}
                        />
                    </Descriptions.Item> */}
          <Descriptions.Item label={t('switches.paufenDepositEnable')}>
            <Switch
              disabled={!canEdit?.can}
              defaultChecked={record?.paufen_deposit_enable}
              onChange={value =>
                updateProviderWithConfirm(
                  record,
                  'paufen_deposit_enable',
                  +value
                )
              }
            />
          </Descriptions.Item>
          <Descriptions.Item label={t('switches.verifyMoneyIn')}>
            <Switch
              disabled={!canEdit?.can}
              defaultChecked={record?.cancel_order_enable}
              onChange={value =>
                updateProviderWithConfirm(record, 'cancel_order_enable', +value)
              }
            />
          </Descriptions.Item>
        </Descriptions>
        <Divider />
        <Descriptions
          title={t('wallet.title')}
          bordered
          column={{ xs: 1, md: 2, lg: 4 }}
          className={'mt-10'}
        >
          <Descriptions.Item label={t('wallet.totalBalance')}>
            <Space>
              <TextField value={record.wallet.balance} />
              {canEdit?.can ? (
                <EditOutlined
                  style={{
                    color: '#6eb9ff',
                  }}
                  onClick={() =>
                    show({
                      initialValues: {
                        type: 'add',
                      },
                      filterFormItems: ['type', 'balance_delta', 'note'],
                      id: record.id,
                      title: t('wallet.editTotalBalance'),
                    })
                  }
                />
              ) : null}
            </Space>
          </Descriptions.Item>
          <Descriptions.Item label={t('wallet.frozenBalance')}>
            <Space>
              <TextField value={record.wallet.frozen_balance} />
              {canEdit?.can ? (
                <EditOutlined
                  style={{
                    color: '#6eb9ff',
                  }}
                  onClick={() =>
                    show({
                      initialValues: {
                        type: 'add',
                      },
                      filterFormItems: ['type', 'frozen_balance_delta', 'note'],
                      id: record.id,
                      title: t('wallet.editFrozenBalance'),
                    })
                  }
                />
              ) : null}
            </Space>
          </Descriptions.Item>
          <Descriptions.Item label={t('wallet.availableBalance')}>
            <TextField value={record.wallet.available_balance} />
          </Descriptions.Item>
          <Descriptions.Item label={t('wallet.profit')}>
            <Space>
              <TextField value={record.wallet.profit} />
              {canEdit?.can ? (
                <EditOutlined
                  style={{
                    color: '#6eb9ff',
                  }}
                  onClick={() =>
                    show({
                      initialValues: {
                        type: 'add',
                      },
                      filterFormItems: ['type', 'profit_delta', 'note'],
                      id: record.id,
                      title: t('wallet.editProfit'),
                    })
                  }
                />
              ) : null}
            </Space>
          </Descriptions.Item>
          <Descriptions.Item label={t('wallet.walletHistory')}>
            <NavLink
              to={{
                pathname: `/providers/user-wallet-history`,
                search: `?user_id=${record.id}`,
              }}
            >
              <Button>{t('wallet.walletButton')}</Button>
            </NavLink>
            {/* <ListButton resourceNameOrRouteName="user-wallet-history">钱包</ListButton> */}
          </Descriptions.Item>
        </Descriptions>
        <Divider />
        <Typography.Title level={5}>{t('channel.title')}</Typography.Title>
        <Space direction="vertical" className="w-full" size={'large'}>
          {record.user_channels.map(userChannel => (
            <ChannelForm
              key={userChannel.id}
              disabled={!canEdit?.can}
              record={userChannel}
            />
          ))}
        </Space>
        <Divider />
        <Typography.Title level={5}>{t('withdraw.title')}</Typography.Title>
        <Space direction="vertical" className="w-full" size={'large'}>
          <PaymentForm
            record={record}
            minField="withdraw_min_amount"
            maxField="withdraw_max_amount"
            label={t('withdraw.balanceWithdraw')}
            disabled={!canEdit?.can}
            withdrawField="withdraw_enable"
            feeField="withdraw_fee"
          />
          <PaymentForm
            record={record}
            minField="withdraw_profit_min_amount"
            maxField="withdraw_profit_max_amount"
            label={t('withdraw.profitWithdraw')}
            disabled={!canEdit?.can}
            withdrawField="withdraw_profit_enable"
            feeField="withdraw_profit_fee"
          />
        </Space>
        <Divider />
        <Typography.Title level={5}>
          {t('withdraw.paufenDepositTitle')}
        </Typography.Title>
        <PaymentForm
          record={record}
          minField="agency_withdraw_min_amount"
          maxField="agency_withdraw_max_amount"
          label={t('withdraw.paufenDeposit')}
          disabled={!canEdit?.can}
        />
      </Show>
      <Modal {...modalProps} />
    </>
  );
};

export default ProviderShow;
