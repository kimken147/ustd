export interface MerchantThirdChannel {
    id: number;
    name: string;
    username: string;
    include_self_providers: number;
    thirdChannelsList: ThirdChannelsList[];
}

export interface ThirdChannelsList {
    id: number;
    thirdchannel_id: number;
    name: string;
    class: string;
    channel_code: string;
    merchant_id: string;
    thirdChannel: string;
    deposit_fee_percent: string;
    withdraw_fee: string;
    daifu_fee_percent: string;
    daifu_min: string;
    daifu_max: string;
    deposit_min: string;
    deposit_max: string;
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
