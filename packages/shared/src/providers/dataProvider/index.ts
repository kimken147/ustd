import type { AxiosInstance } from 'axios';
import type { DataProvider, CrudFilters, LogicalFilter } from '@refinedev/core';
import queryString from 'query-string';
import { createAxiosInstance } from './axiosInstance';
import { generateFilter } from './filters';
import { IRes, DataProviderConfig } from './types';

const { stringify } = queryString;

export { createAxiosInstance } from './axiosInstance';
export { generateFilter, mapOperator } from './filters';
export type { IRes, DataProviderConfig } from './types';
export type { CrudOperators, LogicalFilter, CrudFilters, ConditionalFilter } from './filters';

export const createDataProvider = (
  config: DataProviderConfig,
  httpClient?: AxiosInstance
): DataProvider => {
  const { apiUrl, getLocale } = config;
  const client = httpClient || createAxiosInstance(getLocale);

  return {
    getList: async ({ resource, pagination, filters, sorters, meta }) => {
      if (meta?.url?.includes('undefined')) {
        return { data: [], total: 0 };
      }

      const url = meta?.url || `${apiUrl}/${resource}`;

      const query = pagination?.mode === 'off'
        ? { no_paginate: 1 }
        : {
            page: pagination?.current || 1,
            per_page: pagination?.pageSize || 20,
          };

      // Cast filters to LogicalFilter[] for our generateFilter function
      const logicalFilters = filters as LogicalFilter[] | undefined;
      const queryFilters = generateFilter(logicalFilters);
      const { data } = await client.get<IRes>(`${url}?${stringify(query)}&${stringify(queryFilters)}`);

      return {
        data: data.data ?? data,
        total: data.meta?.total ?? 0,
      };
    },

    getOne: async ({ resource, id }) => {
      const url = `${apiUrl}/${resource}/${id}`;
      const { data } = await client.get<IRes>(url);
      return { data: data.data };
    },

    create: async ({ resource, variables, meta }) => {
      const url = `${apiUrl}/${resource}`;
      const headers = meta?.headers ?? {};
      if (!headers['Content-Type']) {
        headers['Content-Type'] = 'application/json;charset=UTF-8';
      }
      const { data } = await client.post<IRes>(url, variables, { headers });
      return { data: data.data ?? data };
    },

    update: async ({ resource, id, variables }) => {
      const url = `${apiUrl}/${resource}/${id}`;
      const { data } = await client.put(url, variables);
      return { data };
    },

    deleteOne: async ({ resource, id, variables }) => {
      const url = `${apiUrl}/${resource}/${id}`;
      const { data } = await client.delete<IRes>(url, { data: variables });
      return { data: data.data };
    },

    getApiUrl: () => apiUrl,

    custom: async ({ url, method, filters, payload, query, headers }) => {
      let requestUrl = `${url}?`;

      if (filters) {
        // Cast filters to LogicalFilter[] for our generateFilter function
        const logicalFilters = filters as LogicalFilter[];
        const filterQuery = generateFilter(logicalFilters);
        requestUrl = `${requestUrl}&${stringify(filterQuery)}`;
      }

      if (query) {
        requestUrl = `${requestUrl}&${stringify(query as Record<string, unknown>)}`;
      }

      if (headers) {
        Object.entries(headers).forEach(([key, value]) => {
          client.defaults.headers.common[key] = value as string;
        });
      }

      let axiosResponse;
      switch (method) {
        case 'put':
        case 'post':
        case 'patch':
          axiosResponse = await client[method](url, payload);
          break;
        case 'delete':
          axiosResponse = await client.delete(url, { data: payload });
          break;
        default:
          axiosResponse = await client.get(requestUrl);
          break;
      }

      const { data } = axiosResponse;
      return { data: data.data ?? data };
    },
  };
};
