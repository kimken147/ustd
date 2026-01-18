import { Select as AntdSelect, SelectProps } from "antd";

type Options = NonNullable<SelectProps["options"]>;
type Option = Options[0];
type Props = {
    status: Record<string, number>;
};

function useStatus({ status }: Props) {
    const getStatusText = (input: number) => {
        return Object.entries(status).find(([key, value]) => value === input)?.[0];
    };

    const Select = (props: SelectProps) => {
        return (
            <AntdSelect
                options={Object.entries(status).map<Option>(([key, value]) => ({
                    label: key,
                    value,
                }))}
                allowClear
                {...props}
            />
        );
    };

    return {
        Select,
        getStatusText,
        status,
    };
}

export default useStatus;
