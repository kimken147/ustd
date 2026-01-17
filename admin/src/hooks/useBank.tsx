import { SelectProps } from "@pankod/refine-antd";
import { useList } from "@pankod/refine-core";
import { Bank } from "interfaces/bank";

const useBank = () => {
    const queryObserverResult = useList<Bank>({
        resource: "banks",
        config: {
            hasPagination: false,
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
