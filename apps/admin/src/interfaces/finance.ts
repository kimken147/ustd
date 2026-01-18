export interface FinanceStatistic {
    id: number;
    parent_id?: number;
    name: string;
    username: string;
    stats: Stats;
}

export interface Stats {
    daiso: Daiso;
    xiafa: Xiafa;
    daifu: Daifu;
}

export interface Daiso {
    count: number;
    total_amount: number;
    total_fee: number;
    total_profit: number;
    system_profit: number;
}

export interface Xiafa {
    count: number;
    total_amount: number;
    total_fee: number;
    total_profit: number;
    system_profit: number;
}

export interface Daifu {
    count: number;
    total_amount: number;
    total_fee: number;
    total_profit: number;
    system_profit: number;
}
