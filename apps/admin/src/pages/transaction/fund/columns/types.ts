import type { TableColumnProps } from 'antd';
import type { InternalTransfer } from 'interfaces/internalTransfer';

export type FundColumn = TableColumnProps<InternalTransfer>;

export interface ColumnDependencies {
  t: (key: string, options?: Record<string, unknown>) => string;
  profile: Profile | undefined;
  WithdrawStatus: {
    成功: number;
    失败: number;
    手动成功: number;
    支付超时: number;
    等待付款: number;
    匹配中: number;
    匹配超时: number;
  };
  getWithdrawStatusText: (status: number) => string;
  showUpdateModal: (options: {
    id: number;
    title: string;
    filterFormItems?: string[];
    initialValues?: Record<string, unknown>;
    children?: React.ReactNode;
    customMutateConfig?: {
      url: string;
      method: 'post' | 'put' | 'patch' | 'delete';
    };
  }) => void;
  Modal: {
    confirm: (options: {
      title: string;
      id: number;
      values: Record<string, unknown>;
    }) => void;
  };
  apiUrl: string;
}
