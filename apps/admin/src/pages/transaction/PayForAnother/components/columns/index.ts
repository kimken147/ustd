/**
 * Columns module for PayForAnother list
 *
 * Exports the useColumns hook that combines all column groups:
 * - Info columns: order number, payment type, payer info, user name
 * - Action columns: locked, operation, callback, third party payout
 * - Status columns: order status, callback status
 * - Bank columns: bank name, province, city, card number, card holder
 * - Data columns: amount, fee, created at, confirmed at, system order number
 */
import { useMemo } from 'react';
import type { AxiosInstance } from 'axios';
import type { TableColumnProps, SelectProps } from 'antd';
import { useTranslation } from 'react-i18next';
import type { Withdraw } from '@morgan-ustd/shared';
import type { ColumnContext, WithdrawColumn } from './types';
import { getInfoColumns } from './infoColumns';
import { getActionColumns } from './actionColumns';
import { getStatusColumns } from './statusColumns';
import { getBankColumns } from './bankColumns';
import { getDataColumns } from './dataColumns';

export interface UseColumnsProps {
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

/**
 * Hook that returns all columns for the PayForAnother list
 */
export function useColumns(props: UseColumnsProps): TableColumnProps<Withdraw>[] {
  const { t } = useTranslation('transaction');

  const ctx: ColumnContext = {
    t,
    ...props,
  };

  return useMemo(
    () => {
      const infoColumns = getInfoColumns(ctx);
      const actionColumns = getActionColumns(ctx);
      const statusColumns = getStatusColumns(ctx);
      const bankColumns = getBankColumns(ctx);
      const dataColumns = getDataColumns(ctx);

      // Arrange columns in the original order
      return [
        infoColumns[0],  // order number
        actionColumns[0], // locked
        actionColumns[1], // operation
        actionColumns[2], // callback
        actionColumns[3], // third party payout
        infoColumns[1],  // payment type
        infoColumns[2],  // payer info
        infoColumns[3],  // user name
        statusColumns[0], // order status
        bankColumns[0],  // bank name
        bankColumns[1],  // province
        bankColumns[2],  // city
        bankColumns[3],  // card number
        bankColumns[4],  // card holder
        dataColumns[0],  // amount
        dataColumns[1],  // fee
        dataColumns[2],  // created at
        dataColumns[3],  // confirmed at
        statusColumns[1], // callback status
        dataColumns[4],  // system order number
      ];
    },
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [
      t,
      props.canEdit,
      props.profile,
      props.apiUrl,
      props.navigate,
      props.showUpdateModal,
      props.modalConfirm,
      props.mutateAsync,
      props.refetch,
      props.getWithdrawStatusText,
      props.getTranCallbackStatus,
      props.WithdrawStatus,
      props.tranCallbackStatus,
      props.meta,
      props.setSelectMerchantId,
      props.axiosInstance,
    ]
  );
}

// Re-export types
export type { ColumnContext, WithdrawColumn } from './types';

// Re-export individual column factories for flexibility
export { getInfoColumns } from './infoColumns';
export { getActionColumns } from './actionColumns';
export { getStatusColumns } from './statusColumns';
export { getBankColumns } from './bankColumns';
export { getDataColumns } from './dataColumns';

export default useColumns;
