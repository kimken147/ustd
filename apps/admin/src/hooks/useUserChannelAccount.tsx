import { SelectProps, Select as AntdSelect } from "@pankod/refine-antd";
import { CrudFilters, GetListResponse, useList, UseQueryOptions } from "@pankod/refine-core";
import { ProviderUserChannel as UserChannel } from "@morgan-ustd/shared";

type Props = {
    filters: CrudFilters;
    queryOptions: UseQueryOptions<GetListResponse<UserChannel>, any>;
};

function useUserChannelAccount(props?: Partial<Props>) {
    const queryObserverResult = useList<UserChannel>({
        resource: "user-channel-accounts",
        config: {
            hasPagination: false,
            filters: props?.filters,
        },
        queryOptions: {
            refetchOnWindowFocus: false,
            refetchOnMount: false,
            ...props?.queryOptions,
        },
        liveMode: "off",
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
