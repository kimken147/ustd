export interface Transaction {
    id: number;
    system_order_number: string;
    order_number: any;
    channel_name: string;
    channel_code: string;
    amount: string;
    fee: string;
    merchant: Merchant;
    merchant_fees: MerchantFee[];
    status: number;
    notify_status: number;
    created_at: string;
    confirmed_at: string;
    notified_at: any;
    matched_at: string;
    actual_amount: string;
    floating_amount: string;
    notify_url: any;
    client_ip: any;
    usdt_rate: string;
    _search1: any;
}

export interface Merchant {
    id: number;
    last_login_ipv4: string;
    role: number;
    status: number;
    agent_enable: boolean;
    google2fa_enable: boolean;
    withdraw_enable: boolean;
    withdraw_google2fa_enable: boolean;
    agency_withdraw_enable: boolean;
    transaction_enable: boolean;
    credit_mode_enable: boolean;
    deposit_mode_enable: boolean;
    account_mode: number;
    name: string;
    username: string;
    last_login_at: string;
    phone: any;
    contact: any;
}

export interface MerchantFee {
    merchant: Merchant2;
    fee: string;
    profit: string;
    actual_fee: string;
    actual_profit: string;
}

export interface Merchant2 {
    id: number;
    last_login_ipv4: string;
    role: number;
    status: number;
    agent_enable: boolean;
    google2fa_enable: boolean;
    paufen_deposit_enable: boolean;
    withdraw_review_enable: boolean;
    withdraw_enable: boolean;
    withdraw_profit_enable: boolean;
    withdraw_google2fa_enable: boolean;
    paufen_withdraw_enable: boolean;
    agency_withdraw_enable: boolean;
    paufen_agency_withdraw_enable: boolean;
    transaction_enable: boolean;
    third_channel_enable: boolean;
    credit_mode_enable: boolean;
    deposit_mode_enable: boolean;
    balance_transfer_enable: boolean;
    ready_for_matching: boolean;
    account_mode: number;
    name: string;
    username: string;
    last_login_at: string;
    balance_limit: string;
    message_enabled: boolean;
    phone: any;
    contact: any;
    usdt_rate: string;
    tags: any;
    cancel_order_enable: boolean;
    exchange_mode_enable: boolean;
    control_downline: boolean;
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
    total_amount: string;
    total_fee: string;
    total_success: string;
}

export enum TransactionType {
    TYPE_PAUFEN_TRANSACTION = 1, // 跑分交易
    TYPE_PAUFEN_WITHDRAW = 2, // 跑分提現、跑分充值
    TYPE_NORMAL_DEPOSIT = 3, // 一般充值
    TYPE_NORMAL_WITHDRAW = 4, // 一般提現
    TYPE_INTERNAL_TRANSFER = 5, // 内部轉帳
    TYPE_VIRTUAL_PAUFEN_WITHDRAW_AVAILABLE_FOR_ADMIN = 201, // 管理用虛擬狀態：跑分提現（可鎖定）
}

export enum TransactionSubType {
    SUB_TYPE_WITHDRAW = 1,
    SUB_TYPE_AGENCY_WITHDRAW = 2,
    SUB_TYPE_WITHDRAW_PROFIT = 3,
}
