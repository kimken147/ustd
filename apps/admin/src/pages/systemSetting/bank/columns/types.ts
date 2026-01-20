import type { TableColumnProps } from 'antd';
import type { Bank } from '@morgan-ustd/shared';

export type BankColumn = TableColumnProps<Bank>;

export interface ColumnDependencies {
  t: (key: string) => string;
  setCurrent: (bank: Bank) => void;
  setAction: (action: 'create' | 'edit') => void;
  show: () => void;
}
