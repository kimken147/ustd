import type { CrudFilter } from '@refinedev/core';
import dayjs from 'dayjs';

/**
 * 通用表單值轉 CrudFilter[] 函數
 *
 * 將表單的 key-value 自動轉為 Refine 的 CrudFilter 陣列。
 * 跳過 null / undefined / 空字串的欄位。
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
    if (value === null || value === undefined || value === '') continue;

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
