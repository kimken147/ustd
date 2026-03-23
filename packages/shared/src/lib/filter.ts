import type { CrudFilter } from '@refinedev/core';
import dayjs from 'dayjs';

/**
 * getValueProps for Select fields with numeric option values.
 *
 * syncWithLocation restores URL params as strings ("7"), but Select options
 * may use numeric values (7). This helper coerces string→number so the
 * Select can match and display the correct label after page refresh.
 *
 * Only use on fields whose Select options have numeric values.
 * Do NOT use on fields with string values that look like numbers (e.g. account names).
 *
 * @example
 * <ListPageLayout.Filter.Item name="status[]" getValueProps={numericFilterValueProps}>
 *   <StatusSelect mode="multiple" />
 * </ListPageLayout.Filter.Item>
 */
export function numericFilterValueProps(value: unknown) {
  if (Array.isArray(value)) {
    return {
      value: value.map(v => {
        if (typeof v === 'string' && v.trim() !== '') {
          const n = Number(v);
          if (!isNaN(n)) return n;
        }
        return v;
      }),
    };
  }
  if (typeof value === 'string' && value.trim() !== '') {
    const n = Number(value);
    if (!isNaN(n)) return { value: n };
  }
  return { value };
}

/**
 * 通用表單值轉 CrudFilter[] 函數
 *
 * 將表單的 key-value 自動轉為 Refine 的 CrudFilter 陣列。
 * 空值欄位會以 value: undefined 推入，讓 Refine merge 模式覆蓋舊 filter。
 * Dayjs 物件自動格式化為 ISO 字串。
 *
 * @example
 * // 在 useTable 中使用:
 * useTable({
 *   onSearch: formValuesToCrudFilters,
 * })
 */
export function formValuesToCrudFilters(values: unknown): CrudFilter[] {
  const filters: CrudFilter[] = [];

  if (!values || typeof values !== 'object') return filters;

  for (const [field, value] of Object.entries(values as Record<string, any>)) {
    // NOTE: Do NOT skip _t here. The _t nonce must enter the CrudFilter
    // array so React Query detects a change on every submit. The _t field
    // is skipped later in generateFilter() (dataProvider.ts) to keep it
    // out of the actual API request URL.

    // Empty values: push with value undefined so Refine's merge
    // replaces any previous filter for this field (instead of keeping it).
    if (value === null || value === undefined || value === '') {
      filters.push({ field, operator: 'eq', value: undefined });
      continue;
    }

    let filterValue: any;

    if (dayjs.isDayjs(value)) {
      filterValue = value.format('YYYY-MM-DDTHH:mm:ss');
    } else {
      filterValue = value;
    }

    filters.push({
      field,
      operator: 'eq',
      value: filterValue,
    });
  }

  return filters;
}
