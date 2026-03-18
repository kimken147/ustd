import { Space, Tag } from 'antd';
import { TextField } from '@refinedev/antd';
import type { ColumnDependencies, UserChannelColumn } from './types';

export function createAccountColumn(deps: ColumnDependencies): UserChannelColumn {
  const { t } = deps;

  return {
    title: t('fields.account'),
    render(_, record) {
      return (
        <Space size={4}>
          <TextField value={record.account} />
          {record.has_private_key === false && (
            <Tag color="red">{t('fields.noPrivateKey')}</Tag>
          )}
        </Space>
      );
    },
  };
}
