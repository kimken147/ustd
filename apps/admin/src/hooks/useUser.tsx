import { Select as AntdSelect } from "antd";
import type { SelectProps } from "antd";
import { CrudFilters, useList } from "@refinedev/core";
import { User } from "@morgan-ustd/shared";

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
    const { result } = useList<User>({
        resource: "users",
        pagination: {
            mode: "off",
        },
        filters,
    });

    const selectProps: SelectProps = {
        allowClear: true,
        showSearch: true,
        optionFilterProp: "label",
        options: result.data?.map((user: User) => ({
            value: user[valueField || "id"],
            label: `${user.name}`,
        })),
    };

    const Select = (selectComponentProps: SelectProps) => {
        return <AntdSelect {...selectProps} {...selectComponentProps} />;
    };

    return {
        users: result.data,
        Select,
    };
}

export default useUser;
