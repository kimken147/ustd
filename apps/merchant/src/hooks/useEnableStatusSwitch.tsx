import { SelectProps, Select as AntdSelect, SwitchProps, Switch as AntdSwitch } from "antd";

function useEnableStatusSelect() {
    const statusSelectProps: SelectProps = {
        className: "w-full",
        options: [0, 1].map((status) => ({
            label: status ? "啟用" : "禁用",
            value: status,
        })),
        allowClear: true,
    };

    const Select = (props: SelectProps) => {
        return <AntdSelect {...statusSelectProps} {...props} />;
    };

    const Switch = (props: SwitchProps) => {
        return <AntdSwitch checkedChildren="啟用" unCheckedChildren="停用" {...props} />;
    };

    return {
        Select,
        Switch,
    };
}

export default useEnableStatusSelect;
