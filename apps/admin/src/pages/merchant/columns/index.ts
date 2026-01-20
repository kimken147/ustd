import type { TableColumnProps } from 'antd';
import type { Merchant } from '@morgan-ustd/shared';
import type { ColumnDependencies } from './types';

import { createNameColumn, createTagColumn, createAgentColumn } from './infoColumns';
import {
  createBalanceLimitColumn,
  createTotalBalanceColumn,
  createFrozenBalanceColumn,
  createAvailableBalanceColumn,
} from './walletColumns';
import {
  createStatusColumn,
  createTransactionEnableColumn,
  createWithdrawEnableColumn,
  createAgencyWithdrawEnableColumn,
} from './switchColumns';
import { createLastLoginColumn, createIpColumn, createDeleteColumn } from './statusColumns';

export type { ColumnDependencies } from './types';
export { UpdateMerchantParams } from './types';

export function useColumns(deps: ColumnDependencies): TableColumnProps<Merchant>[] {
  return [
    createNameColumn(deps),
    createTagColumn(deps),
    createAgentColumn(deps),
    createBalanceLimitColumn(deps),
    createTotalBalanceColumn(deps),
    createFrozenBalanceColumn(deps),
    createAvailableBalanceColumn(deps),
    createStatusColumn(deps),
    createTransactionEnableColumn(deps),
    createWithdrawEnableColumn(deps),
    createAgencyWithdrawEnableColumn(deps),
    createLastLoginColumn(deps),
    createIpColumn(deps),
    createDeleteColumn(deps),
  ];
}
