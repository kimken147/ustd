import type { TableColumnProps } from 'antd';
import type { Deposit } from 'interfaces/deposit';

export type DepositColumn = TableColumnProps<Deposit>;

export interface ColumnDependencies {
  t: (key: string, options?: Record<string, any>) => string;
  apiUrl: string;
  profile: Profile | undefined;
  Status: Record<string, number>;
  getStatusText: (status: number) => string;
  show: (options: any) => void;
  showSuccessModal: () => void;
  setCurrent: (record: Deposit) => void;
  UpdateModal: {
    confirm: (options: any) => void;
  };
  refetch: () => void;
}
