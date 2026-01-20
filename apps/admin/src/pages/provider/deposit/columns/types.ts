import type { TableColumnProps } from 'antd';
import type { DepositGroup } from 'interfaces/matchDepositGroup';

export type DepositGroupColumn = TableColumnProps<DepositGroup>;

export interface ColumnDependencies {
  t: (key: string, options?: Record<string, unknown>) => string;
  tc: (key: string) => string;
  name: string;
  show: (options: {
    title: string;
    initialValues?: Record<string, unknown>;
    mode?: 'create';
    confirmTitle?: string;
    successMessage?: string;
  }) => void;
  UpdateModal: {
    confirm: (options: {
      title: string;
      id: number;
      mode?: 'delete';
    }) => void;
  };
}
