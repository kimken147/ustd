import type { TableColumnProps, SelectProps } from 'antd';

export type PostColumn = TableColumnProps<IPost>;

export interface ColumnDependencies {
  sorters: { field: string; order: 'asc' | 'desc' }[];
  categoriesData?: { data: ICategory[] };
  isLoading: boolean;
  categorySelectProps: SelectProps;
}
