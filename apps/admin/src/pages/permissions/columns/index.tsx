import { EditOutlined } from '@ant-design/icons';
import { DateField, ShowButton, TextField } from '@refinedev/antd';
import { Space } from 'antd';
import Badge from 'components/badge';
import type { Permission } from 'interfaces/permission';
import { Format } from '@morgan-ustd/shared';
import type { ColumnDependencies, PermissionColumn } from './types';

export type { ColumnDependencies } from './types';

export function useColumns(deps: ColumnDependencies): PermissionColumn[] {
  const { t, filterIds, onEditPermission } = deps;

  return [
    {
      title: t('list.fields.subAccountName'),
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
      title: t('list.fields.accountStatus'),
      dataIndex: 'status',
      render(value) {
        const color = value === 1 ? '#16a34a' : '#ff4d4f';
        const text = value === 1 ? t('list.fields.enabled') : t('list.fields.disabled');
        return <Badge color={color} text={text} />;
      },
    },
    {
      title: t('list.fields.permissionSettings'),
      dataIndex: 'permissions',
      render(value: Permission[], record) {
        return (
          <Space wrap>
            {value
              .filter(per => !filterIds.includes(per.id))
              .map(permission => (
                <TextField key={permission.id} value={permission.name} code />
              ))}
            <EditOutlined
              style={{ color: '#6eb9ff' }}
              onClick={() => onEditPermission(record)}
            />
          </Space>
        );
      },
    },
    {
      title: t('list.fields.lastLoginTime'),
      render(_, record) {
        return (
          <Space>
            {record.last_login_at ? (
              <DateField value={record.last_login_at} format={Format} />
            ) : (
              t('list.fields.none')
            )}
            /
            <TextField value={record.last_login_ipv4 || t('list.fields.none')} />
          </Space>
        );
      },
    },
  ];
}
