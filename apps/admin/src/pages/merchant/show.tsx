import { EditOutlined, LinkOutlined } from '@ant-design/icons';
import {
  Button,
  Col,
  ColProps,
  DateField,
  Descriptions,
  Divider,
  Form,
  Input,
  InputNumber,
  Modal,
  RefreshButton,
  Row,
  Select,
  Show,
  ShowButton,
  Space,
  Spin,
  Switch,
  TextField,
  Typography,
  useForm,
} from '@refinedev/antd';
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
} from '@refinedev/react-router-v6';
import EditableForm from 'components/EditableFormItem';
import useUpdateModal from 'hooks/useUpdateModal';
import useUser from 'hooks/useUser';
import { SelectOption, Merchant, MerchantUserChannel as UserChannel, User } from '@morgan-ustd/shared';
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
  const { t } = useTranslation('merchant');
  const { formProps, form } = useForm();
  const colProps: ColProps = { sm: 24, md: 12 };
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
            <Col span={12}>
              <Form.Item
                label={t('channel.realNameEnable')}
                name={'real_name_enable'}
                valuePropName="checked"
              >
                <Switch disabled={!isEditing} />
              </Form.Item>
            </Col>
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
                  {t('actions.cancel')}
                </Button>
              </Col>
              <Col span={12}>
                <Button block type="primary" htmlType="submit">
                  {t('actions.submit')}
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
              {t('actions.edit')}
            </Button>
          )}
        </Col>
      </Row>
    </Form>
  );
};

const PaymentForm: FC<{
  record: Merchant;
  type: 'withdraw' | 'agency';
  disabled?: boolean;
}> = ({ record, type, disabled }) => {
  const { t } = useTranslation('merchant');
  const { formProps, form } = useForm();
  const { Modal } = useUpdateModal();
  const [isEditing, setEditing] = useState(false);

  const displayType =
    type === 'withdraw' ? t('withdraw.withdraw') : t('withdraw.agency');
  const paufenLabel =
    type === 'withdraw'
      ? t('withdraw.paufenWithdraw')
      : t('withdraw.paufenAgency');

  const getName = (name: keyof Merchant): keyof Merchant => {
    if (type === 'agency') {
      if (name === 'paufen_withdraw_enable') {
        return 'paufen_agency_withdraw_enable';
      } else {
        return `${type}_${name}` as keyof Merchant;
      }
    }
    return name;
  };

  return (
    <Form
      {...formProps}
      initialValues={{
        ...record,
        additional_withdraw_fee: record['additional_withdraw_fee'] ?? 0,
        withdraw_fee_percent: record['withdraw_fee_percent'] ?? 0,
        withdraw_fee: record['withdraw_fee'] ?? 0,
        agency_withdraw_fee: record.agency_withdraw_fee ?? 0,
        agency_withdraw_fee_dollar: record.agency_withdraw_fee_dollar ?? 0,
        additional_agency_withdraw_fee:
          record.additional_agency_withdraw_fee ?? 0,
      }}
      layout="vertical"
      onFinish={async (values: any) => {
        Modal.confirm({
          id: record.id,
          values: { ...values },
          title: t('confirmation.updateWithdraw'),
          onSuccess() {
            setEditing(false);
          },
        });
      }}
    >
      <Row gutter={16}>
        <Col {...getColProps({ lg: 2 })}>
          <Form.Item label=" ">
            <TextField value={displayType} />
          </Form.Item>
        </Col>
        <Col {...getColProps({ lg: 8 })}>
          <Row gutter={8}>
            <Col span={11}>
              <Form.Item
                label={t('withdraw.amountLimit')}
                name={getName('withdraw_min_amount')}
              >
                <InputNumber disabled={!isEditing} className="w-full" />
              </Form.Item>
            </Col>
            <Col span={2} className="flex justify-center items-center">
              <TextField value={'~'} />
            </Col>
            <Col span={11}>
              <Form.Item label=" " name={getName('withdraw_max_amount')}>
                <InputNumber disabled={!isEditing} className="w-full" />
              </Form.Item>
            </Col>
          </Row>
        </Col>
        <Col {...getColProps({ lg: 6 })}>
          <Row gutter={8}>
            <Col span={8}>
              <Form.Item
                label={t('withdraw.feeAmount')}
                name={
                  type === 'agency'
                    ? 'agency_withdraw_fee_dollar'
                    : 'withdraw_fee'
                }
              >
                <InputNumber disabled={!isEditing} className="w-full" />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item
                label={t('withdraw.feePercent')}
                name={
                  type === 'agency'
                    ? 'agency_withdraw_fee'
                    : 'withdraw_fee_percent'
                }
              >
                <InputNumber disabled={!isEditing} className="w-full" />
              </Form.Item>
            </Col>
          </Row>
        </Col>
        <Col {...getColProps({ lg: 4 })}>
          <Row gutter={8}>
            <Col span={8}>
              <Form.Item
                label={displayType}
                name={getName('withdraw_enable')}
                valuePropName="checked"
              >
                <Switch disabled={!isEditing} />
              </Form.Item>
            </Col>
            <Col span={8}>
              <Form.Item
                label={paufenLabel}
                name={getName('paufen_withdraw_enable')}
                valuePropName="checked"
              >
                <Switch disabled={!isEditing} />
              </Form.Item>
            </Col>
          </Row>
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
                  {t('actions.cancel')}
                </Button>
              </Col>
              <Col span={12}>
                <Button block type="primary" htmlType="submit">
                  {t('actions.submit')}
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
              {t('actions.edit')}
            </Button>
          )}
        </Col>
      </Row>
    </Form>
  );
};

const MerchantShow: FC<IResourceComponentsProps<Merchant>> = () => {
  const { t } = useTranslation('merchant');
  const { data: canEdit } = useCan({
    resource: 'merchants',
    action: '4',
  });
  const apiUrl = useApiUrl();
  const navigate = useNavigate();

  const { state } = useLocation();
  const { queryResult } = useShow<Merchant>();
  const { data, isLoading } = queryResult;
  const record = {
    ...(state as Merchant),
    ...data?.data,
  };

  const { users } = useUser({
    role: 3,
    agent_enable: true,
  });

  const { mutateAsync: updateMerchant } = useUpdate<Merchant>();
  const {
    show,
    Modal: UpdateModal,
    modalProps,
  } = useUpdateModal<Merchant>({
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

  const updateMerchantWithConfirm = (
    record: Merchant,
    field: keyof User,
    value: any
  ) => {
    Modal.confirm({
      title: t('confirmation.modify'),
      onOk: async () => {
        await updateMerchant({
          id: record.id,
          values: {
            id: record.id,
            [field]: +value,
          },
          resource: 'merchants',
          successNotification: {
            message: t('messages.updateSuccess'),
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
        <title>{t('titles.info')}</title>
      </Helmet>
      <Show
        isLoading={isLoading}
        title={`${t('fields.name')}: ${record?.name}`}
        headerButtons={() => (
          <>
            <RefreshButton>{t('actions.refresh')}</RefreshButton>
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
              initialValues={record}
              key={record?.id || 0}
            >
              <Input className="w-full !text-stone-500" />
            </EditableForm>
          </Descriptions.Item>
          <Descriptions.Item label={t('fields.username')}>
            <EditableForm
              id={record?.id}
              name="username"
              disabled={!canEdit?.can}
              initialValues={record}
              key={record?.id || 0}
            >
              <Input disabled className="w-full !text-stone-500" />
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
                      url: `${apiUrl}/merchants/${record.id}/password-resets`,
                      method: 'post',
                    },
                    onSuccess: data => {
                      navigate(`/merchants/show/${data?.id}`, {
                        state: {
                          ...(state as Merchant),
                          ...data,
                          ...record,
                        },
                        replace: true,
                      });
                    },
                  });
                }}
              >
                {t('actions.resetPassword')}
              </Button>
              {record?.password ? (
                <TextField value={record.password} copyable />
              ) : null}
            </Space>
          </Descriptions.Item>
          <Descriptions.Item label={t('fields.secret')}>
            <Space>
              <Button
                danger
                disabled={!canEdit?.can}
                type="primary"
                onClick={() => {
                  UpdateModal.confirm({
                    title: t('confirmation.resetSecret'),
                    id: 0,
                    customMutateConfig: {
                      url: `${apiUrl}/merchants/${record.id}/secret-resets`,
                      method: 'post',
                    },
                    onSuccess(data) {
                      navigate(`/merchants/show/${data?.id}`, {
                        state: {
                          ...(state as Merchant),
                          ...data,
                          ...record,
                        },
                        replace: true,
                      });
                    },
                  });
                }}
              >
                {t('actions.resetSecret')}
              </Button>
              {record?.secret ? (
                <TextField value={record?.secret} copyable />
              ) : null}
            </Space>
          </Descriptions.Item>
          <Descriptions.Item label={t('fields.lastLoginTime')}>
            {record?.last_login_at ? (
              <DateField
                value={record?.last_login_at}
                format="YYYY-MM-DD HH:mm:ss"
              />
            ) : (
              t('placeholders.none')
            )}
          </Descriptions.Item>
          <Descriptions.Item label={t('fields.lastLoginIp')}>
            {record?.last_login_ipv4 ? (
              <TextField value={record?.last_login_ipv4} />
            ) : (
              t('placeholders.none')
            )}
          </Descriptions.Item>
          <Descriptions.Item label={t('fields.agentId')}>
            <Space>
              <EditableForm
                id={record.id}
                name="parent_id"
                initialValues={{ parent_id: record.agent?.id }}
                style={{ width: 200 }}
                disabled={!canEdit?.can}
                key={record?.id || 0}
              >
                <Select
                  className="w-[300px]"
                  allowClear
                  options={[
                    {
                      label: t('placeholders.none'),
                      value: null,
                    },
                    ...(users || []).map<SelectOption>(user => ({
                      label: user.name,
                      value: user.id,
                    })),
                  ]}
                />
              </EditableForm>
              {record.agent?.id && (
                <ShowButton
                  icon={<LinkOutlined />}
                  recordItemId={record.agent?.id}
                  hideText
                />
              )}
            </Space>
          </Descriptions.Item>
          <Descriptions.Item label={t('fields.phone')}>
            <EditableForm
              id={record?.id || 0}
              name="phone"
              disabled={!canEdit?.can}
              initialValues={record}
              key={record?.id || 0}
            >
              <Input className="w-full !text-stone-500" />
            </EditableForm>
          </Descriptions.Item>
          <Descriptions.Item label={t('fields.contact')}>
            <EditableForm
              id={record?.id || 0}
              name="contact"
              disabled={!canEdit?.can}
              initialValues={record}
              key={record?.id || 0}
            >
              <Input className="w-full !text-stone-500" />
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
                      url: `${apiUrl}/merchants/${record.id}/google2fa-secret-resets`,
                    },
                    onSuccess: data => {
                      navigate(`/merchants/show/${data?.id}`, {
                        state: {
                          ...(state as Merchant),
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
            <Descriptions.Item label={t('fields.google2faQrCode')}>
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
          <Descriptions.Item label={t('switches.accountEnable')}>
            <Switch
              disabled={!canEdit?.can}
              checked={!!record?.status}
              onChange={value =>
                updateMerchantWithConfirm(record, 'status', +value)
              }
            />
          </Descriptions.Item>
          <Descriptions.Item
            label={t('switches.transactionEnable')}
            key={record.id}
          >
            <Switch
              disabled={!canEdit?.can}
              checked={record.transaction_enable}
              onChange={value =>
                updateMerchantWithConfirm(record, 'transaction_enable', +value)
              }
            />
          </Descriptions.Item>
          <Descriptions.Item label={t('switches.agentEnable')}>
            <Switch
              disabled={!canEdit?.can}
              checked={record?.agent_enable}
              onChange={value =>
                updateMerchantWithConfirm(record, 'agent_enable', +value)
              }
            />
          </Descriptions.Item>
          <Descriptions.Item label={t('switches.google2faEnable')}>
            <Switch
              disabled={!canEdit?.can}
              checked={record?.google2fa_enable}
              onChange={value =>
                updateMerchantWithConfirm(record, 'google2fa_enable', +value)
              }
            />
          </Descriptions.Item>
          <Descriptions.Item label={t('switches.withdrawGoogle2faEnable')}>
            <Switch
              disabled={!canEdit?.can}
              checked={record?.withdraw_google2fa_enable}
              onChange={value =>
                updateMerchantWithConfirm(
                  record,
                  'withdraw_google2fa_enable',
                  +value
                )
              }
            />
          </Descriptions.Item>
          <Descriptions.Item label={t('switches.thirdChannelEnable')}>
            <Switch
              checked={record?.third_channel_enable}
              onChange={value =>
                updateMerchantWithConfirm(
                  record,
                  'third_channel_enable',
                  +value
                )
              }
            />
          </Descriptions.Item>
        </Descriptions>

        <Divider />

        <Descriptions
          title={t('sections.walletInfo')}
          bordered
          column={{ xs: 1, md: 2, lg: 4 }}
          className={'mt-10'}
        >
          <Descriptions.Item label={t('wallet.totalBalance')}>
            <Space>
              <TextField value={record.wallet.balance} />
              {canEdit?.can ? (
                <EditOutlined
                  style={{ color: '#6eb9ff' }}
                  onClick={() =>
                    show({
                      initialValues: { type: 'add' },
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
                  style={{ color: '#6eb9ff' }}
                  onClick={() =>
                    show({
                      initialValues: { type: 'add' },
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
          <Descriptions.Item label={t('wallet.walletHistory')}>
            <NavLink
              to={{
                pathname: `/merchants/user-wallet-history`,
                search: `?user_id=${record.id}`,
              }}
            >
              <Button>{t('actions.walletHistory')}</Button>
            </NavLink>
          </Descriptions.Item>
        </Descriptions>

        <Divider />
        <Typography.Title level={5}>
          {t('sections.channelInfo')}
        </Typography.Title>
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
        <Typography.Title level={5}>
          {t('sections.withdrawInfo')}
        </Typography.Title>
        <Space direction="vertical" className="w-full" size={'large'}>
          <PaymentForm
            record={record}
            type="withdraw"
            disabled={!canEdit?.can}
          />
          <PaymentForm record={record} type="agency" disabled={!canEdit?.can} />
        </Space>
      </Show>
      <UpdateModal {...modalProps} />
    </>
  );
};

export default MerchantShow;
