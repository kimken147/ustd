export interface Member {
    id: number;
    last_login_ipv4: any;
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
    last_login_at: any;
    withdraw_fee: string;
    withdraw_fee_percent: string;
    additional_withdraw_fee: string;
    agency_withdraw_fee: any;
    agency_withdraw_fee_dollar: string;
    additional_agency_withdraw_fee: string;
    withdraw_min_amount: any;
    withdraw_max_amount: any;
    withdraw_profit_min_amount: any;
    withdraw_profit_max_amount: any;
    agency_withdraw_min_amount: any;
    agency_withdraw_max_amount: any;
    wallet: Wallet;
    agent: Agent;
    user_channels: UserChannel[];
    phone: string;
    contact: string;
    google2fa_qrcode?: string;
    google2fa_secret?: string;
    password?: string;
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

export interface Wallet {
    balance: string;
    profit: string;
    frozen_balance: string;
    available_balance: string;
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
