export interface InternalTransfer {
    id: number;
    type: number;
    system_order_number: string;
    order_number: string;
    amount: string;
    status: number;
    to_channel_account?: ToChannelAccount;
    actual_amount: string;
    floating_amount: string;
    bank_card_holder_name: string;
    bank_name: string;
    bank_card_number: string;
    note?: string;
    _search1?: string;
    created_at: string;
    confirmed_at?: string;
    notes: Note[];
    locked: boolean;
    locked_at: any;
    locked_by: {
        id: number;
        name: string;
    };
}

export interface ToChannelAccount {
    id: number;
    name: string;
    user_id: number;
    channel_code: string;
    channel_amount_id: number;
    device_id: number;
    wallet_id: number;
    bank_id: number;
    status: number;
    type: number;
    is_auto: boolean;
    auto_sync: boolean;
    balance: string;
    balance_limit: string;
    regular_customer_first: boolean;
    time_limit_disabled: boolean;
    min_amount: any;
    max_amount: any;
    fee_percent: string;
    daily_status: boolean;
    daily_limit: any;
    daily_total: string;
    withdraw_daily_limit: string;
    withdraw_daily_total: string;
    monthly_status: boolean;
    monthly_limit: string;
    monthly_total: string;
    withdraw_monthly_limit: any;
    withdraw_monthly_total: string;
    account: string;
    detail: Detail;
    note: string;
    created_at: string;
    updated_at: string;
    deleted_at: any;
    last_matched_at: string;
}

export interface Detail {
    mpin: string;
    account: string;
    sync_at: string;
    sync_status: string;
    balance_diff: string;
    account_status: string;
}

export interface Note {
    id: number;
    note: string;
    created_at: string;
}

export interface Links {
    first: string;
    last: string;
    prev: any;
    next: any;
}

export interface Meta {
    current_page: number;
    from: number;
    last_page: number;
    path: string;
    per_page: number;
    to: number;
    total: number;
}
