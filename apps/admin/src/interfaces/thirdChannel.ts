export interface ThirdChannel {
    id: number;
    name: string;
    thirdChannel: string;
    class: string;
    status: number;
    custom_url: string;
    white_ip?: string;
    channel: string;
    type: number;
    auto_daifu_threshold: string;
    merchant_id?: string;
    balance: string;
    notify_balance: string;
    key?: string;
    key2?: string;
    key3?: string;
    created_at?: string;
    updated_at: string;
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
