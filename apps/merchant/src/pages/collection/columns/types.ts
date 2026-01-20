import type { TableColumnProps } from 'antd';
import type { Transaction } from 'interfaces/transaction';

export type CollectionColumn = TableColumnProps<Transaction>;

export interface ColumnDependencies {
  t: (key: string) => string;
  tranStatus: Record<string, number>;
  getTranStatusText: (status: number) => string;
  tranCallbackStatus: Record<string, number>;
  getTranCallbackStatus: (status: number) => string;
}
