import type { TableColumnProps } from 'antd';
import type { Provider } from 'interfaces/provider';

export type ProviderColumn = TableColumnProps<Provider>;

export const UpdateProviderFormField = {
  balance_delta: 'balance_delta',
  note: 'note',
  id: 'id',
  type: 'type',
  frozen_balance_delta: 'frozen_balance_delta',
  profit_delta: 'profit_delta',
  status: 'status',
  withdraw_fee: 'withdraw_fee',
  google2fa_enable: 'google2fa_enable',
  agent_enable: 'agent_enable',
  deposit_enable: 'deposit_enable',
  paufen_deposit_enable: 'paufen_deposit_enable',
  withdraw_enable: 'withdraw_enable',
  transaction_enable: 'transaction_enable',
  credit_mode_enable: 'credit_mode_enable',
} as const;

export interface ColumnDependencies {
  show: (options: {
    title: string;
    id: number;
    filterFormItems?: string[];
    initialValues?: Record<string, unknown>;
  }) => void;
  Modal: {
    confirm: (options: {
      title: string;
      id: number;
      values?: Record<string, unknown>;
      mode?: 'delete';
    }) => void;
  };
}
