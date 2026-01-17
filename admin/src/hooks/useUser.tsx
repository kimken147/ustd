import { SelectProps, Select as AntdSelect } from "@pankod/refine-antd";
import { CrudFilters, useList } from "@pankod/refine-core";
import { User } from "interfaces/user";

type Props = {
    role: number;
    agent_enable?: boolean;
    valueField?: keyof User;
};

function useUser({ role, agent_enable, valueField }: Props) {
    const filters: CrudFilters = [
        {
            field: "role",
            value: role,
            operator: "eq",
        },
        {
            field: "agent_enable",
            value: agent_enable ? 1 : 0,
            operator: "eq",
        },
    ];
    const { data } = useList<User>({
        resource: "users",
        config: {
            hasPagination: false,
            filters,
        },
    });

    const selectProps: SelectProps = {
        allowClear: true,
        showSearch: true,
        optionFilterProp: "label",
        options: data?.data.map((user) => ({
            value: user[valueField || "id"],
            label: `${user.name}`,
        })),
    };

    const Select = (props: SelectProps) => {
        return <AntdSelect {...selectProps} {...props} />;
    };

    return {
        users: data?.data,
        Select,
    };
}

export default useUser;
