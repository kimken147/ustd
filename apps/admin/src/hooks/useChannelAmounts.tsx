import type { SelectProps } from "antd";
import { useList } from "@refinedev/core";
import { ChannelAmount } from "@morgan-ustd/shared";

const useChannelAmounts = () => {
    const queryObserverResult = useList<ChannelAmount>({
        resource: "channel-amounts",
        pagination: {
            mode: "off",
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
