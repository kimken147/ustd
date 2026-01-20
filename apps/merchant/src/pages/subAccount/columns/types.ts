import type { TableColumnProps } from 'antd';
import type { SubAccount } from 'interfaces/subAccount';

export type SubAccountColumn = TableColumnProps<SubAccount>;

export interface ColumnDependencies {
  t: (key: string) => string;
}
