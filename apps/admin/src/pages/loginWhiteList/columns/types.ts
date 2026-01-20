import type { TableColumnProps } from 'antd';
import type { User } from '@morgan-ustd/shared';

export type LoginWhiteListColumn = TableColumnProps<User>;

export interface ColumnDependencies {
  t: (key: string) => string;
  show: (options: {
    title: string;
    id: number;
    initialValues?: Record<string, unknown>;
    onSuccess?: () => void;
    resource?: string;
    mode?: 'create';
  }) => void;
  Modal: {
    confirm: (options: {
      id: number;
      resource: string;
      title: string;
      mode: 'delete';
      onSuccess?: () => void;
    }) => void;
  };
  refetch: () => void;
}
