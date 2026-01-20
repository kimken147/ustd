import type { ColumnDependencies, DepositColumn } from './types';

export function createSystemOrderColumn(deps: ColumnDependencies): DepositColumn {
  const { t } = deps;

  return {
    title: t('fields.systemOrderNumber'),
    dataIndex: 'system_order_number',
  };
}

export function createMerchantOrderColumn(deps: ColumnDependencies): DepositColumn {
  const { t } = deps;

  return {
    title: t('fields.merchantOrderNumber'),
    dataIndex: 'order_number',
  };
}
