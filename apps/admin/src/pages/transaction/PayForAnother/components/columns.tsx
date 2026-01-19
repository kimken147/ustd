import { useMemo } from 'react';
import {
  TableColumnProps,
  Typography,
  Space,
  Button,
  Popover,
  Select,
  SelectProps,
} from 'antd';
import { DateField, ShowButton, TextField } from '@refinedev/antd';
import { List as AntdList } from 'antd';
import {
  BranchesOutlined,
  CheckOutlined,
  CloseOutlined,
  CopyOutlined,
  DoubleRightOutlined,
  EditOutlined,
  InfoCircleOutlined,
  LockOutlined,
  RedoOutlined,
  SelectOutlined,
  SettingOutlined,
  StopOutlined,
  SwapRightOutlined,
  UnlockOutlined,
} from '@ant-design/icons';
import { useTranslation } from 'react-i18next';
import {
  Withdraw,
  WithdrawUser as User,
  TransactionSubType,
  TransactionType,
  TransactionNote,
  Gray,
  Red,
  WithdrawStatusValue,
} from '@morgan-ustd/shared';
import Badge from 'components/badge';
import HiddenText from 'components/hiddenText';
import numeral from 'numeral';
import dayjs from 'dayjs';

export interface UseColumnsProps {
  canEdit: boolean;
  profile: Profile | undefined;
  apiUrl: string;
  navigate: (path: string) => void;
  showUpdateModal: (config: any) => void;
  modalConfirm: (config: any) => void;
  mutateAsync: (config: any) => Promise<any>;
  refetch: () => void;
  getWithdrawStatusText: (status: number) => string;
  getTranCallbackStatus: (status: number) => string;
  WithdrawStatus: Record<string, number>;
  tranCallbackStatus: Record<string, number>;
  meta: { banned_realnames: string[] };
  providerSelectProps: SelectProps;
  currentMerchantThirdChannelSelect: SelectProps['options'];
  setSelectMerchantId: (id: number) => void;
  axiosInstance: any;
}

export function useColumns(props: UseColumnsProps): TableColumnProps<Withdraw>[] {
  const { t } = useTranslation('transaction');
  const {
    canEdit,
    profile,
    apiUrl,
    navigate,
    showUpdateModal,
    modalConfirm,
    mutateAsync,
    refetch,
    getWithdrawStatusText,
    getTranCallbackStatus,
    WithdrawStatus,
    tranCallbackStatus,
    meta,
    providerSelectProps,
    currentMerchantThirdChannelSelect,
    setSelectMerchantId,
    axiosInstance,
  } = props;

  return useMemo(
    () => [
      {
        title: t('fields.merchantOrderNumber'),
        dataIndex: 'order_number',
        render(value, record) {
          return value ? (
            <Space>
              <Typography.Paragraph className="!mb-0">
                <ShowButton recordItemId={record.id} icon={null}>
                  <TextField value={value} delete={record.separated} />
                </ShowButton>
                <TextField
                  value={' '}
                  copyable={{
                    text: value,
                    icon: <CopyOutlined className="text-[#6eb9ff]" />,
                  }}
                />
              </Typography.Paragraph>
              {record?.children?.length ? (
                <Popover
                  trigger={'click'}
                  content={
                    <AntdList<Withdraw>
                      bordered
                      dataSource={record.children}
                      renderItem={(item, index) => (
                        <AntdList.Item key={item.id}>
                          <Space>
                            <TextField
                              value={t('childWithdraw.item', {
                                number: index + 1,
                              })}
                            />
                            <TextField
                              value={
                                <ShowButton icon={null} recordItemId={item.id}>
                                  {item.order_number}
                                </ShowButton>
                              }
                            />
                            <TextField
                              value={''}
                              copyable={{
                                text: item.order_number,
                              }}
                            />
                          </Space>
                        </AntdList.Item>
                      )}
                    />
                  }
                >
                  <InfoCircleOutlined className="text-[#6eb9ff]" />
                </Popover>
              ) : null}
              <Button
                disabled={!canEdit}
                icon={<EditOutlined />}
                className={record.note_exist ? 'text-[#6eb9ff]' : 'text-gray-300'}
                onClick={async () => {
                  const { data } = await axiosInstance.get<
                    IRes<TransactionNote[]>
                  >(`${apiUrl}/transactions/${record.id}/transaction-notes`);

                  const notes: Partial<TransactionNote>[] = [];
                  if (record.note) {
                    notes.push({
                      id: Date.now() as unknown as number,
                      note: record.note,
                      created_at: record.created_at,
                    });
                  }
                  notes.push(...data.data);

                  showUpdateModal({
                    id: record.id,
                    filterFormItems: ['note', 'transaction_id'],
                    title: t('fields.note'),
                    initialValues: {
                      transaction_id: record.id,
                    },
                    children: (
                      <Space direction="vertical">
                        {notes?.map((note, idx) => (
                          <Space direction="vertical" key={idx}>
                            <TextField
                              value={note.note}
                              code
                              className="text-[#1677ff]"
                            />
                            <TextField
                              value={`${
                                note.user
                                  ? `${note.user.name}`
                                  : t('info.systemNote', {
                                      time: dayjs(note.created_at).format(
                                        'YYYY-MM-DD HH:mm:ss'
                                      ),
                                    })
                              }`}
                            />
                          </Space>
                        ))}
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
          ) : null;
        },
      },
      {
        title: t('fields.locked'),
        dataIndex: 'locked',
        render(value, record) {
          const { separated, locked, locked_by } = record;
          const notLocker =
            locked && profile?.role !== 1 && profile?.name !== locked_by?.name;
          let text = value ? t('status.unlocked') : t('status.locked');
          if (separated) text = t('withdraw.locked');
          const icon = value ? <LockOutlined /> : <UnlockOutlined />;
          const disabled =
            !canEdit ||
            separated ||
            notLocker ||
            record.status === WithdrawStatus.审核中 ||
            record.provider !== null;
          let className = '';
          if (canEdit && !separated) {
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
                disabled={disabled}
                danger={!value}
                icon={icon}
                onClick={() =>
                  modalConfirm({
                    title: t('messages.confirmLock', { action: text }),
                    id: record.id,
                    values: { locked: !value },
                  })
                }
                className={`${disabled ? `!bg-black/4` : className}`}
              />
              {locked && (
                <Popover
                  trigger={'click'}
                  content={
                    <Space direction="vertical">
                      <TextField
                        value={t('info.lockedBy', { name: record.locked_by?.name })}
                      />
                      <TextField
                        value={t('info.lockedAt', {
                          time: dayjs(record.locked_at).format('YYYY-MM-DD HH:mm:ss'),
                        })}
                      />
                    </Space>
                  }
                >
                  <InfoCircleOutlined className="text-[#6eb9ff]" />
                </Popover>
              )}
            </Space>
          );
        },
      },
      {
        title: t('actions.operation'),
        dataIndex: 'locked',
        render(_, record) {
          const { locked, locked_by } = record;
          const notLocker =
            locked && profile?.role !== 1 && profile?.name !== locked_by?.name;
          if (record.status === WithdrawStatus.审核中) {
            return (
              <Popover
                trigger={'click'}
                content={
                  <Space>
                    <Button
                      icon={<CheckOutlined />}
                      className="!bg-[#16a34a] !text-slate-50 border-0"
                      onClick={() => {
                        showUpdateModal({
                          title: t('actions.reviewSuccess'),
                          filterFormItems: ['withdrawType'],
                          formValues: { status: 101, to_id: null },
                          id: record.id,
                        });
                      }}
                    >
                      {t('actions.reviewSuccess')}
                    </Button>
                    <Button
                      icon={<CloseOutlined />}
                      className="!bg-[#ff4d4f] !text-white border-0"
                      onClick={() => {
                        showUpdateModal({
                          title: t('actions.reviewFail'),
                          filterFormItems: ['note'],
                          formValues: { status: 8 },
                          id: record.id,
                        });
                      }}
                    >
                      {t('actions.reviewFail')}
                    </Button>
                  </Space>
                }
              >
                <Button icon={<SelectOutlined />} type="primary">
                  {t('actions.review')}
                </Button>
              </Popover>
            );
          }
          return locked &&
            canEdit &&
            !record.separated &&
            !notLocker &&
            record.provider === null &&
            ![WithdrawStatus.失败].includes(record.status) ? (
            <Popover
              content={
                <Space>
                  <Button
                    icon={<CheckOutlined />}
                    disabled={
                      record.status === WithdrawStatus.失败 ||
                      record.status === WithdrawStatus.成功 ||
                      record.status === WithdrawStatus.手动成功
                    }
                    className={
                      record.status === WithdrawStatus.失败 ||
                      record.status === WithdrawStatus.成功 ||
                      record.status === WithdrawStatus.手动成功
                        ? ''
                        : '!bg-[#16a34a] !text-slate-50 border-0'
                    }
                    onClick={() =>
                      modalConfirm({
                        title: t('messages.confirmModifyStatus'),
                        id: record.id,
                        values: { status: WithdrawStatus.手动成功 },
                      })
                    }
                  >
                    {t('actions.changeToSuccess')}
                  </Button>
                  <Button
                    icon={<CloseOutlined />}
                    disabled={record.status === WithdrawStatus.失败}
                    className={
                      record.status === WithdrawStatus.失败
                        ? ''
                        : '!bg-[#ff4d4f] !text-white border-0'
                    }
                    onClick={() =>
                      modalConfirm({
                        title: t('messages.confirmModifyStatus'),
                        id: record.id,
                        values: { status: WithdrawStatus.失败 },
                      })
                    }
                  >
                    {t('actions.changeToFail')}
                  </Button>
                  <Button
                    onClick={() => navigate(`/child-withdraws/show/${record.id}`)}
                    disabled={!record.separatable}
                    icon={<BranchesOutlined className="rotate-180" />}
                  >
                    {t('actions.splitOrder')}
                  </Button>
                  <Button
                    type="primary"
                    disabled={
                      record.status === WithdrawStatus.失败 ||
                      record.status === WithdrawStatus.成功 ||
                      record.status === WithdrawStatus.手动成功
                    }
                    onClick={() =>
                      showUpdateModal({
                        title: t('actions.convertToProviderPayout'),
                        filterFormItems: ['to_id'],
                        id: record.id,
                        initialValues: { to_id: null },
                      })
                    }
                    icon={<DoubleRightOutlined />}
                  >
                    {t('withdraw.providerPayout')}
                  </Button>
                </Space>
              }
              trigger={'click'}
            >
              <Button icon={<SettingOutlined />} type="primary" />
            </Popover>
          ) : (
            <Button disabled icon={<SettingOutlined />} />
          );
        },
      },
      {
        title: t('actions.callback'),
        render: (_, record) => {
          const { status, notify_url } = record;
          return notify_url ? (
            <Button
              icon={<RedoOutlined />}
              disabled={
                status === WithdrawStatus.匹配中 ||
                status === WithdrawStatus.等待付款
              }
              onClick={async () => {
                await mutateAsync({
                  url: `${apiUrl}/transactions/${record.id}/renotify`,
                  method: 'post',
                  values: record,
                  successNotification: {
                    message: t('messages.callbackSuccess'),
                    type: 'success',
                  },
                });
              }}
            />
          ) : null;
        },
      },
      {
        title: t('actions.thirdPartyPayout'),
        render(_, record) {
          return (
            <Button
              icon={<SwapRightOutlined />}
              disabled={
                record.status === WithdrawStatus.失败 ||
                record.status === WithdrawStatus.成功 ||
                record.status === WithdrawStatus.手动成功 ||
                record.type === TransactionType.TYPE_PAUFEN_WITHDRAW ||
                !record.locked ||
                (record.status !== WithdrawStatus.等待付款 &&
                  record.type !== TransactionType.TYPE_NORMAL_WITHDRAW)
              }
              onClick={() => {
                setSelectMerchantId(record.user.id);
                showUpdateModal({
                  title: t('messages.confirmThirdPartyPayout'),
                  id: record.id,
                  filterFormItems: ['to_thirdchannel_id'],
                  customMutateConfig: {
                    url: `${apiUrl}/withdraws/${record.id}`,
                    method: 'put',
                    values: { id: record.id },
                  },
                  onSuccess() {
                    setSelectMerchantId(0);
                    refetch();
                  },
                });
              }}
            />
          );
        },
      },
      {
        title: t('fields.paymentType'),
        dataIndex: 'sub_type',
        render(value) {
          if (value === TransactionSubType.SUB_TYPE_WITHDRAW) return '下发';
          else if (value === TransactionSubType.SUB_TYPE_AGENCY_WITHDRAW)
            return t('types.agency');
          else if (value === TransactionSubType.SUB_TYPE_WITHDRAW_PROFIT)
            return t('types.bonusWithdraw');
          else return '';
        },
      },
      {
        title: t('fields.payerInfo'),
        render(_, record) {
          if (record.to_channel_account) {
            const payer = `${record.to_channel_account.channel_code} - ${record.to_channel_account.account}`;
            return (
              <Space>
                <TextField value={payer} />
                <Popover
                  trigger={'click'}
                  content={
                    <Space direction="vertical">
                      <TextField
                        value={t('info.accountNumber', {
                          number: numeral(record.to_channel_account.id).format('00000'),
                        })}
                      />
                      {record.to_channel_account?.note && (
                        <TextField
                          value={t('info.note', { note: record.to_channel_account?.note })}
                        />
                      )}
                    </Space>
                  }
                >
                  <InfoCircleOutlined className="text-[#6eb9ff]" />
                </Popover>
              </Space>
            );
          } else if (record.provider) {
            return `${record.provider.name} (${record.provider.username})`;
          } else if (record.thirdchannel) {
            return `${record.thirdchannel.name}(${record.thirdchannel.merchant_id})`;
          }
          return null;
        },
      },
      {
        title: t('fields.userName'),
        dataIndex: 'user',
        render(value: User) {
          const resource = value.role === 3 ? 'merchants' : 'providers';
          return (
            <Space>
              <div className="w-5 h-5 relative">
                <img
                  src={value.role !== 3 ? '/provider-icon.png' : '/merchant-icon.png'}
                  alt=""
                  className="object-contain"
                />
              </div>
              <ShowButton icon={null} recordItemId={value?.id} resource={resource}>
                {value?.name}
              </ShowButton>
            </Space>
          );
        },
      },
      {
        title: t('fields.orderStatus'),
        dataIndex: 'status',
        render(value) {
          let color = '';
          if ([WithdrawStatus.成功, WithdrawStatus.手动成功].includes(value)) {
            color = '#16a34a';
          } else if ([WithdrawStatus.支付超时, WithdrawStatus.失败].includes(value)) {
            color = '#ff4d4f';
          } else if (
            [WithdrawStatus.审核中, WithdrawStatus.等待付款, WithdrawStatus.三方处理中].includes(value)
          ) {
            color = '#1677ff';
          } else if (value === WithdrawStatus.匹配中) {
            color = '#ffbe4d';
          } else if (value === WithdrawStatus.匹配超时) {
            color = '#bebebe';
          }
          return <Badge text={getWithdrawStatusText(value)} color={color} />;
        },
      },
      {
        title: t('fields.bankName'),
        dataIndex: 'bank_name',
      },
      {
        title: t('fields.province'),
        dataIndex: 'bank_province',
      },
      {
        title: t('fields.city'),
        dataIndex: 'bank_city',
      },
      {
        title: t('fields.cardNumber'),
        dataIndex: 'bank_card_number',
        render(value, record) {
          let show = false;
          if (
            record.locked &&
            record.locked_by?.id === profile?.id &&
            [WithdrawStatus.审核中, WithdrawStatus.等待付款].includes(record.status)
          ) {
            show = true;
          }
          return show ? (
            value
          ) : (
            <HiddenText key={record.id} text={value} status={record.status} />
          );
        },
      },
      {
        title: t('fields.cardHolder'),
        dataIndex: 'bank_card_holder_name',
        render(value, record) {
          const isBanned = meta?.banned_realnames?.includes(value);
          return value ? (
            <Space>
              <TextField value={value} delete={isBanned} />
              {isBanned ? (
                <RedoOutlined
                  style={{ color: canEdit ? Red : Gray }}
                  onClick={async () => {
                    if (!canEdit) return;
                    await mutateAsync({
                      url: `${apiUrl}/banned/realname/${value}`,
                      method: 'delete',
                      values: { realname: value, type: 2 },
                    });
                    refetch();
                  }}
                />
              ) : (
                <Button
                  disabled={!canEdit}
                  icon={<StopOutlined style={{ color: canEdit ? Red : Gray }} />}
                  onClick={() => {
                    showUpdateModal({
                      title: t('actions.blockRealName'),
                      id: 0,
                      filterFormItems: ['note', 'realname', 'type'],
                      initialValues: { realname: value, type: 2 },
                      customMutateConfig: {
                        url: `${apiUrl}/banned/realname`,
                        method: 'post',
                      },
                      onSuccess() {
                        refetch();
                      },
                    });
                  }}
                />
              )}
            </Space>
          ) : null;
        },
      },
      {
        title: t('fields.orderAmount'),
        dataIndex: 'amount',
      },
      {
        title: t('fields.fee'),
        dataIndex: 'merchant_fees',
        render(value) {
          return value?.length ? value[value.length - 1].actual_fee : 0;
        },
      },
      {
        title: t('fields.createdAt'),
        dataIndex: 'created_at',
        render(value) {
          return <DateField value={value} format="YYYY-MM-DD HH:mm:ss" />;
        },
      },
      {
        title: t('fields.successTime'),
        dataIndex: 'confirmed_at',
        render(value) {
          return value ? <DateField value={value} format="YYYY-MM-DD HH:mm:ss" /> : null;
        },
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
        title: t('fields.systemOrderNumber'),
        dataIndex: 'system_order_number',
        render(value) {
          return (
            <Typography.Paragraph
              copyable={{
                text: value,
                icon: <CopyOutlined className="text-[#6eb9ff]" />,
              }}
              className="!mb-0"
            >
              {value}
            </Typography.Paragraph>
          );
        },
      },
    ],
    [
      t,
      canEdit,
      profile,
      apiUrl,
      navigate,
      showUpdateModal,
      modalConfirm,
      mutateAsync,
      refetch,
      getWithdrawStatusText,
      getTranCallbackStatus,
      WithdrawStatus,
      tranCallbackStatus,
      meta,
      setSelectMerchantId,
      axiosInstance,
    ]
  );
}

export default useColumns;
