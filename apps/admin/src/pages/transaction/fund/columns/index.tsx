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
import { DateField, TextField } from '@refinedev/antd';
import { axiosInstance } from '@refinedev/simple-rest';
import { Button, Popover, Space } from 'antd';
import Badge from 'components/badge';
import dayjs from 'dayjs';
import { TransactionNote, Purple, Format } from '@morgan-ustd/shared';
import numeral from 'numeral';
import { getReceiptUrl } from '../../utils';
import type { ColumnDependencies, FundColumn } from './types';

export type { ColumnDependencies } from './types';

export function useColumns(deps: ColumnDependencies): FundColumn[] {
  const {
    t,
    profile,
    WithdrawStatus,
    getWithdrawStatusText,
    showUpdateModal,
    Modal,
    apiUrl,
  } = deps;

  return [
    {
      title: '订单号',
      dataIndex: 'order_number',
      render(value, record) {
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
              className={record.notes?.length ? 'text-[#6eb9ff]' : 'text-gray-300'}
              onClick={async () => {
                const { data: notes } = await axiosInstance.get<IRes<TransactionNote[]>>(
                  `${apiUrl}/transactions/${record.id}/transaction-notes`
                );

                showUpdateModal({
                  id: record.id,
                  filterFormItems: ['note', 'transaction_id'],
                  title: '备注',
                  initialValues: {
                    transaction_id: record.id,
                  },
                  children: (
                    <Space direction="vertical">
                      {notes?.data.map((note, idx) => (
                        <Space direction="vertical" key={idx}>
                          <TextField value={note.note} code className="text-[#1677ff]" />
                          <TextField
                            value={`${note.user ? `${note.user.name}` : '系统'}: ${dayjs(note.created_at).format('YYYY-MM-DD HH:mm:ss')}`}
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
      render(value, record) {
        const text = value ? '解锁' : '锁定';
        const { locked, locked_by } = record;
        const notLocker = locked && profile?.role !== 1 && profile?.name !== locked_by.name;
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
                trigger="click"
                content={
                  <Space direction="vertical">
                    <TextField value={t('info.lockedBy', { name: record.locked_by?.name })} />
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
      title: '操作',
      dataIndex: 'locked',
      render(value, record) {
        const { locked, locked_by } = record;
        const notLocker = locked && profile?.role !== 1 && profile?.name !== locked_by?.name;
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
            trigger="click"
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
      render(_, record) {
        return record.to_channel_account ? (
          <Space>
            <TextField
              value={`${record.to_channel_account.channel_code} - ${record.to_channel_account.account}`}
            />
            <Popover
              trigger="click"
              content={
                <Space direction="vertical">
                  <TextField
                    value={`账号编号: ${numeral(record.to_channel_account.id).format('00000')}`}
                  />
                  {record.to_channel_account?.note ? (
                    <TextField value={`备注：${record.to_channel_account?.note}`} />
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
      render(value, record) {
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
      dataIndex: 'bank_card_number',
    },
    {
      title: '金额',
      dataIndex: 'amount',
    },
    {
      title: '建立时间',
      dataIndex: 'created_at',
      render(value) {
        return value ? <DateField value={value} format={Format} /> : null;
      },
    },
    {
      title: '订单状态',
      dataIndex: 'status',
      render(value) {
        let color = '';
        if ([WithdrawStatus.成功, WithdrawStatus.手动成功].includes(value)) {
          color = '#16a34a';
        } else if ([WithdrawStatus.支付超时, WithdrawStatus.失败].includes(value)) {
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
      render(value) {
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
}
