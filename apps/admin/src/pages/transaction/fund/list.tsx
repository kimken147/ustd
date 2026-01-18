import {
  CheckOutlined,
  CloseOutlined,
  CopyOutlined,
  EditOutlined,
  FileSearchOutlined,
  InfoCircleOutlined,
  LockOutlined,
  SettingOutlined,
  UnlockOutlined,
} from '@ant-design/icons';
import {
  CreateButton,
  DateField,
  List,
  TextField,
} from '@refinedev/antd';
import {
  Button,
  DatePicker,
  Divider,
  Input,
  Modal as AntdModal,
  Popover,
  Space,
  TableColumnProps,
} from 'antd';
import { useGetIdentity } from '@refinedev/core';
import { axiosInstance } from '@refinedev/simple-rest';
import Badge from 'components/badge';
import CustomDatePicker from 'components/customDatePicker';
import dayjs, { Dayjs } from 'dayjs';
import useAutoRefetch from 'hooks/useAutoRefetch';
import useTable from 'hooks/useTable';
import useTransactionStatus from 'hooks/useTransactionStatus';
import useUpdateModal from 'hooks/useUpdateModal';
import useWithdrawStatus from 'hooks/useWithdrawStatus';
import { apiUrl } from 'index';
import { InternalTransfer } from 'interfaces/internalTransfer';
import { TransactionNote, Purple, Format } from '@morgan-ustd/shared';
import numeral from 'numeral';
import { FC } from 'react';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import { getReceiptUrl } from '../utils';

const FundList: FC = () => {
  const { t } = useTranslation('transaction');
  const defaultStartAt = dayjs().startOf('days').format();
  const { data: profile } = useGetIdentity<Profile>();
  const { Select: TranStatusSelect } = useTransactionStatus();
  const { Status: WithdrawStatus, getStatusText: getWithdrawStatusText } =
    useWithdrawStatus();
  const { freq, enableAuto, AutoRefetch } = useAutoRefetch();
  const {
    modalProps,
    show: showUpdateModal,
    Modal,
  } = useUpdateModal({
    formItems: [
      {
        label: '备注',
        name: 'note',
        children: <Input.TextArea />,
      },
      {
        name: 'transaction_id',
        hidden: true,
      },
    ],
  });
  const { Form, Table, form } = useTable({
    formItems: [
      {
        label: '开始日期',
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
        label: '结束日期',
        name: 'ended_at',
        children: (
          <DatePicker
            showTime
            className="w-full"
            disabledDate={current => {
              const startAt = form.getFieldValue('started_at') as Dayjs;
              return (
                current &&
                (current > startAt.add(1, 'month') || current < startAt)
              );
            }}
          />
        ),
      },
      {
        label: '状态',
        name: 'status[]',
        children: <TranStatusSelect />,
      },
      {
        label: '收款账号',
        name: 'bank_card_number',
        children: <Input />,
      },
      {
        label: '付款账号',
        name: 'account',
        children: <Input />,
      },
    ],
    filters: [
      {
        field: 'started_at',
        value: defaultStartAt,
        operator: 'eq',
      },
    ],
    queryOptions: {
      refetchInterval: enableAuto ? freq * 1000 : undefined,
    },
  });

  const columns: TableColumnProps<InternalTransfer>[] = [
    {
      title: '订单号',
      dataIndex: 'order_number',
      render(value, record, index) {
        return value ? (
          <Space>
            <TextField
              value={value}
              copyable={{
                icon: <CopyOutlined style={{ color: Purple }} />,
              }}
            />
            <Button
              icon={<EditOutlined />}
              className={
                record.notes?.length ? 'text-[#6eb9ff]' : 'text-gray-300'
              }
              onClick={async () => {
                const { data: notes } = await axiosInstance.get<
                  IRes<TransactionNote[]>
                >(`${apiUrl}/transactions/${record.id}/transaction-notes`);

                showUpdateModal({
                  id: record.id,
                  filterFormItems: ['note', 'transaction_id'],
                  title: '备注',
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
                                note.user ? `${note.user.name}` : '系统'
                              }: ${dayjs(note.created_at).format('YYYY-MM-DD HH:mm:ss')}`}
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
            {record.status === WithdrawStatus.成功 ? (
              <Button
                icon={<FileSearchOutlined className="text-[#6eb9ff]" />}
                onClick={() => {
                  const url = getReceiptUrl(record);
                  window.open(url, '_blank');
                }}
              />
            ) : null}
          </Space>
        ) : null;
      },
    },
    {
      title: '锁定',
      dataIndex: 'locked',
      render(value, record, index) {
        let text = value ? '解锁' : '锁定';
        const { locked, locked_by } = record;
        const notLocker =
          locked && profile?.role !== 1 && profile?.name !== locked_by.name;
        const icon = value ? <LockOutlined /> : <UnlockOutlined />;
        const className = `${
          locked ? (notLocker ? `!bg-[#bebebe]` : '!bg-black') : '!bg-[#ffbe4d]'
        } !text-white border-0`;
        const danger = !value;
        const onClick = () =>
          Modal.confirm({
            title: `是否确定${text}提现订单？`,
            id: record.id,
            values: {
              locked: !value,
            },
          });
        return (
          <Space>
            <Button
              danger={danger}
              icon={icon}
              onClick={onClick}
              disabled={notLocker}
              className={className}
            >
              {text}
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
      title: '操作',
      dataIndex: 'locked',
      render(value, record, index) {
        const { locked, locked_by } = record;
        const notLocker =
          locked && profile?.role !== 1 && profile?.name !== locked_by?.name;
        return locked && !notLocker ? (
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
                      title: '是否确定修改状态？',
                      id: record.id,
                      values: {
                        status: WithdrawStatus.手动成功,
                      },
                    })
                  }
                >
                  成功
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
                      title: '是否确定修改状态？',
                      id: record.id,
                      values: {
                        status: WithdrawStatus.失败,
                      },
                    })
                  }
                >
                  失败
                </Button>
              </Space>
            }
            trigger={'click'}
          >
            <Button icon={<SettingOutlined />} type="primary">
              操作
            </Button>
          </Popover>
        ) : (
          <Button disabled icon={<SettingOutlined />}>
            操作
          </Button>
        );
      },
    },
    {
      title: '付款账号',
      dataIndex: ['to_channel_account', 'name'],
      render(value, record, index) {
        return record.to_channel_account ? (
          <Space>
            <TextField
              value={`${record.to_channel_account.channel_code} - ${record.to_channel_account.account}`}
            />
            <Popover
              trigger={'click'}
              content={
                <Space direction="vertical">
                  <TextField
                    value={`账号编号: ${numeral(record.to_channel_account.id).format('00000')}`}
                  />
                  {record.to_channel_account?.note ? (
                    <TextField
                      value={`备注：${record.to_channel_account?.note}`}
                    />
                  ) : null}
                </Space>
              }
            >
              <InfoCircleOutlined className="text-[#6eb9ff]" />
            </Popover>
          </Space>
        ) : null;
      },
    },
    {
      title: '备注',
      dataIndex: 'note',
      render(value, record, index) {
        return (
          <Space>
            <TextField value={value} />
            <EditOutlined
              style={{ color: Purple }}
              onClick={() => {
                showUpdateModal({
                  title: '修改备注',
                  filterFormItems: ['note'],
                  id: record.id,
                  initialValues: {
                    note: value,
                  },
                });
              }}
            />
          </Space>
        );
      },
    },
    {
      title: '收款账号',
      dataIndex: ['bank_card_number'],
    },
    {
      title: '金额',
      dataIndex: 'amount',
    },
    {
      title: '建立时间',
      dataIndex: 'created_at',
      render(value, record, index) {
        return value ? <DateField value={value} format={Format} /> : null;
      },
    },
    {
      title: '订单状态',
      dataIndex: 'status',
      render(value, record, index) {
        let color = '';
        if ([WithdrawStatus.成功, WithdrawStatus.手动成功].includes(value)) {
          color = '#16a34a';
        } else if (
          [WithdrawStatus.支付超时, WithdrawStatus.失败].includes(value)
        ) {
          color = '#ff4d4f';
        } else if ([WithdrawStatus.等待付款].includes(value)) {
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
      title: '成功时间',
      dataIndex: 'confirmed_at',
      render(value, record, index) {
        return value ? <DateField value={value} format={Format} /> : null;
      },
    },
    {
      title: '银行名称',
      dataIndex: 'bank_name',
    },
    {
      title: '持卡人名称',
      dataIndex: 'bank_card_holder_name',
    },
  ];

  return (
    <List
      headerButtons={() => (
        <>
          <CreateButton>建立转账</CreateButton>
        </>
      )}
    >
      <Helmet>
        <title>资金管理</title>
      </Helmet>
      <Form
        initialValues={{
          started_at: dayjs().startOf('days'),
        }}
      />
      <Divider />
      <AutoRefetch />
      <Table columns={columns} />
      <AntdModal {...modalProps} />
    </List>
  );
};

export default FundList;
