import React from 'react';
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

export interface UseSelectorResult<TData> {
  Select: (props: SelectProps) => React.ReactElement;
  data: TData[] | undefined;
  selectProps: SelectProps;
  isLoading: boolean;
  isFetching: boolean;
  isError: boolean;
  refetch: () => void;
}

export function useSelector<TData extends BaseRecord>(
  props?: UseSelectorProps<TData>
): UseSelectorResult<TData> {
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
    Select,
    data: result.data,
    selectProps,
    isLoading: query.isLoading,
    isFetching: query.isFetching,
    isError: query.isError,
    refetch: query.refetch,
  };
}

export default useSelector;
