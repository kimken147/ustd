import { Select as AntdSelect } from "antd";
import type { SelectProps } from "antd";
import { useList } from "@refinedev/core";
import { UserChannelGroup as ChannelGroup } from "@morgan-ustd/shared";

const useChannelGroup = () => {
    const { result, query } = useList<ChannelGroup>({
        resource: "channel-groups",
        pagination: {
            mode: "off",
        },
    });

    const { isLoading, isError, isFetching } = query;

    const selectProps: SelectProps = {
        allowClear: true,
        showSearch: true,
        optionFilterProp: "label",
        options: result.data?.map((record: ChannelGroup) => ({
            value: record.id,
            label: record.name,
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
        selectProps,
        Select,
    };
};

export default useChannelGroup;
