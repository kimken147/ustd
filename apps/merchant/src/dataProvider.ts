import { CrudOperators, DataProvider, LogicalFilter } from "@refinedev/core";
import { axiosInstance, generateSort } from "@refinedev/simple-rest";
import dayjs from "dayjs";
import i18n from "./i18n";

/**
 * URL-safe query string serializer.
 * PHP's parse_str treats `+` as space. `encodeURIComponent` encodes `+` as `%2B`,
 * ensuring date values like `2026-03-05T00:00:00+08:00` survive the round-trip.
 */
const stringify = (params: Record<string, any>): string => {
    const parts: string[] = [];
    for (const [key, value] of Object.entries(params)) {
        if (value == null) continue;
        if (Array.isArray(value)) {
            for (const v of value) {
                parts.push(`${key}=${encodeURIComponent(v)}`);
            }
        } else {
            parts.push(`${key}=${encodeURIComponent(value)}`);
        }
    }
    return parts.join('&');
};

// 將前端語言格式轉換為後端格式 (zh-CN -> zh_CN)
const convertLocaleToBackendFormat = (locale: string): string => {
    return locale.replace("-", "_");
};

// 設置 axios interceptor，自動在每個請求中添加 X-Locale header
axiosInstance.interceptors.request.use(
    (config) => {
        const currentLocale = i18n.language || "zh-CN";
        const backendLocale = convertLocaleToBackendFormat(currentLocale);
        config.headers = config.headers || {};
        config.headers["X-Locale"] = backendLocale;
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

axiosInstance.defaults.headers.get.accept = "application/json, text/plain, */*";
axiosInstance.defaults.headers.get["content-type"] = "application/json;charset=UTF-8";
axiosInstance.defaults.headers.post.accept = "application/json, text/plain, */*";
axiosInstance.defaults.headers.put.accept = "application/json, text/plain, */*";
axiosInstance.defaults.headers.put["content-type"] = "application/json;charset=UTF-8";
axiosInstance.defaults.headers.put.accept = "application/json, text/plain, */*";
axiosInstance.defaults.headers.put["content-type"] = "application/json;charset=UTF-8";
axiosInstance.defaults.headers.delete.accept = "application/json, text/plain, */*";
axiosInstance.defaults.headers.delete["content-type"] = "application/json;charset=UTF-8";

/**
 * Serialize filter values for API requests.
 *
 * Handles the qs.parse `+` → space corruption:
 * Refine's syncWithLocation uses qs.stringify({encode:false}) → URL has literal `+`.
 * On refresh, qs.parse replaces `+` with space (its default decoder behavior).
 * So "2026-03-05T00:00:00+08:00" becomes "2026-03-05T00:00:00 08:00".
 *
 * This function:
 * 1. dayjs objects → format() (includes timezone like +08:00)
 * 2. Strings with corrupted timezone (space) → restore `+`
 * 3. Strings without timezone → parse with dayjs and re-format (adds timezone)
 */
const serializeValue = (value: any): string => {
    if (dayjs.isDayjs(value)) {
        return value.format();
    }
    if (typeof value === 'string') {
        // Fix timezone corrupted by qs.parse: "...T00:00:00 08:00" → "...T00:00:00+08:00"
        const fixed = value.replace(/(T\d{2}:\d{2}:\d{2}) (\d{2}:\d{2})$/, '$1+$2');
        if (fixed !== value) return fixed;

        // Date string without timezone → parse and re-format to add timezone
        if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/.test(value)) {
            const d = dayjs(value);
            if (d.isValid()) return d.format();
        }
    }
    return value;
};

export const generateFilter = (filters?: any[]) => {
    const queryFilters: Record<string, string | string[]> = {};
    if (filters) {
        filters.forEach((filter) => {
            // Skip the cache-busting nonce injected by ListPageLayout.Filter
            if (filter.field === '_t') return;
            // Skip filters with empty values (cleared by user)
            if (filter.value == null || filter.value === '') return;
            const mappedOperator = mapOperator("eq");
            if (Array.isArray(filter.value)) {
                filter.value.forEach((filter: LogicalFilter) => {
                    const field = `${filter.field}${mappedOperator}`;
                    if (!field) return;
                    if (!queryFilters[field]) {
                        queryFilters[field] = [];
                    }
                    (queryFilters[field] as String[]).push(serializeValue(filter.value));
                });
            } else {
                queryFilters[`${filter.field}${mappedOperator}`] = serializeValue(filter.value);
            }
        });
    }

    return queryFilters;
};

const mapOperator = (operator: CrudOperators) => {
    switch (operator) {
        case "ne":
        case "gte":
        case "lte":
            return `_${operator}`;
        case "contains":
            return "_like";
    }

    return ""; // default "eq"
};

const dataProvider = (apiUrl: string, httpClient = axiosInstance): DataProvider => ({
    create: async ({ resource, variables, meta }) => {
        const url = `${apiUrl}/${resource}`;
        const headers = meta?.headers ?? {};
        if (!headers["Content-Type"]) {
            headers["Content-Type"] = "application/json;charset=UTF-8";
        }

        const { data } = await httpClient.post<IRes>(url, variables, {
            headers,
        });

        return {
            data: data.data ?? data,
        };
    },
    getList: async ({ resource, pagination, filters, sorters, meta }) => {
        if (meta?.url?.includes("undefined")) {
            return {
                data: [],
                total: 0,
            };
        }
        const url = meta?.url ? meta?.url : `${apiUrl}/${resource}`;
        const hasPagination = pagination?.mode !== "off";
        const paginationConfig = pagination as { current?: number; pageSize?: number } | undefined;
        const query = hasPagination
            ? {
                  page: paginationConfig?.current,
                  per_page: paginationConfig?.pageSize ?? 20,
              }
            : {
                  no_paginate: 1,
              };
        const queryFilters = generateFilter(filters);
        const { data } = await httpClient.get<IRes>(`${url}?${stringify(query)}&${stringify(queryFilters)}`);

        return {
            data: data.data ?? data,
            total: hasPagination ? data.meta.total : 0,
            meta: data.meta,
        };
    },
    getOne: async ({ resource, id }) => {
        const url = `${apiUrl}/${resource}/${id}`;

        const { data } = await httpClient.get<IRes>(url);

        return {
            data: data.data,
        };
    },
    deleteOne: async ({ resource, id, variables }) => {
        const url = `${apiUrl}/${resource}/${id}`;

        const { data } = await httpClient.delete<IRes>(url, {
            data: variables,
        });

        return {
            data: data.data,
        };
    },
    update: async ({ resource, id, variables }) => {
        const url = `${apiUrl}/${resource}/${id}`;

        const { data } = await httpClient.put(url, variables);

        return {
            data,
        };
    },
    getApiUrl: () => apiUrl,
    custom: async ({ url, method, filters, sorters, payload, query, headers }) => {
        let requestUrl = `${url}?`;

        if (sorters) {
            const generatedSort = generateSort(sorters);
            if (generatedSort) {
                const { _sort, _order } = generatedSort;
                const sortQuery = {
                    _sort: _sort.join(","),
                    _order: _order.join(","),
                };
                requestUrl = `${requestUrl}&${stringify(sortQuery)}`;
            }
        }

        if (filters) {
            const filterQuery = generateFilter(filters);
            requestUrl = `${requestUrl}&${stringify(filterQuery)}`;
        }

        if (query) {
            requestUrl = `${requestUrl}&${stringify(query)}`;
        }

        if (headers) {
            httpClient.defaults.headers = {
                ...httpClient.defaults.headers,
                ...headers,
            } as typeof httpClient.defaults.headers;
        }

        let axiosResponse;
        switch (method) {
            case "put":
            case "post":
            case "patch":
                axiosResponse = await httpClient[method](url, payload);
                break;
            case "delete":
                axiosResponse = await httpClient.delete(url, {
                    data: payload,
                });
                break;
            default:
                axiosResponse = await httpClient.get(requestUrl);
                break;
        }

        const { data } = axiosResponse;

        return Promise.resolve({ data: data.data ?? data });
    },
});

export default dataProvider;
