import type { TableColumnProps } from 'antd';
import type { SubAccount } from 'interfaces/subAccount';

export type PermissionColumn = TableColumnProps<SubAccount>;

export interface ColumnDependencies {
  t: (key: string) => string;
  filterIds: number[];
  onEditPermission: (record: SubAccount) => void;
}
