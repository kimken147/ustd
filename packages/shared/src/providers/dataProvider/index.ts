import { AxiosInstance } from 'axios';
import queryString from 'query-string';
import { createAxiosInstance } from './axiosInstance';
import { generateFilter, LogicalFilter } from './filters';
import { IRes, DataProviderConfig } from './types';

const { stringify } = queryString;

export { createAxiosInstance } from './axiosInstance';
export { generateFilter, mapOperator } from './filters';
export type { IRes, DataProviderConfig } from './types';
export type { CrudOperators, LogicalFilter } from './filters';

// DataProvider interface (compatible with Refine)
export interface DataProvider {
  getList: (params: {
    resource: string;
    pagination?: { current?: number; pageSize?: number; mode?: 'server' | 'client' | 'off' };
    filters?: LogicalFilter[];
    sorters?: any[];
    meta?: Record<string, any>;
  }) => Promise<{ data: any[]; total: number }>;

  getOne: (params: { resource: string; id: string | number }) => Promise<{ data: any }>;

  create: (params: { resource: string; variables: any; meta?: Record<string, any> }) => Promise<{ data: any }>;

  update: (params: { resource: string; id: string | number; variables: any }) => Promise<{ data: any }>;

  deleteOne: (params: { resource: string; id: string | number; variables?: any }) => Promise<{ data: any }>;

  getApiUrl: () => string;

  custom?: (params: {
    url: string;
    method: 'get' | 'post' | 'put' | 'patch' | 'delete';
    filters?: LogicalFilter[];
    payload?: any;
    query?: Record<string, any>;
    headers?: Record<string, string>;
  }) => Promise<{ data: any }>;
}

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

      const queryFilters = generateFilter(filters);
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
        const filterQuery = generateFilter(filters);
        requestUrl = `${requestUrl}&${stringify(filterQuery)}`;
      }

      if (query) {
        requestUrl = `${requestUrl}&${stringify(query)}`;
      }

      if (headers) {
        client.defaults.headers = {
          ...client.defaults.headers,
          ...headers,
        };
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
