import { useTable as useRefineTable } from '@refinedev/antd';
import type { BaseRecord, HttpError } from '@refinedev/core';

const DEFAULT_PAGE_SIZE = 20;

/**
 * Wrapper around Refine's useTable with default pageSize=20.
 */
export function useTable<
  TQueryFnData extends BaseRecord = BaseRecord,
  TError extends HttpError = HttpError,
  TSearchVariables = unknown,
  TData extends BaseRecord = TQueryFnData,
>(
  props: Parameters<typeof useRefineTable<TQueryFnData, TError, TSearchVariables, TData>>[0] = {},
) {
  return useRefineTable<TQueryFnData, TError, TSearchVariables, TData>({
    ...props,
    pagination: {
      pageSize: DEFAULT_PAGE_SIZE,
      ...props.pagination,
    },
  });
}
