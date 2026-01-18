import { Select as AntdSelect } from "antd";
import type { SelectProps } from "antd";
import { useList } from "@refinedev/core";
import { Channel } from "@morgan-ustd/shared";

const useChannel = () => {
    const queryObserverResult = useList<Channel>({
        resource: "channels",
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
            value: record.code,
            label: record.name,
        })),
    };

    const Select = (props: SelectProps) => {
        return <AntdSelect {...selectProps} {...props} />;
    };

    return {
        channels: data?.data,
        isLoading,
        isError,
        isFetching,
        selectProps,
        Select,
    };
};

export default useChannel;
