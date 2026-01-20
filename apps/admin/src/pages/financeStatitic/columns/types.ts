import type { TableColumnProps } from 'antd';
import type { FinanceStatistic } from 'interfaces/finance';

export type FinanceStatisticColumn = TableColumnProps<FinanceStatistic>;

export interface ColumnDependencies {
  t: (key: string) => string;
}
