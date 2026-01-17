import {
  AntdList,
  Button,
  Card,
  Col,
  ColProps,
  DateField,
  DatePicker,
  Divider,
  Input,
  List,
  ListButton,
  Popover,
  Radio,
  Row,
  ShowButton,
  Space,
  Statistic,
  TableColumnProps,
  TextField,
  Typography,
  Modal as AntdModal,
  Select,
  SelectProps,
  Switch,
  Table,
} from '@pankod/refine-antd';
import useTable from 'hooks/useTable';
import { Meta, TransactionNote, User, Withdraw } from 'interfaces/withdraw';
import { TransactionSubType, TransactionType } from 'interfaces/transaction';
import { FC, useEffect, useState } from 'react';
import { Helmet } from 'react-helmet';
import useMerchant from 'hooks/useMerchant';
import useChannel from 'hooks/useChannel';
import useWithdrawStatus from 'hooks/useWithdrawStatus';
import useTransactionCallbackStatus from 'hooks/useTransactionCallbackStatus';
import dayjs, { Dayjs } from 'dayjs';
import {
  BranchesOutlined,
  CheckOutlined,
  CloseOutlined,
  CopyOutlined,
  DoubleRightOutlined,
  EditOutlined,
  ExportOutlined,
  InfoCircleOutlined,
  LockOutlined,
  RedoOutlined,
  SelectOutlined,
  SettingOutlined,
  StopOutlined,
  SwapRightOutlined,
  UnlockOutlined,
} from '@ant-design/icons';
import useUpdateModal from 'hooks/useUpdateModal';
import {
  CrudFilter,
  useApiUrl,
  useCan,
  useCustomMutation,
  useGetIdentity,
} from '@pankod/refine-core';
import { axiosInstance } from '@pankod/refine-simple-rest';
import CustomDatePicker from 'components/customDatePicker';
import useAutoRefetch from 'hooks/useAutoRefetch';
import HiddenText from 'components/hiddenText';
import Badge from 'components/badge';
import { useNavigate } from '@pankod/refine-react-router-v6';
import numeral from 'numeral';
import { Gray, Red } from 'lib/color';
import queryString from 'query-string';
import { generateFilter } from 'dataProvider';
import { getToken } from 'authProvider';
import useSelector from 'hooks/useSelector';
import { MerchantThirdChannel } from 'interfaces/merchantThirdChannel';
import { ThirdChannel } from 'interfaces/thirdChannel';
import { isEqual, uniqueId } from 'lodash';
import { Provider } from 'interfaces/provider';
import Enviroment from 'lib/env';
import NoticeAudio from 'assets/notice.mp3';
import { useAudioPermission } from 'hooks/useAudioPermission';
import AudioPermissionAlert from 'components/AudioPermissionAlert';
import { useTranslation } from 'react-i18next';

const PayForAnotherList: FC = () => {
  const { t } = useTranslation('transaction');
  const isPaufen = Enviroment.isPaufen;
  const navigate = useNavigate();
  const apiUrl = useApiUrl();
  const { data: profile } = useGetIdentity<Profile>();
  const defaultStartAt = dayjs().startOf('days').format();
  const colProps: ColProps = {
    xs: 24,
    sm: 24,
    md: 6,
  };

  const { data: canEdit } = useCan({
    action: '12',
    resource: 'withdraws',
  });

  const [selectedMerchantId, setSelectMerchantId] = useState(0);
  const { Select: MerchantSelect } = useMerchant({ valueField: 'username' });
  const { Select: ChannelSelect } = useChannel();
  const { Select: ThirdChannelSelect } = useSelector<ThirdChannel>({
    resource: 'thirdchannel',
    labelRender(record) {
      return `${record.thirdChannel}-${record.channel}`;
    },
  });
  const { selectProps: providerSelectProps } = useSelector<Provider>({
    resource: 'users',
    filters: [
      {
        field: 'role',
        value: 2,
        operator: 'eq',
      },
    ],
  });
  const { data: merchantThirdChannel } = useSelector<MerchantThirdChannel>({
    resource: 'merchant-third-channel',
    filters: [
      {
        field: 'status',
        value: 1,
        operator: 'eq',
      },
      {
        field: 'merchant_id',
        value: selectedMerchantId,
        operator: 'eq',
      },
    ],
  });

  let currentMerchantThirdChannelSelect: SelectProps['options'] = [];
  if (merchantThirdChannel?.length) {
    const thirdChannelsList = merchantThirdChannel?.find(
      m => m.id === selectedMerchantId
    )?.thirdChannelsList;
    if (thirdChannelsList?.length) {
      currentMerchantThirdChannelSelect = thirdChannelsList?.map(t => ({
        label: t.thirdChannel,
        value: t.thirdchannel_id,
      }));
    }
    if (thirdChannelsList) {
      currentMerchantThirdChannelSelect = Object.values(thirdChannelsList).map(
        t => ({
          label: t.thirdChannel,
          value: t.thirdchannel_id,
        })
      );
    }
  }

  const {
    Select: WithdrawStatusSelect,
    getStatusText: getWithdrawStatusText,
    Status: WithdrawStatus,
  } = useWithdrawStatus();
  const {
    Select: TranCallbackSelect,
    Status: tranCallbackStatus,
    getStatusText: getTranCallbackStatus,
  } = useTransactionCallbackStatus();

  const {
    Modal,
    show: showUpdateModal,
    modalProps,
  } = useUpdateModal({
    formItems: [
      {
        label: t('transaction:fields.note'),
        name: 'note',
        children: <Input.TextArea />,
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
        name: 'to_thirdchannel_id',
        children: <Select options={currentMerchantThirdChannelSelect} />,
      },
      {
        name: 'withdrawType',
        label: t('transaction:withdraw.type'),
        children: (
          <Select
            options={[
              {
                label: t('transaction:types.manualAgency'),
                value: 4,
              },
              {
                label: t('transaction:types.paufenAgency'),
                value: 2,
              },
            ]}
          />
        ),
      },
      {
        name: 'to_id',
        label: t('transaction:fields.assignProvider'),
        children: (
          <Select
            {...providerSelectProps}
            options={[
              {
                label: t('transaction:placeholders.notAssign'),
                value: null,
              },
              ...(providerSelectProps.options ?? []),
            ]}
          />
        ),
      },
    ],
    transferFormValues(record) {
      if (record.withdrawType) {
        return {
          ...record,
          type: record.withdrawType,
        };
      }
      return record;
    },
  });
  const { freq, enableAuto, AutoRefetch } = useAutoRefetch();

  const {
    tableOutterStyle,
    tableProps,
    Form,
    meta,
    refetch,
    form,
    filters,
    data: withdrawData,
    pagination,
  } = useTable<Withdraw, Meta>({
    formItems: [
      {
        label: t('transaction:fields.startDate'),
        name: 'started_at',
        trigger: 'onSelect',
        children: (
          <CustomDatePicker
            showTime
            className="w-full"
            onFastSelectorChange={(startAt, endAt) =>
              form.setFieldsValue({
                started_at: startAt,
                ended_at: endAt,
              })
            }
          />
        ),
        rules: [
          {
            required: true,
          },
        ],
      },
      {
        label: t('transaction:fields.endDate'),
        name: 'ended_at',
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
      // {
      //     label: "付款帐号",
      //     name: "account",
      //     children: <Input />,
      // },
      {
        label: t('transaction:fields.merchantOrderOrSystemOrder'),
        name: 'order_number_or_system_order_number',
        children: <Input allowClear />,
      },
      {
        label: t('transaction:fields.merchantNameOrAccount'),
        name: 'name_or_username[]',
        children: <MerchantSelect mode="multiple" />,
        collapse: true,
      },
      {
        label: t('transaction:fields.channel'),
        name: 'channel_code[]',
        children: <ChannelSelect mode="multiple" />,
        collapse: true,
      },
      {
        label: t('transaction:fields.orderAmount'),
        name: 'amount',
        children: (
          <Input placeholder={t('transaction:fields.amountRange')} allowClear />
        ),
        collapse: true,
      },
      {
        label: t('transaction:fields.agencyAccount'),
        name: 'account',
        children: <Input />,
        collapse: true,
      },
      {
        label: t('transaction:fields.thirdPartyName'),
        name: 'thirdchannel_id[]',
        children: <ThirdChannelSelect mode="multiple" />,
        collapse: true,
      },
      {
        label: t('transaction:fields.bankCardKeyword'),
        name: 'bank_card_q',
        children: <Input />,
        collapse: true,
      },
      {
        label: t('transaction:fields.orderStatus'),
        name: 'status[]',
        children: <WithdrawStatusSelect mode="multiple" />,
        // collapse: true,
      },
      // {
      //     label: "账号编号",
      //     name: "provider_channel_account_hash_id",
      //     children: <Input allowClear />,
      //     collapse: true,
      // },
      {
        label: t('transaction:fields.callbackStatus'),
        name: 'notify_status[]',
        children: <TranCallbackSelect mode="multiple" />,
        collapse: true,
      },
      {
        label: t('transaction:withdraw.agencyType'),
        name: 'sub_type[]',
        children: (
          <Select
            mode="multiple"
            options={[
              {
                label: t('transaction:types.withdraw'),
                value: TransactionSubType.SUB_TYPE_WITHDRAW,
              },
              {
                label: t('transaction:types.agency'),
                value: TransactionSubType.SUB_TYPE_AGENCY_WITHDRAW,
              },
              {
                label: t('transaction:types.bonusWithdraw'),
                value: TransactionSubType.SUB_TYPE_WITHDRAW_PROFIT,
              },
            ]}
          />
        ),
        collapse: true,
      },
      {
        label: t('transaction:fields.category'),
        name: 'confirmed',
        children: (
          <Radio.Group>
            <Radio value={'created'}>{t('filters.byCreateTime')}</Radio>
            <Radio value={'confirmed'}>{t('filters.bySuccessTime')}</Radio>
          </Radio.Group>
        ),
        collapse: true,
      },
      // {
      //     label: "Ref No.",
      //     name: "_search1",
      //     children: <Input />,
      // },
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
    queryOptions: {
      refetchInterval: enableAuto ? freq * 1000 : undefined,
      refetchIntervalInBackground: true,
    },
  });

  const { mutateAsync } = useCustomMutation();
  const columns: TableColumnProps<Withdraw>[] = [
    {
      title: t('transaction:fields.merchantOrderNumber'),
      dataIndex: 'order_number',
      render(value, record, index) {
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
                            value={t('transaction:childWithdraw.item', {
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
              disabled={!canEdit?.can}
              icon={<EditOutlined />}
              className={record.note_exist ? 'text-[#6eb9ff]' : 'text-gray-300'}
              onClick={async () => {
                const { data } = await axiosInstance.get<
                  IRes<TransactionNote[]>
                >(`${apiUrl}/transactions/${record.id}/transaction-notes`);

                const notes: Partial<TransactionNote>[] = [];
                if (record.note) {
                  notes.push({
                    id: uniqueId() as unknown as number,
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
                    // note: record.note,
                    transaction_id: record.id,
                  },
                  children: (
                    <Space direction="vertical">
                      {notes?.map(note => {
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
                                  : t('info.systemNote', {
                                      time: dayjs(note.created_at).format(
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
            {/* {record.status === WithdrawStatus.成功 ? (
                            <Button
                                icon={<FileSearchOutlined className="text-[#6eb9ff]" />}
                                onClick={() => {
                                    const url =
                                        record.to_channel_account?.channel_code === "MAYA"
                                            ? `/maya/receipt/${record.order_number}`
                                            : `${process.env.REACT_APP_HOST}/v1/gcash/${record.system_order_number}/success-page`;
                                    window.open(url, "_blank");
                                }}
                            />
                        ) : null} */}
          </Space>
        ) : null;
      },
    },
    {
      title: t('transaction:fields.locked'),
      dataIndex: 'locked',
      render(value, record, index) {
        const { separated, locked, locked_by } = record;
        const notLocker =
          locked && profile?.role !== 1 && profile?.name !== locked_by?.name;
        let text = value ? t('status.unlocked') : t('status.locked');
        if (separated) text = t('withdraw.locked');
        const icon = value ? <LockOutlined /> : <UnlockOutlined />;
        const disabled =
          !canEdit?.can ||
          separated ||
          notLocker ||
          record.status === WithdrawStatus.审核中 ||
          record.provider !== null;
        let className = '';
        if (canEdit?.can && !separated) {
          className = `${
            locked
              ? notLocker
                ? `!bg-[#bebebe]`
                : '!bg-black'
              : '!bg-[#ffbe4d]'
          } !text-white border-0`;
        }
        const danger = !value;
        const onClick = () =>
          Modal.confirm({
            title: t('messages.confirmLock', {
              action: text,
            }),
            id: record.id,
            values: {
              locked: !value,
            },
          });
        return (
          <Space>
            <Button
              disabled={disabled}
              danger={danger}
              icon={icon}
              onClick={onClick}
              className={`${disabled ? `!bg-black/4` : className}`}
            >
              {/* {text} */}
            </Button>
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
                <InfoCircleOutlined className="text-[#6eb9ff]" />
              </Popover>
            )}
          </Space>
        );
      },
    },
    {
      title: t('transaction:actions.operation'),
      dataIndex: 'locked',
      render(_, record, index) {
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
                        title: t('transaction:actions.reviewSuccess'),
                        filterFormItems: ['withdrawType'],
                        formValues: {
                          status: 101,
                          to_id: null,
                        },
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
                        title: t('transaction:actions.reviewFail'),
                        filterFormItems: ['note'],
                        formValues: {
                          status: 8,
                        },
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
          canEdit?.can &&
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
                    Modal.confirm({
                      title: t('transaction:messages.confirmModifyStatus'),
                      id: record.id,
                      values: {
                        status: WithdrawStatus.手动成功,
                      },
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
                    Modal.confirm({
                      title: t('transaction:messages.confirmModifyStatus'),
                      id: record.id,
                      values: {
                        status: WithdrawStatus.失败,
                      },
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
                      title: t('transaction:actions.convertToProviderPayout'),
                      filterFormItems: ['to_id'],
                      id: record.id,
                      initialValues: {
                        to_id: null,
                      },
                    })
                  }
                  icon={<DoubleRightOutlined />}
                >
                  {t('withdraw.providerPayout')}
                </Button>
                {/* <Button
                                    icon={<ThunderboltOutlined />}
                                    type="primary"
                                    disabled={
                                        record.status === WithdrawStatus.失败 ||
                                        record.status === WithdrawStatus.成功 ||
                                        record.status === WithdrawStatus.手动成功
                                    }
                                    onClick={() => {
                                        Modal.confirm({
                                            title: t("transaction:messages.confirmAutoPayout"),
                                            id: record.id,
                                            values: {
                                                to_id: null,
                                            },
                                        });
                                    }}
                                >
                                    自动出款
                                </Button> */}
                {/* {notify_url ? (
                                    <Button
                                        disabled={
                                            status === WithdrawStatus.匹配中 || status === WithdrawStatus.等待付款
                                        }
                                        icon={<RedoOutlined />}
                                        onClick={async () => {
                                            await mutateAsync({
                                                url: `${apiUrl}/transactions/${record.id}/renotify`,
                                                method: "post",
                                                values: record,
                                                successNotification: {
                                                    message: "回调成功",
                                                    type: "success",
                                                },
                                            });
                                        }}
                                    >
                                        回调
                                    </Button>
                                ) : null} */}
              </Space>
            }
            trigger={'click'}
          >
            <Button icon={<SettingOutlined />} type="primary">
              {/* 操作 */}
            </Button>
          </Popover>
        ) : (
          <Button disabled icon={<SettingOutlined />}>
            {/* 操作 */}
          </Button>
        );
      },
    },
    {
      title: t('transaction:actions.callback'),
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
          >
            {/* 回调 */}
          </Button>
        ) : null;
      },
    },
    {
      title: t('transaction:actions.thirdPartyPayout'),
      render(value, record, index) {
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
                title: t('transaction:messages.confirmThirdPartyPayout'),
                id: record.id,
                filterFormItems: ['to_thirdchannel_id'],
                customMutateConfig: {
                  url: `${apiUrl}/withdraws/${record.id}`,
                  method: 'put',
                  values: {
                    id: record.id,
                  },
                },
                onSuccess() {
                  setSelectMerchantId(0);
                  refetch();
                },
              });
            }}
          >
            {/* 三方出 */}
          </Button>
        );
      },
    },
    {
      title: t('transaction:fields.paymentType'),
      dataIndex: 'sub_type',
      render(value, record, index) {
        if (value === TransactionSubType.SUB_TYPE_WITHDRAW) return '下发';
        else if (value === TransactionSubType.SUB_TYPE_AGENCY_WITHDRAW)
          return t('types.agency');
        else if (value === TransactionSubType.SUB_TYPE_WITHDRAW_PROFIT)
          return t('types.bonusWithdraw');
        else return '';
      },
    },
    // {
    //     title: t("transaction:actions.callback"),
    //     render(value, record, index) {
    //         const { status, notify_url } = record;
    //         return notify_url ? (
    //             <Button
    //                 disabled={status === WithdrawStatus.匹配中 || status === WithdrawStatus.等待付款}
    //                 icon={<RedoOutlined />}
    //                 onClick={async () => {
    //                     await mutateAsync({
    //                         url: `${apiUrl}/transactions/${record.id}/renotify`,
    //                         method: "post",
    //                         values: record,
    //                         successNotification: {
    //                             message: "回调成功",
    //                             type: "success",
    //                         },
    //                     });
    //                 }}
    //             >
    //                 回调
    //             </Button>
    //         ) : null;
    //     },
    // },
    {
      title: t('transaction:fields.payerInfo'),
      render(_, record, index) {
        let payer = null;
        if (record.to_channel_account) {
          payer = `${record.to_channel_account.channel_code} - ${record.to_channel_account.account}`;
          return (
            <Space>
              <TextField value={payer} />
              <Popover
                trigger={'click'}
                content={
                  <Space direction="vertical">
                    <TextField
                      value={t('info.accountNumber', {
                        number: numeral(record.to_channel_account.id).format(
                          '00000'
                        ),
                      })}
                    />
                    {record.to_channel_account?.note ? (
                      <TextField
                        value={t('info.note', {
                          note: record.to_channel_account?.note,
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
        } else if (record.provider) {
          return `${record.provider.name} (${record.provider.username})`;
        } else if (record.thirdchannel) {
          return `${record.thirdchannel.name}(${record.thirdchannel.merchant_id})`;
        }
        return null;
      },
    },
    {
      title: t('transaction:fields.userName'),
      dataIndex: 'user',
      render(value: User, record, index) {
        const resource = value.role === 3 ? 'merchants' : 'providers';
        return (
          <Space>
            <div className="w-5 h-5 relative">
              <img
                src={
                  value.role !== 3 ? '/provider-icon.png' : '/merchant-icon.png'
                }
                alt=""
                className="object-contain"
                // width={20}
                // height={20}
              />
            </div>
            <ShowButton
              icon={null}
              recordItemId={value?.id}
              resourceNameOrRouteName={resource}
            >
              {value?.name}
            </ShowButton>
          </Space>
        );
      },
    },
    {
      title: t('transaction:fields.orderStatus'),
      dataIndex: 'status',
      render(value, record, index) {
        let color = '';
        if ([WithdrawStatus.成功, WithdrawStatus.手动成功].includes(value)) {
          color = '#16a34a';
        } else if (
          [WithdrawStatus.支付超时, WithdrawStatus.失败].includes(value)
        ) {
          color = '#ff4d4f';
        } else if (
          [
            WithdrawStatus.审核中,
            WithdrawStatus.等待付款,
            WithdrawStatus.三方处理中,
          ].includes(value)
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
      title: t('transaction:fields.bankName'),
      dataIndex: 'bank_name',
    },
    {
      title: t('transaction:fields.province'),
      dataIndex: 'bank_province',
    },
    {
      title: t('transaction:fields.city'),
      dataIndex: 'bank_city',
    },
    {
      title: t('transaction:fields.cardNumber'),
      dataIndex: 'bank_card_number',
      render(value, record, index) {
        let show = false;
        if (
          record.locked &&
          record.locked_by?.id === profile?.id &&
          [WithdrawStatus.审核中, WithdrawStatus.等待付款].includes(
            record.status
          )
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
      title: t('transaction:fields.cardHolder'),
      dataIndex: 'bank_card_holder_name',
      render(value, record, index) {
        const isBanned = meta.banned_realnames.includes(value);
        return value ? (
          <Space>
            <TextField value={value} delete={isBanned} />
            {isBanned ? (
              <RedoOutlined
                style={{ color: canEdit?.can ? Red : Gray }}
                disabled={!canEdit?.can}
                onClick={async () => {
                  await mutateAsync({
                    url: `${apiUrl}/banned/realname/${value}`,
                    method: 'delete',
                    values: {
                      realname: value,
                      type: 2,
                    },
                  });
                  refetch();
                }}
              />
            ) : (
              <Button
                disabled={!canEdit?.can}
                icon={
                  <StopOutlined style={{ color: canEdit?.can ? Red : Gray }} />
                }
                onClick={() => {
                  showUpdateModal({
                    title: t('transaction:actions.blockRealName'),
                    id: 0,
                    filterFormItems: ['note', 'realname', 'type'],
                    initialValues: {
                      realname: value,
                      type: 2,
                    },
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
      title: t('transaction:fields.fee'),
      dataIndex: 'merchant_fees',
      render(value, record, index) {
        return value?.length ? value[value.length - 1].actual_fee : 0;
      },
    },
    {
      title: t('transaction:fields.createdAt'),
      dataIndex: 'created_at',
      render(value, record, index) {
        return <DateField value={value} format="YYYY-MM-DD HH:mm:ss" />;
      },
    },
    {
      title: t('transaction:fields.successTime'),
      dataIndex: 'confirmed_at',
      render(value, record, index) {
        return value ? (
          <DateField value={value} format="YYYY-MM-DD HH:mm:ss" />
        ) : null;
      },
    },
    {
      title: t('fields.callbackStatus'),
      dataIndex: 'notify_status',
      render(value, record, index) {
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
      render(value, record, index) {
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
  ];

  const [previouData, setPrevData] = useState<{
    withdraws?: Withdraw[];
    page?: number;
    filters?: CrudFilter[];
  }>({
    page: 1,
    filters,
  });

  const {
    showPermissionAlert,
    grantPermission,
    dismissPermissionAlert,
    playAudio,
  } = useAudioPermission(NoticeAudio);

  const [enableNotice, setEnableNotice] = useState(true);
  useEffect(() => {
    if (enableNotice) {
      if (
        previouData &&
        previouData.page === pagination.current &&
        withdrawData?.[0]?.id &&
        previouData.withdraws?.[0]?.id !== withdrawData?.[0]?.id &&
        isEqual(previouData.filters, filters)
      ) {
        playAudio();
        setPrevData({
          ...previouData,
          withdraws: withdrawData,
        });
      }
      if (
        !isEqual(previouData.filters, filters) ||
        previouData?.page !== pagination.current
      ) {
        setPrevData({
          withdraws: withdrawData,
          page: pagination.current,
          filters: filters,
        });
      }
    }
  }, [withdrawData, pagination, previouData, enableNotice, filters, playAudio]);

  return (
    <>
      <Helmet>
        <title>{t('types.payment')}</title>
      </Helmet>
      <List
        title={t('types.payment')}
        headerButtons={() => (
          <>
            <ListButton resourceNameOrRouteName="user-bank-cards">
              {isPaufen
                ? t('withdraw.merchantProviderBankList')
                : t('withdraw.merchantBankList')}
            </ListButton>
            <Button
              icon={<ExportOutlined />}
              onClick={async () => {
                const url = `${apiUrl}/withdraw-report?${queryString.stringify(
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
                title={t('statistics.paymentCount')}
                valueStyle={{
                  fontStyle: 'italic',
                  fontWeight: 'bold',
                }}
              />
            </Card>
          </Col>
          <Col {...colProps}>
            <Card className="border-[#3f7cac] border-[2.5px]">
              <Statistic
                value={meta?.total_amount}
                title={t('statistics.paymentAmount')}
                valueStyle={{
                  fontStyle: 'italic',
                  fontWeight: 'bold',
                }}
              />
            </Card>
          </Col>
          <Col {...colProps}>
            <Card className="border-[#f7b801] border-[2.5px]">
              <Statistic
                value={meta?.total_profit}
                title={t('statistics.paymentProfit')}
                valueStyle={{
                  fontStyle: 'italic',
                  fontWeight: 'bold',
                }}
              />
            </Card>
          </Col>
          <Col {...colProps}>
            <Card className="border-[#f7b801] border-[2.5px]">
              <Statistic
                value={meta?.third_channel_fee}
                title={t('statistics.thirdPartyFee')}
                valueStyle={{
                  fontStyle: 'italic',
                  fontWeight: 'bold',
                }}
              />
            </Card>
          </Col>
        </Row>
        <Divider />
        <Space>
          <AutoRefetch />
          <Space className="px-4 mb-4">
            <TextField value={t('switches.soundAlert')} />
            <Switch
              checked={enableNotice}
              onChange={checked => setEnableNotice(checked)}
            />
          </Space>
        </Space>
        <div style={tableOutterStyle}>
          <Table {...tableProps} columns={columns} />
        </div>
      </List>
      <AntdModal {...modalProps} />
      {showPermissionAlert && (
        <AudioPermissionAlert
          onConfirm={grantPermission}
          onDismiss={dismissPermissionAlert}
        />
      )}
    </>
  );
};

export default PayForAnotherList;
