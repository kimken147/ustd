export interface Provider {
    id: number;
    username: string;
    name: string;
    user_channels: UserChannel2[];
}

export interface Merchant {
    id: number;
    last_login_ipv4: any;
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
    withdraw_fee: string;
    withdraw_fee_percent: string;
    withdraw_profit_fee: string;
    additional_withdraw_fee: string;
    agency_withdraw_fee: string;
    agency_withdraw_fee_dollar: string;
    additional_agency_withdraw_fee: string;
    wallet: Wallet;
    balance_limit: string;
    agent: any;
    message_enabled: boolean;
    user_channels: UserChannel[];
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
    cancel_order_enable: boolean;
    exchange_mode_enable: boolean;
    control_downline: boolean;
    created_at: string;
}

export interface UserChannel {
    id: number;
    name: string;
    code: string;
    amount_description: string;
    status: number;
    min_amount: any;
    max_amount: any;
    fee_percent?: string;
    floating_enable: boolean;
    real_name_enable: boolean;
    deposit_account_fields?: DepositAccountFields;
    channel_group_id: number;
}

export interface DepositAccountFields {
    account?: string;
    qr_code?: string;
    bank_card_holder_name?: string;
    fields?: Fields;
    auto_daifu_banks?: string[];
    auto_daiso_banks?: string[];
    user_can_deposit_banks?: any[];
    merchant_can_withdraw_banks?: string[];
    mobile?: string;
}

export interface Fields {
    account?: string;
    otp?: string;
    pin?: string;
    pwd?: string;
    bank_name?: string;
    bank_card_holder_name?: string;
    bank_card_branch?: string;
    bank_card_number?: string;
    qr_code?: string;
    receiver_name?: string;
    mpin?: string;
}

export interface Meta {
    agency_withdraw_enabled: boolean;
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
    total_balance: string;
    total_frozen_balance: string;
}
