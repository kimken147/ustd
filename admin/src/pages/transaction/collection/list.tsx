import {
  Button,
  Card,
  Col,
  ColProps,
  DatePicker,
  Divider,
  Input,
  List,
  Popover,
  Radio,
  Row,
  ShowButton,
  Space,
  Statistic,
  TableColumnProps,
  TextField,
  Modal as AntdModal,
  Form as AntdForm,
  useModal,
  useForm,
  Select,
  InputNumber,
  Typography,
  DateField,
  CreateButton,
  SelectProps,
} from '@pankod/refine-antd';
import dayjs, { Dayjs } from 'dayjs';
import useTable from 'hooks/useTable';
import { FC, useEffect } from 'react';
import { Helmet } from 'react-helmet';
import useProvider from 'hooks/useProvider';
import useMerchant from 'hooks/useMerchant';
import useChannel from 'hooks/useChannel';
import useTransactionStatus from 'hooks/useTransactionStatus';
import useTransactionCallbackStatus from 'hooks/useTransactionCallbackStatus';
import { Meta, Stat, Thirdchannel, Transaction } from 'interfaces/transaction';
import {
  CheckOutlined,
  CloseCircleOutlined,
  CloseOutlined,
  CopyOutlined,
  EditOutlined,
  ExportOutlined,
  FileSearchOutlined,
  InfoCircleOutlined,
  LockOutlined,
  PlayCircleOutlined,
  PlusOutlined,
  RedoOutlined,
  SettingOutlined,
  StopOutlined,
  UnlockOutlined,
} from '@ant-design/icons';
import {
  CrudFilter,
  useApiUrl,
  useCan,
  useCustom,
  useCustomMutation,
  useGetIdentity,
  useList,
} from '@pankod/refine-core';
import numeral from 'numeral';
import useUpdateModal from 'hooks/useUpdateModal';
import useChannelGroup from 'hooks/useChannelGroup';
import useUserChannelAccount from 'hooks/useUserChannelAccount';
import { SelectOption } from 'interfaces/antd';
import { axiosInstance } from '@pankod/refine-simple-rest';
import { TransactionNote } from 'interfaces/withdraw';
import CustomDatePicker from 'components/customDatePicker';
import useAutoRefetch from 'hooks/useAutoRefetch';
import Badge from 'components/badge';
import { Format } from 'lib/date';
import queryString from 'query-string';
import { generateFilter } from 'dataProvider';
import { getToken } from 'authProvider';
import useSelector from 'hooks/useSelector';
import { ThirdChannel } from 'interfaces/thirdChannel';
import { SystemSetting } from 'interfaces/systemSetting';
import Env from 'lib/env';
import { useTranslation } from 'react-i18next';

const CollectionList: FC = () => {
  const { t } = useTranslation('transaction');
  const isPaufen = Env.isPaufen;
  const groupLabel = isPaufen ? t('fields.group') : t('fields.groupName'); // "码商" 或 "群组"
  const { data: canEdit } = useCan({
    action: '7',
    resource: 'transactions',
  });
  const { data: canCreate } = useCan({
    action: '32',
    resource: 'transactions',
  });
  const { data: canShowSI } = useCan({
    action: '34',
    resource: 'SI',
  });
  const colProps: ColProps = {
    xs: 24,
    sm: 24,
    md: 12,
    lg: 4,
  };
  const defaultStartAt = dayjs().startOf('days').format();

  const apiUrl = useApiUrl();

  const { Select: MerchantSelect, data: merchants } = useMerchant({
    valueField: 'username',
  });
  const { Select: ProviderSelect, data: providers } = useProvider({
    valueField: 'username',
  });
  const { Select: ChannelSelect, channels } = useChannel();
  const { Select: UserChannelSelect } = useUserChannelAccount();
  const { data: channelGroups } = useChannelGroup();
  const {
    Select: TransStatSelect,
    Status: tranStatus,
    getStatusText: getTranStatusText,
  } = useTransactionStatus();
  const { Select: ThirdChannelSelect, data: thirdChannels } =
    useSelector<ThirdChannel>({
      resource: 'thirdchannel',
      labelRender(record) {
        return `${record.thirdChannel}-${record.channel}`;
      },
    });

  const {
    Select: TranCallbackSelect,
    Status: tranCallbackStatus,
    getStatusText: getTranCallbackStatus,
  } = useTransactionCallbackStatus();

  const { freq, enableAuto, AutoRefetch } = useAutoRefetch();

  const { Form, Table, form, meta, refetch, filters } = useTable<
    Transaction,
    Meta
  >({
    resource: 'transactions',
    formItems: [
      {
        label: t('fields.date'),
        name: 'started_at',
        trigger: 'onSelect',
        children: (
          <CustomDatePicker
            showTime
            className="w-full"
            onFastSelectorChange={(startAt, endAt) =>
              form.setFieldsValue({ started_at: startAt, ended_at: endAt })
            }
          />
        ),
        rules: [{ required: true }],
      },
      {
        label: t('fields.date'),
        name: 'ended_at',
        trigger: 'onSelect',
        children: (
          <DatePicker
            showTime
            className="w-full"
            disabledDate={current => {
              const startAt = form.getFieldValue('started_at') as Dayjs;
              return (
                current &&
                (current > startAt.add(3, 'month') || current < startAt)
              );
            }}
          />
        ),
      },
      {
        label: t('fields.merchantOrderOrSystemOrder'),
        name: 'order_number_or_system_order_number',
        children: <Input allowClear />,
      },
      {
        label: t('fields.orderStatus'),
        name: 'status[]',
        children: <TransStatSelect mode="multiple" />,
      },
      {
        label: t('fields.providerNameTitle', { groupLabel }),
        name: 'provider_name_or_username[]',
        children: <ProviderSelect mode="multiple" />,
        collapse: true,
      },
      {
        label: t('fields.merchantNameOrAccount'),
        name: 'merchant_name_or_username[]',
        children: <MerchantSelect mode="multiple" />,
        collapse: true,
      },
      {
        label: t('fields.channel'),
        name: 'channel_code[]',
        children: <ChannelSelect mode="multiple" />,
        collapse: true,
      },
      {
        label: t('fields.orderAmount'),
        name: 'amount',
        children: <Input placeholder={t('fields.amountRange')} allowClear />,
        collapse: true,
      },
      {
        label: t('fields.collectionAccount'),
        name: 'account',
        children: <Input />,
        collapse: true,
      },
      {
        label: t('fields.thirdPartyName'),
        name: 'thirdchannel_id[]',
        children: <ThirdChannelSelect mode="multiple" />,
        collapse: true,
      },
      {
        label: t('fields.accountNumber'),
        name: 'provider_channel_account_hash_id[]',
        children: <UserChannelSelect mode="multiple" />,
        collapse: true,
      },
      {
        label: t('fields.transferName'),
        name: 'real_name',
        children: <Input />,
        collapse: true,
      },
      {
        label: t('fields.phone'),
        name: 'phone_account',
        children: <Input allowClear />,
        collapse: true,
      },
      {
        label: t('fields.callbackStatus'),
        name: 'notify_status[]',
        children: <TranCallbackSelect mode="multiple" />,
        collapse: true,
      },
      {
        label: t('fields.memberIp'),
        name: 'client_ipv4',
        children: <Input allowClear />,
        collapse: true,
      },
      {
        label: t('filters.classification'),
        name: 'confirmed',
        children: (
          <Radio.Group>
            <Radio value={'created'}>{t('filters.byCreateTime')}</Radio>
            <Radio value={'confirmed'}>{t('filters.bySuccessTime')}</Radio>
          </Radio.Group>
        ),
        collapse: true,
      },
    ],
    filters: [
      {
        field: 'started_at',
        value: defaultStartAt,
        operator: 'eq',
      },
      {
        field: 'confirmed',
        value: 'created',
        operator: 'eq',
      },
    ],
    transferValues(values) {
      // if (!values.ended_at) {
      //     values.ended_at = dayjs(values.started_at).add(1, "weeks").format();
      // }
      if (values['provider_channel_account_hash_id[]']?.length) {
        values['provider_channel_account_hash_id[]'] = (
          values['provider_channel_account_hash_id[]'] as number[]
        ).map(id => numeral(id).format('00000'));
      }
      return values;
    },
    queryOptions: {
      refetchInterval: enableAuto ? freq * 1000 : undefined,
    },
    onSubmit: () => {
      statisticsRefetch();
    },
  });

  const { data, refetch: statisticsRefetch } = useCustom<Stat>({
    url: `${apiUrl}/transactions/statistics`,
    config: {
      filters: [
        ...Object.entries({
          ...form.getFieldsValue(),
          'provider_channel_account_hash_id[]': (
            form.getFieldValue('provider_channel_account_hash_id[]') as number[]
          )?.map(id => numeral(id).format('00000')),
        })
          .filter(([_, value]) => value !== undefined)
          .map<CrudFilter>(([field, value]: [string, any]) => {
            const $value =
              value instanceof dayjs ? (value as Dayjs).format() : value;
            if (Array.isArray(value)) {
              return {
                operator: 'or',
                value: value.map<CrudFilter>((x: any) => ({
                  field,
                  value: x,
                  operator: 'eq',
                })),
              };
            } else
              return {
                field,
                value: $value,
                operator: 'eq',
              };
          }),
      ],
    },
    queryOptions: {
      refetchInterval: enableAuto ? freq * 1000 : undefined,
    },
    method: 'get',
  });
  const { data: profile } = useGetIdentity<Profile>();

  const { Modal, show, modalProps } = useUpdateModal({
    formItems: [
      {
        label: t('fields.amount'),
        name: 'amount',
        children: <InputNumber className="w-full" />,
        rules: [
          {
            required: true,
          },
        ],
      },
      {
        label: t('fields.note'),
        name: 'note',
        children: <Input />,
      },
      {
        name: 'realname',
        hidden: true,
      },
      {
        name: 'type',
        hidden: true,
      },
      {
        name: 'ipv4',
        hidden: true,
      },
      {
        name: 'transaction_id',
        hidden: true,
      },
      {
        name: 'delay_settle_minutes',
        children: (
          <Select
            options={[
              {
                label: '即时',
                value: 0,
              },
              {
                label: '5分钟',
                value: 5,
              },
              {
                label: '10分钟',
                value: 10,
              },
              {
                label: '15分钟',
                value: 15,
              },
            ]}
          />
        ),
      },
    ],
    onSuccess: () => refetch(),
  });
  const {
    modalProps: createTranModalProps,
    show: showCreateTranModal,
    close: closeTranModal,
  } = useModal();
  const { form: createTranForm } = useForm();
  const selectedMerchantName = AntdForm.useWatch('merchant', createTranForm);
  const selectedProviderName = AntdForm.useWatch('provider', createTranForm);
  const selectedChannelGroupId = AntdForm.useWatch(
    'channelGroup',
    createTranForm
  );
  const selectedThirdChannel = AntdForm.useWatch(
    'thirdchannel',
    createTranForm
  );
  const selectedMerchant = merchants?.find(
    merchant => merchant.username === selectedMerchantName
  );
  const selectedProvider = providers?.find(
    provider => provider.username === selectedProviderName
  );

  const { data: userChannelAccounts, refetch: refetchUserChannelAccounts } =
    useUserChannelAccount({
      filters: [
        {
          field: 'provider_id',
          operator: 'eq',
          value: selectedProvider?.id,
        },
        {
          field: 'channel_group',
          operator: 'eq',
          value: selectedChannelGroupId,
        },
      ],
      queryOptions: {
        enabled: false,
      },
    });

  const { data: systemSettings } = useList<SystemSetting>({
    resource: 'feature-toggles',
    config: {
      hasPagination: false,
    },
  });

  const canSeeReward =
    systemSettings?.data.find(item => item.id === 27)?.enabled ?? false;

  const { mutateAsync: customMutate } = useCustomMutation();

  const stat = data?.data;

  const columns: TableColumnProps<Transaction>[] = [
    {
      render(_value, record, _index) {
        const { locked, locked_by } = record;
        const notLocker =
          locked && profile?.role !== 1 && profile?.name !== locked_by?.name;
        let className = '';
        if (canEdit?.can) {
          className = `${
            locked
              ? notLocker
                ? `!bg-[#bebebe]`
                : '!bg-black'
              : '!bg-[#ffbe4d]'
          } !text-white border-0`;
        }
        return (
          <Space>
            <Button
              className={className}
              disabled={!canEdit?.can || notLocker}
              icon={locked ? <LockOutlined /> : <UnlockOutlined />}
              onClick={() =>
                Modal.confirm({
                  title: t('messages.confirmLock', {
                    action: record.locked
                      ? t('messages.unlock')
                      : t('messages.lock'),
                  }),
                  id: record.id,
                  resource: 'transactions',
                  values: {
                    locked: !record.locked,
                  },
                })
              }
            ></Button>
            {locked && (
              <Popover
                trigger={'click'}
                content={
                  <Space direction="vertical">
                    <TextField
                      value={t('info.lockedBy', {
                        name: record.locked_by?.name,
                      })}
                    />
                    <TextField
                      value={t('info.lockedAt', {
                        time: dayjs(record.locked_at).format(
                          'YYYY-MM-DD HH:mm:ss'
                        ),
                      })}
                    />
                  </Space>
                }
              >
                <InfoCircleOutlined className="text-[#1677ff]" />
              </Popover>
            )}
          </Space>
        );
      },
    },
    {
      title: t('actions.operation'),
      dataIndex: 'locked',
      render(value, record, _index) {
        const { status } = record;
        const { locked, locked_by } = record;
        const notLocker =
          locked && profile?.role !== 1 && profile?.name !== locked_by?.name;
        const disabled =
          !locked || [tranStatus.匹配超时].includes(status) || notLocker;
        return !disabled && canEdit?.can ? (
          <Popover
            trigger={'click'}
            content={
              <Space>
                <Button
                  className={
                    status === tranStatus.失败 ||
                    status === tranStatus.成功 ||
                    status === tranStatus.手动成功
                      ? ''
                      : '!bg-[#16a34a] !text-white border-0'
                  }
                  disabled={
                    status === tranStatus.失败 ||
                    status === tranStatus.成功 ||
                    status === tranStatus.手动成功
                  }
                  icon={<CheckOutlined />}
                  onClick={() =>
                    Modal.confirm({
                      title: t('messages.confirmSupplement'),
                      id: record.id,
                      resource: 'transactions',
                      values: {
                        status: tranStatus.手动成功,
                      },
                    })
                  }
                >
                  {t('actions.supplement')}
                </Button>
                <Button
                  className={`${
                    status === tranStatus.付款超时 || status === tranStatus.失败
                      ? ''
                      : '!bg-[#ff4d4f] !text-white border-0'
                  }`}
                  icon={<CloseOutlined />}
                  disabled={
                    status === tranStatus.付款超时 || status === tranStatus.失败
                  }
                  onClick={() =>
                    Modal.confirm({
                      title: t('messages.confirmChangeToFail'),
                      id: record.id,
                      resource: 'transactions',
                      values: {
                        status: tranStatus.失败,
                      },
                    })
                  }
                >
                  {t('actions.changeToFail')}
                </Button>
                <Button
                  disabled={[
                    tranStatus.成功,
                    tranStatus.手动成功,
                    tranStatus.失败,
                  ].includes(status)}
                  icon={<PlusOutlined />}
                  onClick={() =>
                    show({
                      title: t('messages.confirmCreateEmptyOrder'),
                      id: record.id,
                      customMutateConfig: {
                        url: `${apiUrl}/transactions/${record.id}/child-transactions`,
                        method: 'post',
                        values: {
                          id: record.id,
                        },
                      },
                      filterFormItems: ['amount'],
                      successMessage: t('messages.createEmptyOrderSuccess'),
                      onSuccess() {
                        refetch();
                      },
                    })
                  }
                >
                  {t('buttons.addEmptyOrder')}
                </Button>
                <Button
                  icon={<CloseCircleOutlined />}
                  disabled={
                    record.refunded_at || record.status !== tranStatus.付款超时
                  }
                  onClick={() =>
                    show({
                      title: '销单',
                      filterFormItems: ['delay_settle_minutes'],
                      id: record.id,
                      initialValues: {
                        delay_settle_minutes: 0,
                      },
                      formValues: {
                        refund: 1,
                      },
                    })
                  }
                >
                  {t('actions.cancelOrder')}
                </Button>
                <Button
                  style={{
                    color: record.certificate_files?.length
                      ? '#6eb9ff'
                      : '#d1d5db',
                  }}
                  icon={
                    record.certificate_files.length ? (
                      <FileSearchOutlined />
                    ) : null
                  }
                  disabled={!record.certificate_files?.length}
                  onClick={() =>
                    AntdModal.info({
                      title: t('actions.viewTransactionDetails'),
                      content: (
                        <>
                          {record.certificate_files?.map(file => (
                            <Card key={file.id} className="mt-4">
                              <img src={file.url} alt="" />
                            </Card>
                          ))}
                        </>
                      ),
                      maskClosable: true,
                    })
                  }
                >
                  {t('actions.viewTransactionDetails')}
                </Button>
              </Space>
            }
          >
            <Button
              icon={<SettingOutlined />}
              className="border-0"
              type="primary"
            >
              {/* 操作 */}
            </Button>
          </Popover>
        ) : (
          <Button icon={<SettingOutlined />} disabled>
            {/* 操作 */}
          </Button>
        );
      },
    },
    {
      title: t('actions.reCallback'),
      render: (_, record) => {
        const { status, notify_url } = record;
        return notify_url ? (
          <Button
            icon={<RedoOutlined />}
            disabled={
              status === tranStatus.付款超时 ||
              status === tranStatus.等待付款 ||
              status === tranStatus.匹配超时
            }
            onClick={async () => {
              await customMutate({
                url: `${apiUrl}/transactions/${record.id}/renotify`,
                method: 'post',
                values: record,
                successNotification: {
                  message: t('messages.callbackSuccess'),
                  type: 'success',
                },
              });
            }}
          >
            {/* 回调 */}
          </Button>
        ) : null;
      },
    },
    {
      title: t('fields.providerAccountTitle', { groupLabel }),
      dataIndex: ['provider', 'name'],
      render(value, _record, _index) {
        return isPaufen && _record.provider ? (
          <ShowButton
            recordItemId={_record.provider?.id}
            icon={null}
            resourceNameOrRouteName="providers"
          >
            {value}
          </ShowButton>
        ) : (
          (value ?? '-')
        );
      },
    },
    {
      title: t('fields.thirdPartyAccount'),
      dataIndex: 'thirdchannel',
      render(value: Thirdchannel, record, index) {
        return value ? `${value.name}(${value.merchant_id ?? ''})` : '';
      },
    },
    {
      title: t('fields.accountNumber'),
      dataIndex: 'provider_channel_account_hash_id',
      render(value, record, _index) {
        if (!value) return null;
        return (
          <Space>
            <TextField value={value} />
            <Popover
              trigger={'click'}
              content={
                <Space direction="vertical">
                  <TextField
                    value={t('fields.collectionAccountWithValue', {
                      account: record.provider_account,
                    })}
                  />
                  {record.provider_account_note ? (
                    <TextField
                      value={t('info.note', {
                        note: record.provider_account_note,
                      })}
                    />
                  ) : null}
                </Space>
              }
            >
              <InfoCircleOutlined className="text-[#6eb9ff]" />
            </Popover>
          </Space>
        );
      },
    },
    {
      title: t('fields.systemOrderNumber'),
      dataIndex: 'system_order_number',
      render(value, record, index) {
        return (
          <Space>
            <Typography.Paragraph
              copyable={{
                text: value,
                icon: <CopyOutlined className="text-[#6eb9ff]" />,
              }}
              className="!mb-0"
            >
              <ShowButton recordItemId={record.id} icon={false}>
                <TextField
                  value={value}
                  delete={record.child_system_order_number}
                />
              </ShowButton>
            </Typography.Paragraph>
            {record.child_system_order_number ? (
              <Popover
                trigger={'click'}
                content={
                  <Space>
                    <TextField value={`${t('fields.emptyOrderNumber')}: `} />
                    <TextField
                      value={
                        <ShowButton icon={null} recordItemId={record.id}>
                          {record.child_system_order_number}
                        </ShowButton>
                      }
                      copyable={{
                        text: record.child_system_order_number,
                        icon: <CopyOutlined className="text-[#6eb9ff]" />,
                      }}
                    />
                  </Space>
                }
              >
                <InfoCircleOutlined className="text-[#6eb9ff]" />
              </Popover>
            ) : null}
            {record.parent_system_order_number ? (
              <Popover
                trigger={'click'}
                content={
                  <Space>
                    <TextField value={`${t('fields.originalOrderNumber')}: `} />
                    <TextField
                      value={
                        <ShowButton icon={null} recordItemId={record.id}>
                          {record.parent_system_order_number}
                        </ShowButton>
                      }
                      copyable={{
                        text: record.parent_system_order_number,
                        icon: <CopyOutlined className="text-[#6eb9ff]" />,
                      }}
                    />
                  </Space>
                }
              >
                <InfoCircleOutlined className="text-[#6eb9ff]" />
              </Popover>
            ) : null}
            <Button
              disabled={!canEdit?.can}
              icon={<EditOutlined />}
              className={record.note_exist ? 'text-[#6eb9ff]' : 'text-gray-300'}
              onClick={async () => {
                const { data: notes } = await axiosInstance.get<
                  IRes<TransactionNote[]>
                >(`${apiUrl}/transactions/${record.id}/transaction-notes`);
                show({
                  id: record.id,
                  filterFormItems: ['note', 'transaction_id'],
                  title: t('actions.addNote'),
                  initialValues: {
                    transaction_id: record.id,
                  },
                  children: (
                    <Space direction="vertical">
                      {notes?.data.map(note => {
                        return (
                          <Space direction="vertical">
                            <TextField
                              value={note.note}
                              code
                              className="text-[#1677ff]"
                            />
                            <TextField
                              value={`${
                                note.user
                                  ? `${note.user.name}`
                                  : t('info.note', {
                                      note: dayjs(note.created_at).format(
                                        'YYYY-MM-DD HH:mm:ss'
                                      ),
                                    })
                              }`}
                            />
                          </Space>
                        );
                      })}
                    </Space>
                  ),
                  customMutateConfig: {
                    url: `${apiUrl}/transaction-notes`,
                    method: 'post',
                  },
                });
              }}
            />
          </Space>
        );
      },
    },
    {
      title: t('fields.merchantOrderNumber'),
      dataIndex: 'order_number',
      render(value, record, index) {
        return value ? (
          <Typography.Paragraph
            copyable={{
              text: value,
              icon: <CopyOutlined className="text-[#6eb9ff]" />,
            }}
            className="!mb-0"
          >
            {value}
          </Typography.Paragraph>
        ) : null;
      },
    },
    {
      title: t('fields.channel'),
      dataIndex: 'channel_name',
    },
    {
      title: t('fields.orderAmount'),
      render(value, record, index) {
        if (record.amount !== record.floating_amount) {
          return (
            <>
              <span className="line-through">{record.amount}</span>{' '}
              <span>{record.floating_amount}</span>
            </>
          );
        } else return record.amount;
      },
    },
    {
      title: t('fields.transferName'),
      dataIndex: 'real_name',
      render(value, record) {
        let name = '';
        if (value) {
          name = `${value}${record.mobile_number && canShowSI?.can ? `(${record.mobile_number})` : ''}`;
        } else {
          name = `${record.mobile_number && canShowSI?.can ? `(${record.mobile_number})` : ''}`;
        }
        const isBanned = meta.banned_realnames.includes(value);
        return (
          <Space>
            <TextField value={name} delete={isBanned} />
            {isBanned ? (
              <Button
                disabled={!canEdit?.can}
                icon={<RedoOutlined className="!text-[#ff4d4f]" />}
                onClick={async () => {
                  await customMutate({
                    url: `${apiUrl}/banned/realname/${value}`,
                    method: 'delete',
                    values: {
                      realname: value,
                      type: 1,
                    },
                  });
                  refetch();
                }}
              />
            ) : value ? (
              <Button
                disabled={!canEdit?.can}
                icon={<StopOutlined className="!text-[#ff4d4f]" />}
                onClick={() => {
                  show({
                    title: t('actions.block'),
                    id: 0,
                    filterFormItems: ['note', 'realname', 'type'],
                    initialValues: {
                      realname: value,
                      type: 1,
                    },
                    customMutateConfig: {
                      url: `${apiUrl}/banned/realname`,
                      method: 'post',
                    },
                  });
                }}
              />
            ) : null}
          </Space>
        );
      },
    },
    {
      title: t('fields.status'),
      dataIndex: 'status',
      render(value, record): JSX.Element {
        let color = '';
        if ([tranStatus.成功, tranStatus.手动成功].includes(value)) {
          color = '#16a34a';
        } else if ([tranStatus.失败].includes(value)) {
          color = '#ff4d4f';
        } else if (
          [tranStatus.等待付款, tranStatus.三方处理中].includes(value)
        ) {
          color = '#1677ff';
        } else if ([tranStatus.已建立, tranStatus.匹配中].includes(value)) {
          color = '#ffbe4d';
        } else if (value === tranStatus.匹配超时) {
          color = '#bebebe';
        } else if ([tranStatus.付款超时].includes(value)) {
          color = '#ff4d4f';
          return (
            <Badge
              text={`${getTranStatusText(value)}${record.refunded_at ? '(退)' : ''}`}
              color={color}
            />
          );
        }
        return <Badge text={getTranStatusText(value)} color={color} />;
      },
    },
    {
      title: t('fields.fee'),
      dataIndex: 'fee',
    },
    {
      title: t('fields.remark'),
      dataIndex: 'note',
    },
    {
      title: t('fields.callbackStatus'),
      dataIndex: 'notify_status',
      render(value) {
        let color = '';
        if ([tranCallbackStatus.成功].includes(value)) {
          color = '#16a34a';
        } else if (tranCallbackStatus.未通知 === value) {
          color = '#bebebe';
        } else if (tranCallbackStatus.失败 === value) {
          color = '#ff4d4f';
        } else if (
          tranCallbackStatus.已通知 === value ||
          tranCallbackStatus.通知中 === value
        ) {
          color = '#ffbe4d';
        }
        return <Badge text={getTranCallbackStatus(value)} color={color} />;
      },
    },
    {
      title: t('fields.merchantName'),
      render(_value, record) {
        return (
          <ShowButton
            recordItemId={record.merchant.id}
            resourceNameOrRouteName="merchants"
            icon={false}
          >
            {record.merchant.name}
          </ShowButton>
        );
      },
    },
    {
      title: t('fields.memberIp'),
      dataIndex: 'client_ip',
      render(value, _index) {
        if (!value) return '';
        const isBanned = meta.banned_ips.includes(value);
        return (
          <Space>
            <TextField value={value} delete={isBanned} />
            {isBanned ? (
              <Button
                className="!text-[#ff4d4f]"
                disabled={!canEdit?.can}
                icon={<RedoOutlined />}
                onClick={async () => {
                  await customMutate({
                    url: `${apiUrl}/banned/ip/${value}`,
                    method: 'delete',
                    values: {
                      ipv4: value,
                      type: 1,
                    },
                  });
                  refetch();
                }}
              />
            ) : (
              <Button
                className="!text-[#ff4d4f]"
                icon={<StopOutlined />}
                onClick={() =>
                  show({
                    title: t('actions.blockIp'),
                    id: 0,
                    filterFormItems: ['note', 'ipv4', 'type'],
                    initialValues: {
                      ipv4: value,
                      type: 1,
                    },
                    customMutateConfig: {
                      url: `${apiUrl}/banned/ip`,
                      method: 'post',
                    },
                  })
                }
              />
            )}
          </Space>
        );
      },
    },
    {
      title: t('fields.createdAt'),
      dataIndex: 'created_at',
      render(value, record, index) {
        return value ? <DateField value={value} format={Format} /> : null;
      },
    },
    {
      title: t('fields.successTime'),
      dataIndex: 'confirmed_at',
      render(value, record, index) {
        return value ? <DateField value={value} format={Format} /> : null;
      },
    },
    {
      title: t('fields.refundInfo'),
      render(value, record, index) {
        return record.refunded_at !== null ? (
          <Popover
            trigger={'click'}
            content={
              <>
                <p>
                  {t('info.refundedBy', {
                    name: record?.refunded_by?.name ?? t('placeholders.none'),
                  })}
                </p>
                <p>
                  {t('info.refundedAt', {
                    time: record?.refunded_at
                      ? dayjs(record.refunded_at).format(Format)
                      : t('placeholders.none'),
                  })}
                </p>
              </>
            }
          >
            <Button disabled={record.refunded_at === null}>
              {t('actions.view')}
            </Button>
          </Popover>
        ) : (
          <Button disabled={true}>{t('actions.view')}</Button>
        );
      },
    },
  ];

  useEffect(() => {
    if (selectedProvider && selectedChannelGroupId) {
      refetchUserChannelAccounts();
    }
  }, [refetchUserChannelAccounts, selectedProvider, selectedChannelGroupId]);

  return (
    <>
      <Helmet>
        <title>{t('types.collection')}</title>
      </Helmet>
      <List
        title={t('types.collection')}
        headerButtons={() => (
          <>
            <Button
              disabled={!canCreate?.can}
              icon={<PlusOutlined />}
              onClick={() => showCreateTranModal()}
            >
              {t('actions.createEmptyOrder')}
            </Button>
            <CreateButton icon={<PlayCircleOutlined />}>
              {t('buttons.testOrder')}
            </CreateButton>
            {/* {canSeeReward && (
                            <ListButton resourceNameOrRouteName="transaction-rewards">交易奖励</ListButton>
                        )} */}
            <Button
              icon={<ExportOutlined />}
              onClick={async () => {
                const url = `${apiUrl}/transaction-report?${queryString.stringify(
                  generateFilter(filters)
                )}&token=${getToken()}`;
                window.open(url);
              }}
            >
              {t('buttons.export')}
            </Button>
          </>
        )}
      >
        <Form
          initialValues={{
            started_at: dayjs().startOf('days'),
            confirmed: 'created',
          }}
        />
        <Divider />
        <Row gutter={[16, 16]}>
          <Col {...colProps}>
            <Card className="border-[#ff4d4f] border-[2.5px]">
              <Statistic
                value={meta?.total}
                title={t('statistics.transactionCount')}
                valueStyle={{ fontWeight: 'bold', fontStyle: 'italic' }}
              />
            </Card>
          </Col>
          <Col {...colProps}>
            <Card className="border-[#7fd1b9] border-[2.5px]">
              <Statistic
                valueStyle={{ fontWeight: 'bold', fontStyle: 'italic' }}
                value={`${stat?.total_success ?? 0}/${meta?.total || 0}`}
                title={`${t('statistics.successRate')} ${
                  stat
                    ? `${numeral(((stat?.total_success || 0) * 100) / meta?.total).format('0.00')}`
                    : '0'
                }%`}
              />
            </Card>
          </Col>
          <Col {...colProps}>
            <Card className="border-[#3f7cac] border-[2.5px]">
              <Statistic
                value={stat?.total_amount}
                title={t('statistics.transactionAmount')}
                valueStyle={{ fontWeight: 'bold', fontStyle: 'italic' }}
              />
            </Card>
          </Col>
          <Col {...colProps}>
            <Card className="border-[#f7b801] border-[2.5px]">
              <Statistic
                value={stat?.total_profit}
                title={t('statistics.transactionProfit')}
                valueStyle={{ fontWeight: 'bold', fontStyle: 'italic' }}
              />
            </Card>
          </Col>
          <Col {...colProps}>
            <Card className="border-[#f7b801] border-[2.5px]">
              <Statistic
                value={stat?.third_channel_fee}
                title={t('statistics.thirdPartyFee')}
                valueStyle={{ fontWeight: 'bold', fontStyle: 'italic' }}
              />
            </Card>
          </Col>
        </Row>
        <Divider />
        <AutoRefetch />
        <Table columns={columns} />
      </List>
      <AntdModal {...modalProps} />
      <AntdModal
        {...createTranModalProps}
        title={t('actions.createEmptyOrder')}
        onOk={() =>
          AntdModal.confirm({
            title: t('messages.confirmCreateEmptyOrderFinal'),
            onOk: async () => {
              await createTranForm.validateFields();
              await customMutate({
                url: `${apiUrl}/transactions`,
                method: 'post',
                values: {
                  ...createTranForm.getFieldsValue(),
                  merchant: selectedMerchant?.id,
                  provider: selectedProvider?.id,
                },
              });
              createTranForm.resetFields();
              refetch();
              closeTranModal();
            },
          })
        }
      >
        <AntdForm form={createTranForm}>
          <Form.Item
            label={t('placeholders.selectMerchant')}
            name={'merchant'}
            rules={[
              {
                required: true,
              },
            ]}
          >
            <MerchantSelect />
          </Form.Item>

          {(() => {
            const channelGroupOptions: SelectProps['options'] = merchants
              ?.find(m => m.username === selectedMerchantName)
              ?.user_channels.map<SelectOption>(userChannel => ({
                value: userChannel.channel_group_id,
                label: channelGroups?.find(
                  channelGroup =>
                    channelGroup.id === userChannel.channel_group_id
                )?.name,
              }));

            const channel = channels?.find(c =>
              c.channel_groups.find(cg => cg.id === selectedChannelGroupId)
            );
            const thirdChannelOptions: SelectProps['options'] = thirdChannels
              ?.filter(t => t.channel === channel?.name)
              .map(t => ({
                label: `${t.thirdChannel}-${t.channel}`,
                value: t.id,
              }));

            return (
              <>
                <Form.Item
                  label={t('placeholders.selectChannel')}
                  name={'channelGroup'}
                  rules={[
                    {
                      required: true,
                    },
                  ]}
                >
                  <Select
                    showSearch
                    allowClear
                    optionFilterProp="label"
                    options={channelGroupOptions}
                  />
                </Form.Item>
                <Form.Item
                  name={'provider'}
                  label={groupLabel}
                  validateStatus={selectedThirdChannel ? 'success' : undefined}
                  help={selectedThirdChannel ? '' : undefined}
                  rules={[
                    {
                      required: !selectedThirdChannel,
                    },
                  ]}
                >
                  <ProviderSelect
                    disabled={selectedThirdChannel}
                    onChange={value => {
                      if (value) {
                        createTranForm.setFieldValue('thirdchannel', undefined);
                      }
                    }}
                  />
                </Form.Item>
                <Form.Item
                  name="account"
                  label={t('fields.collectionNumber')}
                  validateStatus={selectedThirdChannel ? 'success' : undefined}
                  help={selectedThirdChannel ? '' : undefined}
                  rules={[
                    {
                      required: !selectedThirdChannel,
                    },
                  ]}
                >
                  <Select
                    disabled={selectedThirdChannel}
                    options={
                      selectedProvider && selectedChannelGroupId
                        ? userChannelAccounts?.map<SelectOption>(
                            userChannelAccount => ({
                              label: `${userChannelAccount.account}(${userChannelAccount.name})`,
                              value: userChannelAccount.id,
                            })
                          )
                        : []
                    }
                    allowClear
                  />
                </Form.Item>
                <Form.Item
                  name={'thirdchannel'}
                  label={t('placeholders.selectThirdParty')}
                  rules={[
                    {
                      required: !selectedProviderName,
                    },
                  ]}
                >
                  <Select
                    options={thirdChannelOptions}
                    disabled={selectedProviderName}
                    allowClear
                    onChange={value => {
                      if (value) {
                        createTranForm.setFieldsValue({
                          account: undefined,
                          provider: undefined,
                        });
                      }
                    }}
                  />
                </Form.Item>
              </>
            );
          })()}

          <Form.Item
            label={t('fields.amount')}
            name={'amount'}
            rules={[
              {
                required: true,
              },
            ]}
          >
            <InputNumber className="w-full" />
          </Form.Item>
          <Form.Item
            label={t('fields.note')}
            name={'note'}
            rules={[
              {
                required: true,
              },
            ]}
          >
            <Input />
          </Form.Item>
        </AntdForm>
      </AntdModal>
    </>
  );
};

export default CollectionList;
