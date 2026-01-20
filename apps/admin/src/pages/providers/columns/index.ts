import type { TableColumnProps } from 'antd';
import type { Provider } from 'interfaces/provider';
import type { ColumnDependencies } from './types';

import { createNameColumn, createTagColumn, createAgentColumn } from './infoColumns';
import {
  createTotalBalanceColumn,
  createAvailableBalanceColumn,
  createFrozenBalanceColumn,
  createProfitColumn,
} from './walletColumns';
import {
  createStatusColumn,
  createGoogle2faColumn,
  createTransactionEnableColumn,
  createDepositEnableColumn,
  createPaufenDepositEnableColumn,
  createAgentEnableColumn,
} from './switchColumns';
import {
  createLastLoginColumn,
  createIpColumn,
  createOperationColumn,
} from './statusColumns';

export type { ColumnDependencies } from './types';
export { UpdateProviderParams } from './types';

export function useColumns(deps: ColumnDependencies): TableColumnProps<Provider>[] {
  return [
    createNameColumn(deps),
    createTagColumn(deps),
    createAgentColumn(deps),
    createTotalBalanceColumn(deps),
    createAvailableBalanceColumn(deps),
    createFrozenBalanceColumn(deps),
    createProfitColumn(deps),
    createStatusColumn(deps),
    createGoogle2faColumn(deps),
    createTransactionEnableColumn(deps),
    createDepositEnableColumn(deps),
    createPaufenDepositEnableColumn(deps),
    createAgentEnableColumn(deps),
    createLastLoginColumn(deps),
    createIpColumn(deps),
    createOperationColumn(deps),
  ];
}
