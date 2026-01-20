import type { TableColumnProps } from 'antd';
import type { ProviderUserChannel as UserChannel } from '@morgan-ustd/shared';
import type { ColumnDependencies } from './types';

import { createProviderColumn } from './providerColumn';
import { createStatusColumn } from './statusColumn';
import { createTypeColumn } from './typeColumn';
import { createAccountColumn } from './accountColumn';
import {
  createChannelColumn,
  createBankNameColumn,
  createBankBranchColumn,
  createBankCardHolderColumn,
} from './bankColumns';
import { createNoteColumn, createAccountNumberColumn } from './infoColumns';
import { createBalanceColumn, createBalanceLimitColumn } from './balanceColumns';
import { createSingleLimitColumn } from './singleLimitColumn';
import {
  createDailyStatusColumn,
  createDailyLimitReceiveColumn,
  createDailyLimitPayoutColumn,
} from './dailyLimitColumns';
import {
  createMonthlyStatusColumn,
  createMonthlyLimitReceiveColumn,
  createMonthlyLimitPayoutColumn,
} from './monthlyLimitColumns';
import { createOperationColumn } from './operationColumn';

export type { ColumnDependencies } from './types';

export function useColumns(deps: ColumnDependencies): TableColumnProps<UserChannel>[] {
  const columns: (TableColumnProps<UserChannel> | null)[] = [
    createProviderColumn(deps),
    createChannelColumn(deps),
    createStatusColumn(deps),
    createTypeColumn(deps),
    createAccountColumn(deps),
    createBankNameColumn(deps),
    createBankBranchColumn(deps),
    createBankCardHolderColumn(deps),
    createNoteColumn(deps),
    createAccountNumberColumn(deps),
    createBalanceColumn(deps),
    createBalanceLimitColumn(deps),
    createSingleLimitColumn(deps),
    createDailyStatusColumn(deps),
    createDailyLimitReceiveColumn(deps),
    createDailyLimitPayoutColumn(deps),
    createMonthlyStatusColumn(deps),
    createMonthlyLimitReceiveColumn(deps),
    createMonthlyLimitPayoutColumn(deps),
    createOperationColumn(deps),
  ];

  return columns.filter((col): col is TableColumnProps<UserChannel> => col !== null);
}
