export interface IProfileRes {
    data: Profile;
}

export interface Profile {
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
    permissions: any[];
    ui_permissions: UiPermissions;
    tags: any;
    cancel_order_enable: boolean;
    exchange_mode_enable: boolean;
    control_downline: boolean;
    created_at: string;
}

export interface UiPermissions {
    manage_whitelisted_ip: boolean;
    manage_time_limit_bank: boolean;
    manage_matching_deposit_reward: boolean;
    manage_transaction_reward: boolean;
    manage_fill_in_order: boolean;
    manage_provider_whitelisted_ip: boolean;
    manage_merchant_login_whitelisted_ip: boolean;
    manage_merchant_api_whitelisted_ip: boolean;
    manage_merchant_blocklist: boolean;
    manage_transaction_phone_number: boolean;
    manage_withdraw_csv_and_account_number: boolean;
    manage_merchant_matching_deposit_groups: boolean;
    manage_merchant_transaction_groups: boolean;
    manage_merchant_third_channel: boolean;
}
