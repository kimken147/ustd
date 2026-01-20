import type { TableColumnProps } from 'antd';
import type { Transaction } from '@morgan-ustd/shared';
import type { ColumnDependencies } from './types';

import { createLockColumn } from './lockColumn';
import { createOperationColumn } from './operationColumn';
import { createCallbackColumn } from './callbackColumn';
import {
  createProviderColumn,
  createThirdChannelColumn,
  createAccountNumberColumn,
  createMerchantColumn,
} from './infoColumns';
import { createSystemOrderColumn, createMerchantOrderColumn } from './orderColumns';
import {
  createChannelColumn,
  createAmountColumn,
  createTransferNameColumn,
  createStatusColumn,
  createFeeColumn,
  createRemarkColumn,
  createCallbackStatusColumn,
  createMemberIpColumn,
  createCreatedAtColumn,
  createConfirmedAtColumn,
  createRefundInfoColumn,
} from './dataColumns';

export type { ColumnDependencies } from './types';

export function useColumns(deps: ColumnDependencies): TableColumnProps<Transaction>[] {
  return [
    createLockColumn(deps),
    createOperationColumn(deps),
    createCallbackColumn(deps),
    createProviderColumn(deps),
    createThirdChannelColumn(deps),
    createAccountNumberColumn(deps),
    createSystemOrderColumn(deps),
    createMerchantOrderColumn(deps),
    createChannelColumn(deps),
    createAmountColumn(deps),
    createTransferNameColumn(deps),
    createStatusColumn(deps),
    createFeeColumn(deps),
    createRemarkColumn(deps),
    createCallbackStatusColumn(deps),
    createMerchantColumn(deps),
    createMemberIpColumn(deps),
    createCreatedAtColumn(deps),
    createConfirmedAtColumn(deps),
    createRefundInfoColumn(deps),
  ];
}
