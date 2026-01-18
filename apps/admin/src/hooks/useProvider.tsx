import { Select as AntdSelect } from "antd";
import type { SelectProps } from "antd";
import { useList } from "@refinedev/core";
import { MerchantProvider as Provider } from "@morgan-ustd/shared";

type Props = {
    valueField?: keyof Provider;
};

const useProvider = (props?: Props) => {
    const key = props?.valueField || "id";
    const queryObserverResult = useList<Provider>({
        resource: "providers",
        pagination: {
            mode: "off",
        },
    });

    const { data, isLoading, isError, isFetching, refetch } = queryObserverResult;

    const selectProps: SelectProps = {
        allowClear: true,
        showSearch: true,
        optionFilterProp: "label",
        options: queryObserverResult.data?.data.map((provider) => ({
            value: key === "id" ? provider.id : provider.username,
            label: provider.name,
            key: provider.id,
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
        selectProps,
        Select,
        refetch,
    };
};

export default useProvider;
