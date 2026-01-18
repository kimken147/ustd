export interface SystemSetting {
    id: number;
    enabled: boolean;
    label: string;
    note: string;
    type: string;
    unit?: string;
    value: string;
    updated_at: string;
}
