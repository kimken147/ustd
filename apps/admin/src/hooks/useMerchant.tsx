import { SelectProps, Select as AntdSelect } from "@pankod/refine-antd";
import { useList } from "@pankod/refine-core";
import { Merchant } from "@morgan-ustd/shared";

type Props = {
    valueField: keyof Merchant;
};

function useMerchant(props?: Props) {
    const field = props?.valueField || "id";
    const queryObserverResult = useList<Merchant>({
        resource: "merchants",
        config: {
            hasPagination: false,
        },
        queryOptions: {
            refetchOnWindowFocus: false,
            refetchOnMount: false,
        },
        liveMode: "off",
    });

    const { data, isLoading, isError, isFetching } = queryObserverResult;

    const selectProps: SelectProps = {
        allowClear: true,
        showSearch: true,
        optionFilterProp: "label",
        options: queryObserverResult.data?.data.map((merchant) => ({
            value: merchant[field],
            label: `${merchant.username}(${merchant.name})`,
        })),
    };

    const Select = (props: SelectProps) => {
        return <AntdSelect {...selectProps} {...props} />;
    };

    return {
        data: data?.data,
        isLoading,
        isError,
        isFetching,
        Select,
    };
}

export default useMerchant;
