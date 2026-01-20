import type { TableColumnProps } from 'antd';
import type { Transaction, TransactionMeta } from '@morgan-ustd/shared';

export type CollectionColumn = TableColumnProps<Transaction>;

export interface ColumnDependencies {
  t: (key: string, options?: Record<string, any>) => string;
  apiUrl: string;
  canEdit: boolean;
  canShowSI: boolean;
  isPaufen: boolean;
  groupLabel: string;
  profile: Profile | undefined;
  meta: TransactionMeta;
  tranStatus: Record<string, number>;
  tranCallbackStatus: Record<string, number>;
  getTranStatusText: (status: number) => string;
  getTranCallbackStatus: (status: number) => string;
  refetch: () => void;
  show: (options: any) => void;
  customMutate: (options: any) => Promise<any>;
  Modal: {
    confirm: (options: any) => void;
  };
}
