export interface Channel {
    code: string;
    name: string;
    status: number;
    order_timeout: number;
    order_timeout_enable: boolean;
    transaction_timeout: number;
    transaction_timeout_enable: boolean;
    floating: string;
    floating_enable: boolean;
    note_type?: number;
    note_enable: boolean;
    channel_groups: ChannelGroup[];
    real_name_enable: boolean;
    deposit_account_fields?: DepositAccountFields;
    withdraw_account_fields: any;
}

export interface ChannelGroup {
    id: number;
    name: string;
    fixed_amount: boolean;
    amount_description: string;
}

export interface DepositAccountFields {
    fields?: Fields;
    auto_daifu_banks?: string[];
    auto_daiso_banks?: string[];
    user_can_deposit_banks?: any[];
    merchant_can_withdraw_banks?: string[];
    mobile?: string;
    account?: string;
    qr_code?: string;
    bank_card_holder_name?: string;
}

export interface Fields {
    bank_name?: string;
    bank_card_branch?: string;
    bank_card_number?: string;
    bank_card_holder_name?: string;
    otp?: string;
    pin?: string;
    pwd?: string;
    account?: string;
    mpin?: string;
    qr_code?: string;
    receiver_name?: string;
}
