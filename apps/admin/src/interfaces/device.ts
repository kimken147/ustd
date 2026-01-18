export interface Device {
    id: number;
    regular_customer_first: boolean;
    user: User;
    name: string;
    last_login_at: any;
    last_login_ipv4: any;
    last_heartbeat_at?: string;
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
}
