export interface FormValues {
    provider: string;
    channel_amount_id: string;
    is_auto: number;
    bank_card_number?: string;
    chain_network?: string;
    private_key?: string;
    single_min_limit?: number;
    single_max_limit?: number;
    note?: string;
    // USDT 子地址相關欄位
    address_type?: 'master' | 'child';
    parent_account_id?: number;
}
