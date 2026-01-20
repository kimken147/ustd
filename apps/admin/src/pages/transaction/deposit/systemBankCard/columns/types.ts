import type { TableColumnProps } from 'antd';
import type { SystemBankCard } from 'interfaces/systemBankCard';

export type SystemBankCardColumn = TableColumnProps<SystemBankCard>;

export interface ColumnDependencies {
  t: (key: string, options?: Record<string, unknown>) => string;
  show: (options: {
    title: string;
    filterFormItems?: string[];
    id: number;
    initialValues?: Record<string, unknown>;
  }) => void;
  setSelectedKey: (id: number) => void;
  showUpdateUserModal: () => void;
  update: (options: {
    resource: string;
    id: number;
    values: Record<string, unknown>;
    successNotification?: {
      message: string;
      type: string;
    };
  }) => Promise<unknown>;
}
