import type { SelectProps } from "antd";
import { useList } from "@refinedev/core";
import { Bank } from "@morgan-ustd/shared";

const useBank = () => {
    const { result, query } = useList<Bank>({
        resource: "banks",
        pagination: {
            mode: "off",
        },
    });

    const selectProps: SelectProps = {
        allowClear: true,
        showSearch: true,
        optionFilterProp: "label",
        options: result.data?.map((provider: Bank) => ({
            value: provider.id,
            label: provider.name,
        })),
    };

    return {
        ...query,
        data: result.data,
        selectProps,
    };
};

export default useBank;
