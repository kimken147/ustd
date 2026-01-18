import { SelectProps, Select as AntdSelect } from "@pankod/refine-antd";
import { useList } from "@pankod/refine-core";
import { Channel } from "@morgan-ustd/shared";

const useChannel = () => {
    const queryObserverResult = useList<Channel>({
        resource: "channels",
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
