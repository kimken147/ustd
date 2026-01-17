export interface SystemSetting {
    id: number;
    enabled: boolean;
    label: string;
    note: string;
    type: string;
    unit?: string;
    value: string | null;
    updated_at: string;
}
