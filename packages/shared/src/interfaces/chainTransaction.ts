// 鏈上交易相關介面定義

export interface ChainTransactionAccount {
  id: number;
  name: string;
  account: string;
}

export interface ChainTransactionMatchedTx {
  id: number;
  order_number: string;
  amount: string;
  status: number;
}

export interface ChainTransactionMatchedByUser {
  id: number;
  name: string;
}

export interface ChainTransaction {
  id: number;
  tx_hash: string;
  user_channel_account_id: number | null;
  user_channel_account: ChainTransactionAccount | null;
  direction: 'in' | 'out';
  from_address: string;
  to_address: string;
  amount: string;
  block_number: number | null;
  block_timestamp: string;
  confirmations: number;
  match_status: 'pending' | 'matched' | 'unmatched' | 'ignored';
  matched_transaction_id: number | null;
  matched_transaction: ChainTransactionMatchedTx | null;
  matched_at: string | null;
  matched_by: number | null;
  matched_by_user: ChainTransactionMatchedByUser | null;
  note: string | null;
  created_at: string;
  updated_at: string;
}
