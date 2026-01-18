declare interface Wallet {
    balance: string;
    profit: string;
    frozen_balance: string;
    available_balance: string;
}

declare interface UserChannel2 {
    id: number;
    name: string;
    status: number;
    min_amount?: any;
    max_amount?: any;
    fee_percent: string;
    floating_enable: boolean;
}

interface Profile {
    id: number;
    last_login_ipv4: string;
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
    last_login_at: string;
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
    agency_withdraw_min_amount: string;
    agency_withdraw_max_amount: any;
    wallet: Wallet;
    agent: Agent;
    user_channels: UserChannel[];
    phone: any;
    contact: any;
    google2fa_secret?: string;
    google2fa_qrcode?: string;
    parent?: Profile;
}

declare interface IPreLoginRes {
    google2fa_enable: boolean;
}
declare interface ILoginRes extends IRes<{ access_token: string; token_type: string; expires_in: number }> {}
declare interface IProfileRes
    extends IRes<
        Profile,
        {
            today_self_total_profit: string;
            yesterday_self_total_profit: string;
            today_descendants_total_profit: string;
            yesterday_descendants_total_profit: string;
        }
    > {}

declare interface IChangePasswordReq {
    old_password: string;
    new_password: string;
    one_time_password: string;
}

declare interface IUpdateUserChannel {
    id: number;
    name: string;
    provider_id: number;
    channel_amount_id: string;
    account: string;
    mpin: number;
    balance: number | string;
    balance_limit: number;
    monthly_status: boolean;
    monthly_limit: number;
    monthly_total: number;
    monthly_limit_null: boolean;
    daily_status: boolean;
    daily_limit: number | string;
    daily_total: number;
    daily_limit_null: boolean;
    withdraw_daily_limit: number;
    withdraw_daily_total: number;
    daily_withdraw_limit_null: boolean;
    withdraw_monthly_limit: number;
    withdraw_monthly_total: number;
    monthly_withdraw_limit_null: boolean;
    type: number;
    status: number;
    is_auto: boolean;
}

declare type NamePath = string | number | (string | number)[];
