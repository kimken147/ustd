import type { TableColumnProps } from 'antd';
import type { SystemSetting } from 'interfaces/systemSetting';

export type SystemSettingColumn = TableColumnProps<SystemSetting>;

export interface ColumnDependencies {
  t: (key: string, options?: Record<string, unknown>) => string;
  show: (options: {
    id: number;
    initialValues?: Record<string, unknown>;
    filterFormItems?: string[];
    title: string;
  }) => void;
  Modal: {
    confirm: (options: {
      title: string;
      id: number;
      values: Record<string, unknown>;
    }) => void;
  };
}
