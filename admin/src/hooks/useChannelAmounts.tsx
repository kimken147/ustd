import { SelectProps } from "@pankod/refine-antd";
import { useList } from "@pankod/refine-core";
import { ChannelAmount } from "interfaces/channelAmounts";

const useChannelAmounts = () => {
    const queryObserverResult = useList<ChannelAmount>({
        resource: "channel-amounts",
        config: {
            hasPagination: false,
        },
    });

    const { data, ...others } = queryObserverResult;

    const selectProps: SelectProps = {
        allowClear: true,
        showSearch: true,
        optionFilterProp: "label",
        options: queryObserverResult.data?.data.map((channel) => ({
            value: channel.id,
            label: channel.name,
        })),
    };

    return {
        ...others,
        data: data?.data,
        selectProps,
    };
};

export default useChannelAmounts;
