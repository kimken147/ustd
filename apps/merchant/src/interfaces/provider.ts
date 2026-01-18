export interface Provider {
    id: number;
    last_login_ipv4?: string;
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
    transactions_today: string;
    withdraw_today: string;
    subtract_today: string;
    wallet: Wallet;
    balance_limit: string;
    agent?: Agent;
    message_enabled: boolean;
    phone?: string;
    contact?: string;
    usdt_rate: string;
    withdraw_min_amount: any;
    withdraw_max_amount: any;
    withdraw_profit_min_amount: any;
    withdraw_profit_max_amount: any;
    agency_withdraw_min_amount: any;
    agency_withdraw_max_amount: any;
    tags: any;
    cancel_order_enable: boolean;
    exchange_mode_enable: boolean;
    control_downline: boolean;
    control_downlines: any[];
    downlines: Downline[];
    created_at: string;
}

export interface Wallet {
    balance: string;
    profit: string;
    frozen_balance: string;
    available_balance: string;
}

export interface Agent {
    id: number;
    name: string;
    username: string;
}

export interface Downline {
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
    provider_todays_amount_enable: ProviderTodaysAmountEnable;
}

export interface ProviderTodaysAmountEnable {
    enabled: boolean;
}
