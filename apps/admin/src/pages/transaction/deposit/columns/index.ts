import type { TableColumnProps } from 'antd';
import type { Deposit } from 'interfaces/deposit';
import type { ColumnDependencies } from './types';

import { createNoteColumn } from './noteColumn';
import { createLockColumn } from './lockColumn';
import { createOperationColumn } from './operationColumn';
import {
  createTypeColumn,
  createProviderColumn,
  createAmountColumn,
  createCollectionPartyColumn,
  createStatusColumn,
  createMerchantColumn,
} from './infoColumns';
import {
  createMatchedAtColumn,
  createCreatedAtColumn,
  createConfirmedAtColumn,
} from './dateColumns';
import { createSystemOrderColumn, createMerchantOrderColumn } from './orderColumns';

export type { ColumnDependencies } from './types';

export function useColumns(deps: ColumnDependencies): TableColumnProps<Deposit>[] {
  return [
    createNoteColumn(deps),
    createLockColumn(deps),
    createOperationColumn(deps),
    createTypeColumn(deps),
    createProviderColumn(deps),
    createAmountColumn(deps),
    createCollectionPartyColumn(deps),
    createStatusColumn(deps),
    createMatchedAtColumn(deps),
    createCreatedAtColumn(deps),
    createConfirmedAtColumn(deps),
    createSystemOrderColumn(deps),
    createMerchantOrderColumn(deps),
    createMerchantColumn(deps),
  ];
}
