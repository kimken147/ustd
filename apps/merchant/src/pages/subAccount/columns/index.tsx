import { Badge, Space } from 'antd';
import { DateField, ShowButton, TextField } from '@refinedev/antd';
import { Format } from '@morgan-ustd/shared';
import type { ColumnDependencies, SubAccountColumn } from './types';

export type { ColumnDependencies } from './types';

export function useColumns(deps: ColumnDependencies): SubAccountColumn[] {
  const { t } = deps;

  return [
    {
      title: t('subAccount.fields.name'),
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
      title: t('subAccount.fields.status'),
      dataIndex: 'status',
      render(value) {
        const color = value === 1 ? '#16a34a' : '#ff4d4f';
        const text = value === 1 ? t('enable') : t('disable');
        return <Badge color={color} text={text} />;
      },
    },
    {
      title: t('subAccount.fields.theLastLoginTimeOrIp'),
      render(_, record) {
        return (
          <Space>
            {record.last_login_at ? <DateField value={record.last_login_at} format={Format} /> : '-'}
            /
            <TextField value={record.last_login_ipv4 || '-'} />
          </Space>
        );
      },
    },
  ];
}
