export interface FormValues {
    provider: string;
    channel_amount_id: string;
    is_auto: number;
    bank_card_number?: string;
    qr_code?: any[];
    bank_card_holder_name?: string;
    bank_name?: string;
    bank_card_branch?: string;
    single_min_limit?: number;
    single_max_limit?: number;
    note?: string;
}
