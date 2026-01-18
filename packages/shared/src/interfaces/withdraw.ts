export interface Withdraw {
    id: number;
    is_parent: boolean;
    is_child: boolean;
    separated: boolean;
    type: number;
    sub_type: number;
    user: User;
    system_order_number: string;
    order_number: string;
    amount: string;
    usdt: string;
    status: number;
    notify_status: number;
    created_at: string;
    confirmed_at?: string;
    notified_at: any;
    provider?: Provider;
    to_channel_account?: ToChannelAccount;
    to_channel_account_hash_id?: string;
    actual_amount: string;
    floating_amount: string;
    bank_card_holder_name: string;
    bank_name: string;
    bank_province: string;
    bank_city: string;
    bank_card_number: string;
    merchant_fees: MerchantFee[];
    provider_fees: ProviderFee[];
    system_profit: string;
    parent: any;
    children: Withdraw[];
    siblings: any[];
    all_relative_withdraws: AllRelativeWithdraw[];
    notify_url: any;
    note?: string;
    notes: Note[];
    note_exist: boolean;
    locked: boolean;
    locked_at?: string;
    locked_by?: LockedBy2;
    lockable: boolean;
    unlockable: boolean;
    confirmable: boolean;
    failable: boolean;
    paufenable: boolean;
    separatable: boolean;
    thirdchannel?: Thirdchannel2;
    certificate_file_path: any;
    certificate_files: CertificateFile[];
    _search1?: string;
}
export interface Provider {
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
    phone?: string;
    contact?: string;
    usdt_rate: string;
    tags: any;
    cancel_order_enable: boolean;
    exchange_mode_enable: boolean;
    control_downline: boolean;
    note?: string;
}
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
    phone: string;
    contact: string;
    usdt_rate: string;
    tags: string[];
    cancel_order_enable: boolean;
    exchange_mode_enable: boolean;
    control_downline: boolean;
    created_at: string;
}

export interface MerchantFee {
    merchant: Merchant;
    fee: string;
    profit: string;
    actual_fee: string;
    actual_profit: string;
}

export interface Merchant {
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
    phone: string;
    contact: string;
    usdt_rate: string;
    tags: string[];
    cancel_order_enable: boolean;
    exchange_mode_enable: boolean;
    control_downline: boolean;
    created_at: string;
}

export interface AllRelativeWithdraw {
    id: number;
    is_parent: boolean;
    is_child: boolean;
    type: number;
    sub_type: number;
    system_order_number: string;
    order_number: string;
    amount: string;
    usdt: string;
    status: number;
    notify_status: number;
    created_at: string;
    confirmed_at?: string;
    notified_at: any;
    actual_amount: string;
    floating_amount: string;
    bank_card_holder_name: string;
    bank_name: string;
    bank_province: string;
    bank_city: string;
    bank_card_number: string;
    all_relative_withdraws: any[];
    notify_url: any;
    note: any;
    note_exist: boolean;
    locked: boolean;
    locked_at: any;
    locked_by: any;
    lockable: boolean;
    unlockable: boolean;
    confirmable: boolean;
    failable: boolean;
    paufenable: boolean;
    separatable: boolean;
    thirdchannel?: Thirdchannel;
    certificate_file_path: any;
    certificate_files: any[];
    _search1: any;
}

export interface Thirdchannel {
    id: number;
    name: string;
    class: string;
    type: number;
    channel_code: string;
    status: number;
    sync: number;
    custom_url: string;
    balance: string;
    notify_balance: string;
    auto_daifu_threshold: string;
    proxy: any;
    merchant_id: string;
    key: string;
    key2: any;
    key3: any;
    created_at: string;
    updated_at: string;
    white_ip: string;
}

export interface Note {
    id: number;
    user: any;
    note: string;
    created_at: string;
}

export interface Thirdchannel2 {
    id: number;
    name: string;
    class: string;
    type: number;
    channel_code: string;
    status: number;
    sync: number;
    custom_url: string;
    balance: string;
    notify_balance: string;
    auto_daifu_threshold: string;
    proxy: any;
    merchant_id: string;
    key: string;
    key2: any;
    key3: any;
    created_at: string;
    updated_at: string;
    white_ip: string;
}

export interface LockedBy2 {
    id: number;
    name: string;
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
    has_new_withdraws: boolean;
    total_amount: string;
    total_fee: string;
    total_profit: string;
    third_channel_fee: string;
    banned_realnames: string[];
}

export interface TransactionNote {
    id: number;
    user: User;
    note: string;
    created_at: string;
}

export interface ToChannelAccount {
    id: number;
    name: string;
    user_id: number;
    channel_code: string;
    channel_amount_id: number;
    device_id: number;
    wallet_id: number;
    bank_id: number;
    status: number;
    type: number;
    is_auto: boolean;
    balance: string;
    balance_limit: string;
    regular_customer_first: boolean;
    time_limit_disabled: boolean;
    min_amount: any;
    max_amount: any;
    fee_percent: string;
    daily_status: boolean;
    daily_limit?: string;
    daily_total: string;
    withdraw_daily_limit?: string;
    withdraw_daily_total: string;
    monthly_status: boolean;
    monthly_limit?: string;
    monthly_total: string;
    withdraw_monthly_limit?: string;
    withdraw_monthly_total: string;
    account: string;
    detail: Detail;
    note: string;
    created_at: string;
    updated_at: string;
    deleted_at: any;
    last_matched_at?: string;
    device: Device;
}

export interface Detail {
    mpin: string;
    account: string;
    sync_status: string;
    sync_at?: string;
    account_status?: string;
    balance_diff?: string;
}

export interface Device {
    id: number;
    user_id: number;
    regular_customer_first: boolean;
    last_login_ipv4: any;
    name: string;
    last_login_at: any;
    last_heartbeat_at: any;
    created_at: string;
    updated_at: string;
    deleted_at: any;
}

export interface ProviderFee {
    provider: Provider2;
    fee: string;
    profit: string;
    actual_fee: string;
    actual_profit: string;
}

export interface Provider2 {
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
    phone?: string;
    contact?: string;
    usdt_rate: string;
    tags: any;
    cancel_order_enable: boolean;
    exchange_mode_enable: boolean;
    control_downline: boolean;
    created_at: string;
}

export interface CertificateFile {
    id: number;
    path: string;
    url: string;
    created_at: string;
    updated_at: string;
}
