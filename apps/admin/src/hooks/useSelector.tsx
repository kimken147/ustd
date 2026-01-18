import { Select as AntdSelect } from "antd";
import type { SelectProps } from "antd";
import { BaseRecord, CrudFilters, useList } from "@refinedev/core";

type Props<TData> = {
    valueField?: keyof TData;
    labelField?: keyof TData;
    resource: string;
    filters?: CrudFilters;
    labelRender?: (record: TData) => string;
};

function useSelector<TData extends BaseRecord>(props?: Props<TData>) {
    const queryObserverResult = useList<TData>({
        resource: props?.resource || "",
        pagination: {
            mode: "off",
        },
        filters: props?.filters,
    });

    const { data, ...others } = queryObserverResult;

    const selectProps: SelectProps = {
        showSearch: true,
        optionFilterProp: "label",
        options: queryObserverResult.data?.data.map((record) => ({
            value: record[props?.valueField || "id"],
            label: props?.labelRender?.(record) ?? record[props?.labelField || "name"],
        })),
    };

    const Select = (props: SelectProps) => {
        return <AntdSelect {...selectProps} {...props} />;
    };

    return {
        ...others,
        Select,
        data: data?.data,
        selectProps,
    };
}

export default useSelector;
