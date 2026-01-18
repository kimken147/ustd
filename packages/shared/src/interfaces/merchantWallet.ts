export interface MerchantWallet {
    id: number;
    user: User;
    operator: Operator;
    type: number;
    balance_delta: string;
    profit_delta: string;
    frozen_balance_delta: string;
    balance_result: string;
    profit_result: string;
    frozen_balance_result: string;
    note: string;
    created_at: string;
}

export interface User {
    id: number;
    role: number;
    name: string;
    username: string;
}

export interface Operator {
    id: number;
    role: number;
    name: string;
    username: string;
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
    total_increased_balance_delta: string;
    total_decreased_balance_delta: string;
}
