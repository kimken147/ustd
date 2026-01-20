import type { TableColumnProps } from 'antd';
import type { MerchantWalletHistory } from '@morgan-ustd/shared';

export type ProviderWalletColumn = TableColumnProps<MerchantWalletHistory>;

export interface ColumnDependencies {
  t: (key: string, options?: Record<string, any>) => string;
  profileRole?: number;
}
