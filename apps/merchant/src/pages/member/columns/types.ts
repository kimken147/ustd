import type { TableColumnProps } from 'antd';
import type { Member } from 'interfaces/member';

export type MemberColumn = TableColumnProps<Member>;

export interface ColumnDependencies {
  // No dependencies needed for this simple table
}
