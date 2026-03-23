import type { CrudFilter } from '@refinedev/core';
import dayjs from 'dayjs';

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
    // Skip internal nonce field
    if (field === '_t') continue;

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
