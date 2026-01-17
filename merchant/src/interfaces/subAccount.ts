export interface SubAccount {
    id: number;
    last_login_ipv4?: string;
    role: number;
    name: string;
    username: string;
    last_login_at?: string;
    status: number;
    google2fa_enable: boolean;
    password?: string;
    google2fa_secret?: string;
    google2fa_qrcode?: string;
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
