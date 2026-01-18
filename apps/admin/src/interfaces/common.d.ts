declare interface Wallet {
    balance: string;
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

declare interface Profile {
    id: number;
    last_login_ipv4: string;
    role: number;
    status: number;
    agent_enable: boolean;
    google2fa_enable: boolean;
    deposit_enable: boolean;
    paufen_deposit_enable: boolean;
    withdraw_enable: boolean;
    paufen_withdraw_enable: boolean;
    transaction_enable: boolean;
    credit_mode_enable: boolean;
    deposit_mode_enable: boolean;
    ready_for_matching: boolean;
    account_mode: number;
    name: string;
    username: string;
    last_login_at: Date;
    withdraw_fee: string;
    wallet: Wallet;
    agent?: any;
    user_channels: UserChannel2[];
    phone?: any;
    contact?: any;
    permissions?: Permission[];
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

declare interface Permission {
    id: number;
    group_name: string;
    name: string;
}
