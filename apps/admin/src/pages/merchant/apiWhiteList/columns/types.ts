import type { TableColumnProps } from 'antd';
import type { User } from '@morgan-ustd/shared';

export type ApiWhiteListColumn = TableColumnProps<User>;

export interface ColumnDependencies {
  t: (key: string, options?: Record<string, unknown>) => string;
  show: (options: {
    title: string;
    id?: number;
    resource?: string;
    filterFormItems?: string[];
    initialValues?: Record<string, unknown>;
    formValues?: Record<string, unknown>;
    mode?: 'create';
  }) => void;
  Modal: {
    confirm: (options: {
      title: string;
      id: number;
      mode: 'delete';
      resource: string;
      onSuccess: () => void;
    }) => void;
  };
  refetch: () => void;
}
