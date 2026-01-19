import { Select as AntdSelect } from "antd";
import type { SelectProps } from "antd";
import { useList } from "@refinedev/core";
import { Channel } from "@morgan-ustd/shared";

const useChannel = () => {
    const { result, query } = useList<Channel>({
        resource: "channels",
        pagination: {
            mode: "off",
        },
    });

    const { isLoading, isError, isFetching } = query;

    const selectProps: SelectProps = {
        allowClear: true,
        showSearch: true,
        optionFilterProp: "label",
        options: result.data?.map((record: Channel) => ({
            value: record.code,
            label: record.name,
        })),
    };

    const Select = (props: SelectProps) => {
        return <AntdSelect {...selectProps} {...props} />;
    };

    return {
        channels: result.data,
        isLoading,
        isError,
        isFetching,
        selectProps,
        Select,
    };
};

export default useChannel;
