import type { SelectProps } from "antd";
import { useList } from "@refinedev/core";
import { ChannelAmount } from "@morgan-ustd/shared";

const useChannelAmounts = () => {
    const { result, query } = useList<ChannelAmount>({
        resource: "channel-amounts",
        pagination: {
            mode: "off",
        },
    });

    const selectProps: SelectProps = {
        allowClear: true,
        showSearch: true,
        optionFilterProp: "label",
        options: result.data?.map((channel: ChannelAmount) => ({
            value: channel.id,
            label: channel.name,
        })),
    };

    return {
        ...query,
        data: result.data,
        selectProps,
    };
};

export default useChannelAmounts;
