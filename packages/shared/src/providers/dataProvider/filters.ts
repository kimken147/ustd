export type CrudOperators = 'eq' | 'ne' | 'lt' | 'gt' | 'lte' | 'gte' | 'in' | 'nin' | 'contains' | 'ncontains' | 'between' | 'nbetween' | 'null' | 'nnull' | 'startswith' | 'nstartswith' | 'endswith' | 'nendswith' | 'or' | 'and';

export interface LogicalFilter {
  field: string;
  operator: CrudOperators;
  value: any;
}

export const mapOperator = (operator: CrudOperators): string => {
  switch (operator) {
    case 'ne':
    case 'gte':
    case 'lte':
      return `_${operator}`;
    case 'contains':
      return '_like';
    default:
      return '';
  }
};

export const generateFilter = (filters?: LogicalFilter[]): Record<string, string | string[]> => {
  const queryFilters: Record<string, string | string[]> = {};

  if (filters) {
    filters.forEach((filter) => {
      const mappedOperator = mapOperator('eq');
      if (Array.isArray(filter.value)) {
        filter.value.forEach((f: LogicalFilter) => {
          const field = `${f.field}${mappedOperator}`;
          if (!field) return;
          if (!queryFilters[field]) {
            queryFilters[field] = [];
          }
          (queryFilters[field] as string[]).push(f.value);
        });
      } else {
        queryFilters[`${filter.field}${mappedOperator}`] = filter.value;
      }
    });
  }

  return queryFilters;
};
