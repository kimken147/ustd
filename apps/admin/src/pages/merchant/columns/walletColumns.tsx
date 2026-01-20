import { EditOutlined } from '@ant-design/icons';
import { TextField } from '@refinedev/antd';
import { Button, Space } from 'antd';
import type { ColumnDependencies, MerchantColumn } from './types';
import { UpdateMerchantParams } from './types';

export function createBalanceLimitColumn(deps: ColumnDependencies): MerchantColumn {
  const { t, canEditWallet, show } = deps;

  return {
    title: t('fields.balanceLimit'),
    dataIndex: 'balance_limit',
    render(value, record) {
      return (
        <Space>
          <TextField value={value} />
          <Button
            icon={<EditOutlined className="text-[#6eb9ff]" />}
            disabled={!canEditWallet}
            onClick={() => {
              show({
                title: t('fields.balanceLimit'),
                filterFormItems: [UpdateMerchantParams.balance_limit],
                id: record.id,
              });
            }}
          />
        </Space>
      );
    },
  };
}

export function createTotalBalanceColumn(deps: ColumnDependencies): MerchantColumn {
  const { t, canEditWallet, show } = deps;

  return {
    title: t('wallet.totalBalance'),
    dataIndex: 'wallet',
    render(wallet, record) {
      return (
        <Space>
          <TextField value={wallet?.balance} />
          <Button
            disabled={!canEditWallet}
            icon={<EditOutlined className="text-[#6eb9ff]" />}
            onClick={() => {
              show({
                title: t('wallet.editTotalBalance'),
                filterFormItems: [
                  UpdateMerchantParams.type,
                  UpdateMerchantParams.balance_delta,
                  UpdateMerchantParams.note,
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

export function createFrozenBalanceColumn(deps: ColumnDependencies): MerchantColumn {
  const { t, canEditWallet, show } = deps;

  return {
    title: t('wallet.frozenBalance'),
    dataIndex: 'wallet',
    render(wallet, record) {
      return (
        <Space>
          <TextField value={wallet?.frozen_balance || '0.00'} />
          <Button
            icon={<EditOutlined className="text-[#6eb9ff]" />}
            disabled={!canEditWallet}
            onClick={() => {
              show({
                title: t('wallet.editFrozenBalance'),
                filterFormItems: [
                  UpdateMerchantParams.type,
                  UpdateMerchantParams.frozen_balance_delta,
                  UpdateMerchantParams.note,
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

export function createAvailableBalanceColumn(deps: ColumnDependencies): MerchantColumn {
  const { t } = deps;

  return {
    title: t('wallet.availableBalance'),
    dataIndex: 'wallet',
    render(wallet) {
      return wallet?.available_balance || '0.00';
    },
  };
}
