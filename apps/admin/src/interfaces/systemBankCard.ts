export interface SystemBankCard {
    id: number;
    status: number;
    balance: string;
    balance_text: string;
    bank_card_holder_name: string;
    bank_card_number: string;
    bank_name: string;
    bank_province?: string;
    bank_city?: string;
    created_at: string;
    updated_at: string;
    published_at: any;
    last_matched_at: any;
    users: User[];
    note: string;
}

export interface User {
    id: number;
    name: string;
    share_descendants: boolean;
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
