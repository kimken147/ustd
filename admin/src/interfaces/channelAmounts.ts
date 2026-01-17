export interface ChannelAmount {
    id: number;
    name: string;
    present_result: number;
    channel_code: string;
    deposit_account_fields?: DepositAccountFields;
    withdraw_account_fields: any;
}

export interface DepositAccountFields {
    fields?: Fields;
    auto_daifu_banks?: string[];
    auto_daiso_banks?: string[];
    user_can_deposit_banks?: any[];
    merchant_can_withdraw_banks?: string[];
    account?: string;
    qr_code?: string;
    bank_card_holder_name?: string;
    mobile?: string;
}

export interface Fields {
    account?: string;
    qr_code?: string;
    bank_card_holder_name?: string;
    mpin?: string;
    otp?: string;
    pin?: string;
    pwd?: string;
    bank_name?: string;
    bank_card_branch?: string;
    bank_card_number?: string;
    receiver_name?: string;
}
