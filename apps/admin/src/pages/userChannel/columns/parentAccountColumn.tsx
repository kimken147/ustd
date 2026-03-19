import { Link } from 'react-router';
import type { ColumnDependencies, UserChannelColumn } from './types';

/**
 * 母地址欄位（子地址時顯示母地址連結）
 */
export function createParentAccountColumn(deps: ColumnDependencies): UserChannelColumn {
  const { t } = deps;
  return {
    title: t('fields.parentAccount'),
    dataIndex: 'parent_account',
    width: 120,
    render(value: any, record) {
      if (!value || record.address_type !== 'child') return '-';
      return (
        <Link to={`/user-channel-accounts/${value.id}`}>
          {value.account?.slice(0, 8)}...
        </Link>
      );
    },
  };
}
