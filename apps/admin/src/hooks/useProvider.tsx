import { SelectProps, Select as AntdSelect } from "@pankod/refine-antd";
import { useList } from "@pankod/refine-core";
import { Provider } from "interfaces/merchant";

type Props = {
    valueField?: keyof Provider;
};

const useProvider = (props?: Props) => {
    const key = props?.valueField || "id";
    const queryObserverResult = useList<Provider>({
        resource: "providers",
        config: {
            hasPagination: false,
        },
        queryOptions: {
            refetchOnWindowFocus: false,
            refetchOnMount: false,
        },
        liveMode: "off",
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
