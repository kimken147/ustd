import type { TableColumnProps } from 'antd';
import type { BankCard } from 'interfaces/bankCard';

export type BankCardColumn = TableColumnProps<BankCard>;

export interface ColumnDependencies {
  t: (key: string) => string;
  showUpdateModal: (options: {
    initialValues: BankCard;
    id: number;
    filterFormItems: unknown[];
    title: string;
  }) => void;
}
