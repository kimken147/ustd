import type { TableColumnProps } from 'antd';
import type { Banned } from 'interfaces/banned';

export type BannedColumn = TableColumnProps<Banned>;

export interface IPColumnDependencies {
  t: (key: string) => string;
  show: (options: {
    title: string;
    id: number;
    filterFormItems: string[];
    initialValues: Record<string, unknown>;
    resource: string;
  }) => void;
  Modal: {
    confirm: (options: {
      title: string;
      id: string;
      resource: string;
      values: Record<string, unknown>;
      mode: 'delete';
    }) => void;
  };
}

export interface NameColumnDependencies {
  t: (key: string) => string;
  type: number;
  show: (options: {
    title: string;
    id: number;
    filterFormItems: string[];
    initialValues: Record<string, unknown>;
    resource: string;
  }) => void;
  Modal: {
    confirm: (options: {
      title: string;
      id: string;
      resource: string;
      values: Record<string, unknown>;
      mode: 'delete';
    }) => void;
  };
}
