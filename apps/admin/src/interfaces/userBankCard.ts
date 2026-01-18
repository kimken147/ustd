export interface UserBankCard {
    id: number;
    status: number;
    user: User;
    bank_card_holder_name: string;
    bank_card_number: string;
    bank_name: string;
    bank_province?: string;
    bank_city?: string;
    created_at: string;
}

export interface User {
    id: number;
    role: number;
    name: string;
    username: string;
    last_login_at: string;
    last_login_ipv4: string;
}

export interface Links {
    first: string;
    last: string;
    prev: any;
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
}
