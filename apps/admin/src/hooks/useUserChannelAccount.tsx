import { Select as AntdSelect } from "antd";
import type { SelectProps } from "antd";
import { CrudFilters, GetListResponse, useList } from "@refinedev/core";
import { ProviderUserChannel as UserChannel } from "@morgan-ustd/shared";

type Props = {
    filters: CrudFilters;
    queryOptions?: any;
};

function useUserChannelAccount(props?: Partial<Props>) {
    const queryObserverResult = useList<UserChannel>({
        resource: "user-channel-accounts",
        pagination: {
            mode: "off",
        },
        filters: props?.filters,
        queryOptions: props?.queryOptions,
    });
    const { data, isLoading, isError, isFetching, refetch } = queryObserverResult;

    const selectProps: SelectProps = {
        allowClear: true,
        showSearch: true,
        optionFilterProp: "label",
        options: queryObserverResult.data?.data.map((record) => ({
            value: record.id,
            label: record.name,
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
}

export default useUserChannelAccount;
