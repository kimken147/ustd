import { DeleteButton } from '@refinedev/antd';
import dayjs from 'dayjs';
import type { ColumnDependencies, ProviderColumn } from './types';

export function createLastLoginColumn(deps: ColumnDependencies): ProviderColumn {
  const { t } = deps;

  return {
    title: t('fields.lastLoginTime'),
    dataIndex: 'last_login_at',
    render(value) {
      return value ? dayjs(value).format('YYYY-MM-DD HH:mm:ss') : null;
    },
  };
}

export function createIpColumn(_deps: ColumnDependencies): ProviderColumn {
  return {
    title: 'IP',
    dataIndex: 'last_login_ipv4',
  };
}

export function createOperationColumn(deps: ColumnDependencies): ProviderColumn {
  const { t } = deps;

  return {
    title: t('operation', { ns: 'common' }),
    render(_, record) {
      return <DeleteButton recordItemId={record.id} danger />;
    },
  };
}
