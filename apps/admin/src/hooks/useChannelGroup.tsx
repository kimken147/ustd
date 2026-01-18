import { Select as AntdSelect } from "antd";
import type { SelectProps } from "antd";
import { useList } from "@refinedev/core";
import { UserChannelGroup as ChannelGroup } from "@morgan-ustd/shared";

const useChannelGroup = () => {
    const queryObserverResult = useList<ChannelGroup>({
        resource: "channel-groups",
        pagination: {
            mode: "off",
        },
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
