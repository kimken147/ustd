import type { TableColumnProps } from 'antd';
import type { Merchant } from '@morgan-ustd/shared';

export type MerchantColumn = TableColumnProps<Merchant>;

export const UpdateMerchantParams = {
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
} as const;

export interface ColumnDependencies {
  t: (key: string, options?: Record<string, any>) => string;
  canEditWallet: boolean;
  canEditProfile: boolean;
  show: (options: any) => void;
  showTagModal: (record: Merchant) => void;
  Modal: {
    confirm: (options: any) => void;
  };
}
