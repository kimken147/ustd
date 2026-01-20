import type { TableColumnProps } from 'antd';
import type { Provider } from 'interfaces/provider';

export type ProviderColumn = TableColumnProps<Provider>;

export const UpdateProviderParams = {
  balance_limit: 'balance_limit',
  balance_delta: 'balance_delta',
  type: 'type',
  note: 'note',
  frozen_balance_delta: 'frozen_balance_delta',
  withdraw_fee: 'withdraw_fee',
  status: 'status',
  google2fa_enable: 'google2fa_enable',
  agent_enable: 'agent_enable',
  withdraw_enable: 'withdraw_enable',
  agency_withdraw_enable: 'agency_withdraw_enable',
  withdraw_google2fa_enable: 'withdraw_google2fa_enable',
  transaction_enable: 'transaction_enable',
  third_channel_enable: 'third_channel_enable',
  profit_delta: 'profit_delta',
  deposit_enable: 'deposit_enable',
  paufen_deposit_enable: 'paufen_deposit_enable',
} as const;

export interface ColumnDependencies {
  t: (key: string, options?: Record<string, any>) => string;
  show: (options: any) => void;
  showTagModal: (record: Provider) => void;
  UpdateModal: {
    confirm: (options: any) => void;
  };
}
