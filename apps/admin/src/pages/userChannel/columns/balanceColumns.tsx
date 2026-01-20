import { EditOutlined } from '@ant-design/icons';
import { TextField } from '@refinedev/antd';
import { Button, Space } from 'antd';
import type { ColumnDependencies, UserChannelColumn } from './types';

export function createBalanceColumn(deps: ColumnDependencies): UserChannelColumn {
  const { t, canEdit, showUpdateModal } = deps;

  return {
    dataIndex: 'balance',
    title: t('fields.balance'),
    render(value, record) {
      return (
        <Space>
          <TextField value={value} />
          <Button
            icon={<EditOutlined className={canEdit ? 'text-[#6eb9ff]' : ''} />}
            onClick={() => {
              showUpdateModal({
                title: t('actions.editBalance'),
                id: record.id,
                initialValues: { balance: record.balance },
                filterFormItems: ['balance'],
              });
            }}
            disabled={!canEdit}
          />
        </Space>
      );
    },
  };
}

export function createBalanceLimitColumn(deps: ColumnDependencies): UserChannelColumn {
  const { t, canEdit, showUpdateModal } = deps;

  return {
    dataIndex: 'balance_limit',
    title: t('fields.balanceLimit'),
    render(value, record) {
      return (
        <Space>
          <TextField value={value} />
          <Button
            disabled={!canEdit}
            icon={<EditOutlined className={canEdit ? 'text-[#6eb9ff]' : ''} />}
            onClick={() => {
              showUpdateModal({
                id: record.id,
                initialValues: { balance_limit: value },
                filterFormItems: ['balance_limit'],
                title: t('actions.editBalanceLimit'),
              });
            }}
          />
        </Space>
      );
    },
  };
}
