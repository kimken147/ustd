export interface User {
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
    whitelisted_ips?: WhitelistedIp[];
    tags: any;
    cancel_order_enable: boolean;
    exchange_mode_enable: boolean;
    control_downline: boolean;
    created_at: string;
    password?: string;
    secret?: string;
    user_channels?: UserChannel[];
}

export interface WhitelistedIp {
    id: number;
    ipv4: string;
    created_at: string;
}

export interface UserChannel {
    id: number;
    name: string;
    code: string;
    amount_description: string;
    status: number;
    min_amount?: any;
    max_amount?: any;
    fee_percent: string;
    floating_enable: boolean;
    real_name_enable: boolean;
    deposit_account_fields: DepositAccountFields;
}
export interface DepositAccountFields {
    fields: Fields;
    auto_daifu_banks: string[];
    auto_daiso_banks: string[];
    user_can_deposit_banks: any[];
    merchant_can_withdraw_banks: string[];
    account: string;
    qr_code: string;
    bank_card_holder_name: string;
    mobile: string;
}

export interface Fields {
    account: string;
    qr_code: string;
    receiver_name: string;
    bank_name: string;
    bank_card_branch: string;
    bank_card_number: string;
    bank_card_holder_name: string;
    otp: string;
    pin: string;
    pwd: string;
    mpin: string;
}
