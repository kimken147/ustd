import type { AxiosInstance } from 'axios';
import type { SelectProps, TableColumnProps } from 'antd';
import type { Withdraw } from '@morgan-ustd/shared';

/**
 * Shared props for all column definition functions
 */
export interface ColumnContext {
  t: (key: string, options?: Record<string, any>) => string;
  canEdit: boolean;
  profile: Profile | undefined;
  apiUrl: string;
  navigate: (path: string) => void;
  showUpdateModal: (config: any) => void;
  modalConfirm: (config: any) => void;
  mutateAsync: (config: any) => Promise<any>;
  refetch: () => void;
  getWithdrawStatusText: (status: number) => string;
  getTranCallbackStatus: (status: number) => string;
  WithdrawStatus: Record<string, number>;
  tranCallbackStatus: Record<string, number>;
  meta: { banned_realnames: string[] };
  providerSelectProps: SelectProps;
  currentMerchantThirdChannelSelect: SelectProps['options'];
  setSelectMerchantId: (id: number) => void;
  axiosInstance: AxiosInstance;
}

export type WithdrawColumn = TableColumnProps<Withdraw>;
export type ColumnFactory = (ctx: ColumnContext) => WithdrawColumn[];
