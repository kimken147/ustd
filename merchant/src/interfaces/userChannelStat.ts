export interface UserChannelStat {
    total: number;
    withdraw_orders: number;
    channels: Channel[];
}

export interface Channel {
    channel_name: string;
    paying: number;
}
