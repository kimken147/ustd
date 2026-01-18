import { CrudOperators, DataProvider, LogicalFilter } from "@refinedev/core";
import { axiosInstance, generateSort, stringify } from "@refinedev/simple-rest";
import i18n from "./i18n";

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

export const generateFilter = (filters?: any[]) => {
    const queryFilters: Record<string, string | string[]> = {};
    if (filters) {
        filters.forEach((filter) => {
            const mappedOperator = mapOperator("eq");
            if (Array.isArray(filter.value)) {
                filter.value.forEach((filter: LogicalFilter) => {
                    const field = `${filter.field}${mappedOperator}`;
                    if (!field) return;
                    if (!queryFilters[field]) {
                        queryFilters[field] = [];
                    }
                    (queryFilters[field] as String[]).push(filter.value);
                });
            } else {
                queryFilters[`${filter.field}${mappedOperator}`] = filter.value;
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
    create: async ({ resource, variables, metaData }) => {
        const url = `${apiUrl}/${resource}`;
        const headers = metaData?.headers ?? {};
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
    getList: async ({ resource, hasPagination, pagination, filters, sort, metaData }) => {
        if (metaData?.url?.includes("undefined")) {
            return {
                data: [],
                total: 0,
            };
        }
        const url = metaData?.url ? metaData?.url : `${apiUrl}/${resource}`;
        const query = hasPagination
            ? {
                  page: pagination?.current,
                  per_page: pagination?.pageSize ?? 20,
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
    custom: async ({ url, method, filters, sort, payload, query, headers }) => {
        let requestUrl = `${url}?`;

        if (sort) {
            const generatedSort = generateSort(sort);
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
            };
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
