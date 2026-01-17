export interface BankCard {
    id: number;
    status: number;
    bank_card_holder_name: string;
    bank_card_number: string;
    bank_name: string;
    bank_province?: string;
    bank_city?: string;
    created_at: string;
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
}
