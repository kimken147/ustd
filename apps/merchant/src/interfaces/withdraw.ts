import { User } from "./user";

export interface Withdraw {
    id: number;
    sub_type: number;
    system_order_number: string;
    order_number: string;
    amount: string;
    usdt: string;
    fee: string;
    merchant: Merchant;
    status: number;
    notify_status: number;
    notes: Note[];
    bank_card_holder_name: string;
    bank_name: string;
    bank_province: string;
    bank_city: string;
    bank_card_number: string;
    created_at: string;
    confirmed_at: any;
    notified_at: any;
    notify_url: any;
    _search1: any;
}

export interface Merchant {
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
    phone: any;
    contact: any;
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
    balance: string;
    total_amount: string;
    total_fee: string;
    thirdchannel_balance: string;
}

export interface Note {
    id: number;
    note: string;
    created_at: string;
}

export interface TransactionNote {
    id: number;
    user: User;
    note: string;
    created_at: string;
}
