import { Select as AntdSelect } from "antd";
import type { SelectProps } from "antd";
import { CrudFilters, useList } from "@refinedev/core";
import { ProviderUserChannel as UserChannel } from "@morgan-ustd/shared";

type Props = {
    filters: CrudFilters;
    queryOptions?: any;
};

function useUserChannelAccount(props?: Partial<Props>) {
    const { result, query } = useList<UserChannel>({
        resource: "user-channel-accounts",
        pagination: {
            mode: "off",
        },
        filters: props?.filters,
        queryOptions: props?.queryOptions,
    });
    const { isLoading, isError, isFetching, refetch } = query;

    const selectProps: SelectProps = {
        allowClear: true,
        showSearch: true,
        optionFilterProp: "label",
        options: result.data?.map((record: UserChannel) => ({
            value: record.id,
            label: record.name,
        })),
    };

    const Select = (selectComponentProps: SelectProps) => {
        return <AntdSelect {...selectProps} {...selectComponentProps} />;
    };

    return {
        data: result.data,
        isLoading,
        isError,
        isFetching,
        selectProps,
        Select,
        refetch,
    };
}

export default useUserChannelAccount;
