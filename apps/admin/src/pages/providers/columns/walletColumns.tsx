import { EditOutlined } from '@ant-design/icons';
import { TextField } from '@refinedev/antd';
import { Button, Space } from 'antd';
import { UpdateProviderParams, type ColumnDependencies, type ProviderColumn } from './types';

export function createTotalBalanceColumn(deps: ColumnDependencies): ProviderColumn {
  const { t, show } = deps;

  return {
    title: t('fields.totalBalance'),
    dataIndex: ['wallet', 'balance'],
    render(value, record) {
      return (
        <Space>
          <TextField value={value} />
          <Button
            icon={<EditOutlined className="text-[#6eb9ff]" />}
            onClick={() => {
              show({
                title: t('wallet.editTotalBalance'),
                filterFormItems: [
                  UpdateProviderParams.type,
                  UpdateProviderParams.balance_delta,
                  UpdateProviderParams.note,
                ],
                id: record.id,
              });
            }}
          />
        </Space>
      );
    },
  };
}

export function createAvailableBalanceColumn(deps: ColumnDependencies): ProviderColumn {
  const { t } = deps;

  return {
    title: t('fields.availableBalance'),
    dataIndex: ['wallet', 'available_balance'],
  };
}

export function createFrozenBalanceColumn(deps: ColumnDependencies): ProviderColumn {
  const { t, show } = deps;

  return {
    title: t('fields.frozenBalance'),
    dataIndex: ['wallet', 'frozen_balance'],
    render(value, record) {
      return (
        <Space>
          <TextField value={value} />
          <Button
            icon={<EditOutlined className="text-[#6eb9ff]" />}
            onClick={() => {
              show({
                title: t('actions.adjustFrozenBalance'),
                filterFormItems: [
                  UpdateProviderParams.type,
                  UpdateProviderParams.frozen_balance_delta,
                  UpdateProviderParams.note,
                ],
                id: record.id,
              });
            }}
          />
        </Space>
      );
    },
  };
}

export function createProfitColumn(deps: ColumnDependencies): ProviderColumn {
  const { t, show } = deps;

  return {
    title: t('fields.profit'),
    dataIndex: ['wallet', 'profit'],
    render(value, record) {
      return (
        <Space>
          <TextField value={value} />
          <Button
            icon={<EditOutlined className="text-[#6eb9ff]" />}
            onClick={() => {
              show({
                title: t('fields.profit'),
                filterFormItems: [
                  UpdateProviderParams.type,
                  UpdateProviderParams.profit_delta,
                  UpdateProviderParams.note,
                ],
                id: record.id,
              });
            }}
          />
        </Space>
      );
    },
  };
}
