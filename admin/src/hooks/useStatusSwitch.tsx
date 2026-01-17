import { SelectProps, Select as AntdSelect } from "@pankod/refine-antd";

function useStatusSwitch() {
    const statusSelectProps: SelectProps = {
        className: "w-full",
        options: [0, 1].map((status) => ({
            label: status ? "啟用" : "禁用",
            value: status,
        })),
        allowClear: true,
    };

    const StatusSelect = (props: SelectProps) => {
        return <AntdSelect {...statusSelectProps} {...props} />;
    };

    return {
        StatusSelect,
    };
}

export default useStatusSwitch;
