export interface Deposit {
    id: number;
    type: number;
    provider: Provider;
    merchant: any;
    system_order_number: string;
    order_number: string;
    amount: string;
    status: number;
    certificate_file_path: any;
    certificate_files: CertificateFile[];
    matched_at: string;
    created_at: string;
    from_channel_account: FromChannelAccount;
    to_channel_account: any;
    to_channel_account_hash_id: any;
    confirmed_at: any;
    note: string;
    notes: Note[];
    note_exist: boolean;
    locked: boolean;
    locked_at: string;
    locked_by: LockedBy;
    lockable: boolean;
    unlockable: boolean;
    confirmable: boolean;
    failable: boolean;
    cancelable: boolean;
    provider_wallet_settled: boolean;
    provider_wallet_should_settled_at: any;
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
    withdraw_fee: string;
    withdraw_fee_percent: string;
    withdraw_profit_fee: string;
    additional_withdraw_fee: string;
    agency_withdraw_fee: string;
    agency_withdraw_fee_dollar: any;
    additional_agency_withdraw_fee: string;
    wallet: Wallet;
    balance_limit: string;
    root_agent: any;
    message_enabled: boolean;
    phone: string;
    contact: string;
    usdt_rate: string;
    withdraw_min_amount: any;
    withdraw_max_amount: any;
    withdraw_profit_min_amount: any;
    withdraw_profit_max_amount: any;
    agency_withdraw_min_amount: any;
    agency_withdraw_max_amount: any;
    tags: any;
    currency: string;
    cancel_order_enable: boolean;
    exchange_mode_enable: boolean;
    control_downline: boolean;
    created_at: string;
}

export interface Wallet {
    balance: string;
    profit: string;
    frozen_balance: string;
    available_balance: string;
}

export interface CertificateFile {
    id: number;
    path: string;
    url: string;
    created_at: string;
    updated_at: string;
}

export interface FromChannelAccount {
    bank_name: string;
    bank_card_number: string;
    bank_card_holder_name: string;
}

export interface Note {
    id: number;
    user: User;
    note: string;
    created_at: string;
}

export interface User {
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
    currency: string;
    cancel_order_enable: boolean;
    exchange_mode_enable: boolean;
    control_downline: boolean;
    created_at: string;
}

export interface LockedBy {
    id: number;
    name: string;
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
    has_new_deposits: boolean;
    total_amount: string;
}
