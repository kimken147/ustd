export interface ITransactionRes {
    data: any[];
    links: Links;
    meta: Meta;
}

export interface Transaction {
    id: number;
    operator: any;
    merchant: Merchant;
    system_order_number: string;
    parent_system_order_number: any;
    child_system_order_number: any;
    child_id: any;
    is_partial_success: boolean;
    order_number: string;
    channel_name: string;
    channel_code: string;
    amount: string;
    status: number;
    notify_status: number;
    bug_report: any;
    note_exist: boolean;
    matched_at?: string;
    created_at: string;
    confirmed_at: any;
    notified_at: any;
    operated_at: any;
    provider?: Provider;
    provider_device_name?: string;
    provider_channel_account_hash_id?: string;
    provider_channel_account_id?: number;
    provider_account?: string;
    provider_account_name: any;
    provider_account_vendor_name: any;
    provider_account_note?: any;
    provider_bank_card_branch: any;
    qr_code_file_path: any;
    actual_amount: string;
    floating_amount: string;
    fee?: string;
    merchant_fees: MerchantFee[];
    provider_fees: any[];
    system_profit: any;
    notify_url: string;
    client_ip: string;
    note: any;
    lockable: boolean;
    unlockable: boolean;
    confirmable: boolean;
    failable: boolean;
    locked: boolean;
    locked_at: any;
    locked_by: any;
    refunded_at: any;
    refunded_by: any;
    should_refund_at: any;
    thirdchannel: Thirdchannel;
    certificate_file_path: any;
    certificate_files: any[];
    real_name: string;
    usdt_rate: string;
    _search1: any;
    mobile_number?: string;
    type: number;
    from_channel_account: FromChannelAccount;
    to_channel_account: ToChannelAccount;
}

export interface Merchant {
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
    phone: string;
    contact: string;
    usdt_rate: string;
    tags: string[];
    cancel_order_enable: boolean;
    exchange_mode_enable: boolean;
    control_downline: boolean;
    created_at: string;
}

export interface Provider {
    id: number;
    last_login_ipv4: string;
    role: number;
    status: number;
    agent_enable: boolean;
    google2fa_enable: boolean;
    deposit_enable: boolean;
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
    phone: string;
    contact: string;
    usdt_rate: string;
    tags: any;
    cancel_order_enable: boolean;
    exchange_mode_enable: boolean;
    control_downline: boolean;
    created_at: string;
    note?: string;
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
    phone: string;
    contact: string;
    usdt_rate: string;
    tags?: string[];
    cancel_order_enable: boolean;
    exchange_mode_enable: boolean;
    control_downline: boolean;
    created_at: string;
}

export interface Stat {
    total_amount: string;
    total_fee: string;
    total_profit: string;
    third_channel_fee: string;
    total_success: number;
}

export interface Links {
    first: string;
    last: string;
    prev: any;
    next: string;
}

export interface Meta {
    current_page: number;
    from: number;
    last_page: number;
    path: string;
    per_page: number;
    to: number;
    total: number;
    banned_ips: string[];
    banned_realnames: string[];
    channel_note_enable: boolean;
}

export interface DemoCreateRes {
    url: string;
}

export interface Thirdchannel {
    id: number;
    name: string;
    class: string;
    type: number;
    channel_code: string;
    status: number;
    sync: number;
    custom_url: string;
    balance: string;
    notify_balance: string;
    auto_daifu_threshold: string;
    proxy: any;
    merchant_id: any;
    key: string;
    key2: string;
    key3: any;
    white_ip: string;
    created_at: any;
    updated_at: string;
}

export interface FromChannelAccount {
    bank_city: string;
    bank_name: string;
    bank_province: string;
    bank_card_number: string;
    extra_withdraw_fee: number;
    bank_card_holder_name: string;
}

export interface ToChannelAccount {
    mpin: string;
    status: string;
    account: string;
    sync_at: string;
    accessToken: string;
    sync_status: string;
    channel_code: string;
    expiresChallengeId: string;
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
