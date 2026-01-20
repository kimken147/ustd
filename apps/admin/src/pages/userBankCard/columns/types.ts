import type { TableColumnProps } from 'antd';
import type { UserBankCard } from 'interfaces/userBankCard';

export type UserBankCardColumn = TableColumnProps<UserBankCard>;

export interface ColumnDependencies {
  t: (key: string, options?: Record<string, unknown>) => string;
  Modal: {
    confirm: (options: {
      id: number;
      values?: Record<string, unknown>;
      title: string;
      mode?: 'delete';
      className?: string;
    }) => void;
  };
  canDelete: boolean;
  getStatusText: (status: number) => string;
}
