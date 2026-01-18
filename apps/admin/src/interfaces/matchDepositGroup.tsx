export interface DepositGroup {
    id: number;
    name: string;
    username: string;
    matching_deposit_groups: MatchingDepositGroup[];
}

export interface MatchingDepositGroup {
    id: number;
    provider_name: string;
    provider_username: string;
    personal_enable: boolean;
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
