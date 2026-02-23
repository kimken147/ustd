import { TextField } from '@refinedev/antd';
import type { ColumnDependencies, UserChannelColumn } from './types';

export function createAccountColumn(deps: ColumnDependencies): UserChannelColumn {
  const { t } = deps;

  return {
    title: t('fields.account'),
    render(_, record) {
      return <TextField value={record.account} />;
    },
  };
}
