import type { TableColumnProps } from 'antd';
import type { UserWalletHistory } from 'interfaces/userWalletHistory';

export type ProviderUserWalletColumn = TableColumnProps<UserWalletHistory>;

export interface ColumnDependencies {
  t: (key: string, options?: Record<string, any>) => string;
  profileRole?: number;
  userId: string;
  getUserWalletStatusText: (value: number) => string;
  show: (options: any) => void;
}
