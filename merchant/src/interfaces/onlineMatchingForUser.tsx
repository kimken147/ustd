export interface OnlinMatchingUser {
    id: number;
    name: string;
    username: string;
    available_balance: string;
    total_paying_count: number;
    paying_balance: number;
    total_withdraw_count: number;
    withdraw_balance: number;
    hash_id: string;
    user_channel_accounts_id: number;
    user_channel_accounts: string;
    daily_status: boolean;
    daily_limit: string;
    daily_total: string;
    monthly_status: boolean;
    monthly_limit: string;
    monthly_total: string;
    withdraw_monthly_limit: string;
    withdraw_monthly_total: string;
    withdraw_daily_limit: string;
    withdraw_daily_total: string;
    device: Device;
    type: number;
    balance: string;
}

export interface Device {
    id: number;
    user_id: number;
    regular_customer_first: boolean;
    last_login_ipv4: any;
    name: string;
    last_login_at: any;
    last_heartbeat_at?: string;
    created_at?: string;
    updated_at?: string;
    deleted_at: any;
}

export interface Meta {
    daily_limit_enabled: boolean;
    monthly_limit_enabled: boolean;
}
