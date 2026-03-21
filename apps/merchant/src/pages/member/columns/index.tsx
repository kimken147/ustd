import { Space } from 'antd';
import { DateField, ShowButton, TextField } from '@refinedev/antd';
import { useTranslate } from '@refinedev/core';
import { Format } from '@morgan-ustd/shared';
import type { MemberColumn } from './types';

export type { ColumnDependencies } from './types';

export function useColumns(): MemberColumn[] {
  const t = useTranslate();
  return [
    {
      title: t('member.fields.merchantName'),
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
      title: t('member.fields.loginAccount'),
      dataIndex: 'username',
    },
    {
      title: t('member.fields.totalBalance'),
      dataIndex: ['wallet', 'balance'],
    },
    {
      title: t('member.fields.frozenBalance'),
      dataIndex: ['wallet', 'frozen_balance'],
    },
    {
      title: t('member.fields.availableBalance'),
      dataIndex: ['wallet', 'available_balance'],
    },
    {
      title: t('member.fields.lastLoginTimeIp'),
      render(_, record) {
        if (!record.last_login_at) return t('member.fields.noRecord');
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
