import { SelectProps, Select as AntdSelect } from "@pankod/refine-antd";
import { useList } from "@pankod/refine-core";
import { UserChannelGroup as ChannelGroup } from "@morgan-ustd/shared";

const useChannelGroup = () => {
    const queryObserverResult = useList<ChannelGroup>({
        resource: "channel-groups",
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
    };
};

export default useChannelGroup;
