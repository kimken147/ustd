export interface WalletHistory {
    id: number;
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
    wallet_balance_total: string;
}
