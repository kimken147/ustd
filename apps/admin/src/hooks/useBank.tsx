import type { SelectProps } from "antd";
import { useList } from "@refinedev/core";
import { Bank } from "@morgan-ustd/shared";

const useBank = () => {
    const queryObserverResult = useList<Bank>({
        resource: "banks",
        pagination: {
            mode: "off",
        },
    });

    const { data, ...others } = queryObserverResult;

    const selectProps: SelectProps = {
        allowClear: true,
        showSearch: true,
        optionFilterProp: "label",
        options: queryObserverResult.data?.data.map((provider) => ({
            value: provider.id,
            label: provider.name,
        })),
    };

    return {
        ...others,
        data: data?.data,
        selectProps,
    };
};

export default useBank;
