import { Space } from 'antd';
import { DateField, ShowButton, TextField } from '@refinedev/antd';
import { Format } from '@morgan-ustd/shared';
import type { MemberColumn } from './types';

export type { ColumnDependencies } from './types';

export function useColumns(): MemberColumn[] {
  return [
    {
      title: '商户名称',
      dataIndex: 'name',
      render(value, record) {
        return (
          <ShowButton icon={null} recordItemId={record.id}>
            {value}
          </ShowButton>
        );
      },
    },
    {
      title: '登录帐号',
      dataIndex: 'username',
    },
    {
      title: '总余额',
      dataIndex: ['wallet', 'balance'],
    },
    {
      title: '冻结余额',
      dataIndex: ['wallet', 'frozen_balance'],
    },
    {
      title: '可用余额',
      dataIndex: ['wallet', 'available_balance'],
    },
    {
      title: '最后登录时间 / IP',
      render(_, record) {
        if (!record.last_login_at) return '尚无纪录';
        return (
          <Space>
            <DateField value={record.last_login_at} format={Format} />
            <TextField value={record.last_login_ipv4} />
          </Space>
        );
      },
    },
  ];
}
