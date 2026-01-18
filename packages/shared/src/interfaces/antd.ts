// SelectProps options type - defined locally to avoid dependency on @pankod/refine-antd
export type SelectOption = {
    label?: React.ReactNode;
    value?: string | number | null;
    disabled?: boolean;
    key?: string;
    title?: string;
};

export type SelectOptions = SelectOption[];
