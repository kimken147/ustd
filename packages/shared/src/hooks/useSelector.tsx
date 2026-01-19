import { Select as AntdSelect } from 'antd';
import type { SelectProps } from 'antd';
import { BaseRecord, CrudFilters, useList } from '@refinedev/core';

export type UseSelectorProps<TData> = {
  valueField?: keyof TData;
  labelField?: keyof TData;
  resource: string;
  filters?: CrudFilters;
  labelRender?: (record: TData) => string;
};

export function useSelector<TData extends BaseRecord>(
  props?: UseSelectorProps<TData>
) {
  const { result, query } = useList<TData>({
    resource: props?.resource || '',
    pagination: {
      mode: 'off',
    },
    filters: props?.filters,
  });

  const selectProps: SelectProps = {
    showSearch: true,
    optionFilterProp: 'label',
    options: result.data?.map((record: TData) => ({
      value: record[props?.valueField || 'id'],
      label:
        props?.labelRender?.(record) ?? record[props?.labelField || 'name'],
    })),
  };

  const Select = (selectComponentProps: SelectProps) => {
    return <AntdSelect {...selectProps} {...selectComponentProps} />;
  };

  return {
    ...query,
    Select,
    data: result.data,
    selectProps,
  };
}

export default useSelector;
