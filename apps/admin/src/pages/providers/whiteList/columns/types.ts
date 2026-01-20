import type { TableColumnProps } from 'antd';
import type { User, WhitelistedIp } from '@morgan-ustd/shared';

export type ProviderWhiteListColumn = TableColumnProps<User>;

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
      values: Record<string, unknown>;
      resource: string;
      onSuccess: () => void;
    }) => void;
  };
  refetch: () => void;
}
