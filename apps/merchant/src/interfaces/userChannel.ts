import { BaseRecord } from "@refinedev/core";

export interface Agent {
    id: number;
    name: string;
    username: string;
}

export interface UserOfChannel {
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
    last_login_at: Date;
    balance_limit: string;
    agent: Agent;
    message_enabled: boolean;
    phone: string;
    contact: string;
    usdt_rate: string;
    tags?: any;
    cancel_order_enable: boolean;
    exchange_mode_enable: boolean;
    control_downline: boolean;
    created_at: Date;
}

export interface Detail {
    mpin: string;
    account: string;
    sync_at?: Date;
    sync_status: string;
    account_status: string;
    bank_name: string;
    otp: string;
    pwd: string;
    bank_card_branch?: any;
    bank_card_number: string;
    bank_card_holder_name: string;
}

export interface Device {
    id: number;
    name: string;
    last_heartbeat_at?: any;
}

export interface UserChannel extends BaseRecord {
    id: number;
    name: string;
    hash_id: string;
    user: UserOfChannel;
    channel_code: string;
    channel_name: string;
    account: string;
    account_name: string;
    bank_name: string;
    detail: Detail;
    bank_branch: string;
    status: number;
    type: number;
    device: Device;
    present_result: number;
    time_limit_disabled: boolean;
    created_at: Date;
    deleted_at?: any;
    daily_status: boolean;
    daily_limit: string;
    daily_limit_value: string;
    daily_total: string;
    withdraw_daily_limit: string;
    withdraw_daily_total: string;
    monthly_status: boolean;
    monthly_limit: string;
    monthly_limit_value: string;
    monthly_total: string;
    withdraw_monthly_limit: string;
    withdraw_monthly_total: string;
    user_channel_account_daily_limit_enabled: boolean;
    user_channel_account_daily_limit_value: string;
    user_channel_account_monthly_limit_enabled: boolean;
    user_channel_account_monthly_limit_value: string;
    record_user_channeL_account_balance: boolean;
    balance: string;
    balance_limit: string;
    is_auto: boolean;
}

export interface Links {
    first: string;
    last: string;
    prev?: any;
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
    late_night_bank_limit_feature_enabled: boolean;
    user_channel_account_daily_limit_enabled: boolean;
    user_channel_account_daily_limit_value: string;
    user_channel_account_monthly_limit_enabled: boolean;
    user_channel_account_monthly_limit_value: string;
    record_user_channeL_account_balance: boolean;
    total_balance: string;
}

export interface IUserChannelRes {
    data: UserChannel[];
    meta: Meta;
}

export enum UserChannelType {
    收出款 = 1,
    收款 = 2,
    出款 = 3,
}

export enum UserChannelStatus {
    強制下線 = 0,
    下線 = 1,
    上線 = 2,
}

export interface Channel {
    code: string;
    name: string;
    status: number;
    order_timeout: number;
    order_timeout_enable: boolean;
    transaction_timeout: number;
    transaction_timeout_enable: boolean;
    floating: string;
    floating_enable: boolean;
    note_type?: number;
    note_enable: boolean;
    channel_groups: ChannelGroup[];
    real_name_enable: boolean;
    deposit_account_fields?: DepositAccountFields;
    withdraw_account_fields: any;
}

export interface ChannelGroup {
    id: number;
    name: string;
    fixed_amount: boolean;
    amount_description: string;
}

export interface DepositAccountFields {
    fields?: Fields;
    auto_daifu_banks?: string[];
    auto_daiso_banks?: string[];
    user_can_deposit_banks?: any[];
    merchant_can_withdraw_banks?: string[];
    mobile?: string;
    account?: string;
    qr_code?: string;
    bank_card_holder_name?: string;
}

export interface Fields {
    bank_name?: string;
    bank_card_branch?: string;
    bank_card_number?: string;
    bank_card_holder_name?: string;
    otp?: string;
    pin?: string;
    pwd?: string;
    account?: string;
    mpin?: string;
    qr_code?: string;
    receiver_name?: string;
}

export interface IUserChannelQuery {
    name_or_username: string;
    agent_name_or_username: string;
    "channel_code[]": string[];
    "type[]": string[];
    "status[]": string[];
    "bank[]": string[];
    "name[]": string[];
}
