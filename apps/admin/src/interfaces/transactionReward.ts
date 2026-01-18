export interface TransactionReward {
    [key: string]: Detail[];
}

export interface Detail {
    id: number;
    min_amount: string;
    max_amount: string;
    reward_amount: string;
    reward_unit: number;
    started_at: string;
    ended_at: string;
    updated_at: string;
    created_at: string;
    timeRange?: string;
}

export interface Meta {
    transaction_reward_feature: TransactionRewardFeature;
}

export interface TransactionRewardFeature {
    id: number;
    hidden: number;
    enabled: boolean;
    input: Input;
    created_at: string;
    updated_at: string;
}

export interface Input {
    type: string;
    value: string;
}
