import type { TableColumnProps } from 'antd';
import type { Tag } from '@morgan-ustd/shared';

export type TagColumn = TableColumnProps<Tag>;

export interface ColumnDependencies {
  t: (key: string) => string;
}
