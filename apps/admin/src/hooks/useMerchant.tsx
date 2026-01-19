import { Select as AntdSelect } from "antd";
import type { SelectProps } from "antd";
import { useList } from "@refinedev/core";
import { Merchant } from "@morgan-ustd/shared";

type Props = {
    valueField: keyof Merchant;
};

function useMerchant(props?: Props) {
    const field = props?.valueField || "id";
    const { result, query } = useList<Merchant>({
        resource: "merchants",
        pagination: {
            mode: "off",
        },
    });

    const { isLoading, isError, isFetching } = query;

    const selectProps: SelectProps = {
        allowClear: true,
        showSearch: true,
        optionFilterProp: "label",
        options: result.data?.map((merchant: Merchant) => ({
            value: merchant[field],
            label: `${merchant.username}(${merchant.name})`,
        })),
    };

    const Select = (props: SelectProps) => {
        return <AntdSelect {...selectProps} {...props} />;
    };

    return {
        data: result.data,
        isLoading,
        isError,
        isFetching,
        Select,
    };
}

export default useMerchant;
