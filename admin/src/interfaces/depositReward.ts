export interface DepositReward {
    id: number;
    min_amount: string;
    max_amount: string;
    reward_amount: string;
    reward_unit: number;
    updated_at: string;
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
    matching_deposit_reward_feature: MatchingDepositRewardFeature;
}

export interface MatchingDepositRewardFeature {
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
