import type { TableColumnProps } from 'antd';
import type { BankCard } from 'interfaces/bankCard';

export type BankCardColumn = TableColumnProps<BankCard>;

export interface ColumnDependencies {
  t: (key: string) => string;
  showUpdateModal: (config: {
    initialValues?: any;
    id?: string | number;
    filterFormItems?: any[];
    title: string;
    [key: string]: any;
  }) => void;
}
