import type { TableColumnProps } from 'antd';
import type { WalletHistory } from 'interfaces/wallet-history';

export type WalletHistoryColumn = TableColumnProps<WalletHistory>;

export interface ColumnDependencies {
  t: (key: string) => string;
}
