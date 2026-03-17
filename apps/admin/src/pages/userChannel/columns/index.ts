import type { TableColumnProps } from 'antd';
import type { ProviderUserChannel as UserChannel } from '@morgan-ustd/shared';
import type { ColumnDependencies } from './types';

import { createProviderColumn } from './providerColumn';
import { createStatusColumn } from './statusColumn';
import { createTypeColumn } from './typeColumn';
import { createAccountColumn } from './accountColumn';
import { createAddressTypeColumn } from './addressTypeColumn';
import { createReceiveStatusColumn } from './receiveStatusColumn';
import { createParentAccountColumn } from './parentAccountColumn';
import { createChannelColumn } from './bankColumns';
import { createNoteColumn, createAccountNumberColumn } from './infoColumns';
import { createBalanceColumn, createBalanceLimitColumn } from './balanceColumns';
import { createOnchainUsdtColumn, createOnchainTrxColumn, createOnchainSyncedAtColumn } from './onchainColumns';
import { createSingleLimitColumn } from './singleLimitColumn';
import {
  createDailyStatusColumn,
  createDailyLimitReceiveColumn,
  createDailyLimitPayoutColumn,
} from './dailyLimitColumns';
import {
  createDailyReceiveCountLimitColumn,
  createDailyPayoutCountLimitColumn,
} from './dailyCountColumns';
import {
  createMonthlyStatusColumn,
  createMonthlyLimitReceiveColumn,
  createMonthlyLimitPayoutColumn,
} from './monthlyLimitColumns';
import {
  createMonthlyReceiveCountLimitColumn,
  createMonthlyPayoutCountLimitColumn,
} from './monthlyCountColumns';
import { createOperationColumn } from './operationColumn';

export type { ColumnDependencies } from './types';

export function useColumns(deps: ColumnDependencies): TableColumnProps<UserChannel>[] {
  const columns: (TableColumnProps<UserChannel> | null)[] = [
    createProviderColumn(deps),
    createChannelColumn(deps),
    createStatusColumn(deps),
    createTypeColumn(deps),
    createAccountColumn(deps),
    createAddressTypeColumn(deps),
    createReceiveStatusColumn(deps),
    createParentAccountColumn(deps),
    createNoteColumn(deps),
    createAccountNumberColumn(deps),
    createBalanceColumn(deps),
    createBalanceLimitColumn(deps),
    createOnchainUsdtColumn(deps),
    createOnchainTrxColumn(deps),
    createOnchainSyncedAtColumn(deps),
    createSingleLimitColumn(deps),
    // 額度 group
    createDailyStatusColumn(deps),
    createDailyLimitReceiveColumn(deps),
    createDailyLimitPayoutColumn(deps),
    createMonthlyStatusColumn(deps),
    createMonthlyLimitReceiveColumn(deps),
    createMonthlyLimitPayoutColumn(deps),
    // 筆數 group
    createDailyReceiveCountLimitColumn(deps),
    createDailyPayoutCountLimitColumn(deps),
    createMonthlyReceiveCountLimitColumn(deps),
    createMonthlyPayoutCountLimitColumn(deps),
    createOperationColumn(deps),
  ];

  return columns.filter((col): col is TableColumnProps<UserChannel> => col !== null);
}
