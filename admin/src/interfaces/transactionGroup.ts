export interface MatchTransactionGroup {
    id: number;
    name: string;
    username: string;
    transaction_groups: TransactionGroup[];
}

export interface TransactionGroup {
    id: number;
    provider_name: string;
    provider_username: string;
    personal_enable: boolean;
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
