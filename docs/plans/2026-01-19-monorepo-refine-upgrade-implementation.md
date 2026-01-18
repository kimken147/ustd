# Monorepo 重構與 Refine v5 升級實作計劃

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** 將 admin 和 merchant 重構為 pnpm monorepo，抽取共用程式碼，並升級 Refine v3 到 v5。

**Architecture:** 使用 pnpm workspaces 管理 monorepo，共用程式碼放在 `packages/shared`，應用程式放在 `apps/`。dataProvider 和 authProvider 需要適配 Laravel API 格式和 Refine v5 介面。

**Tech Stack:** pnpm workspaces, React 18, Refine v5, Ant Design v5, TypeScript

**Design Document:** `docs/plans/2026-01-18-monorepo-refine-upgrade-design.md`

---

## Phase 1: 建立 Monorepo 結構

### Task 1.1: 初始化 pnpm workspace

**Files:**
- Create: `pnpm-workspace.yaml`
- Create: `package.json` (root)
- Create: `.npmrc`

**Step 1: 建立 pnpm-workspace.yaml**

```yaml
packages:
  - 'apps/*'
  - 'packages/*'
```

**Step 2: 建立根目錄 package.json**

```json
{
  "name": "morgan-ustd",
  "private": true,
  "scripts": {
    "dev:admin": "pnpm --filter @morgan-ustd/admin local",
    "dev:merchant": "pnpm --filter @morgan-ustd/merchant local",
    "build:admin": "pnpm --filter @morgan-ustd/admin build",
    "build:merchant": "pnpm --filter @morgan-ustd/merchant build",
    "build:shared": "pnpm --filter @morgan-ustd/shared build",
    "build": "pnpm -r build",
    "lint": "pnpm -r lint",
    "test": "pnpm -r test"
  },
  "engines": {
    "node": ">=18",
    "pnpm": ">=8"
  }
}
```

**Step 3: 建立 .npmrc**

```
shamefully-hoist=true
strict-peer-dependencies=false
```

**Step 4: Commit**

```bash
git add pnpm-workspace.yaml package.json .npmrc
git commit -m "chore: initialize pnpm workspace configuration"
```

---

### Task 1.2: 移動 admin 到 apps/admin

**Files:**
- Move: `admin/` → `apps/admin/`
- Modify: `apps/admin/package.json`

**Step 1: 建立 apps 目錄並移動 admin**

```bash
mkdir -p apps
mv admin apps/
```

**Step 2: 更新 apps/admin/package.json**

將 `name` 改為 `@morgan-ustd/admin`：

```json
{
  "name": "@morgan-ustd/admin",
  "version": "0.1.0",
  "private": true,
  ...
}
```

**Step 3: Commit**

```bash
git add -A
git commit -m "chore: move admin to apps/admin"
```

---

### Task 1.3: 移動 merchant 到 apps/merchant

**Files:**
- Move: `merchant/` → `apps/merchant/`
- Modify: `apps/merchant/package.json`

**Step 1: 移動 merchant**

```bash
mv merchant apps/
```

**Step 2: 更新 apps/merchant/package.json**

將 `name` 改為 `@morgan-ustd/merchant`：

```json
{
  "name": "@morgan-ustd/merchant",
  "version": "0.1.0",
  "private": true,
  ...
}
```

**Step 3: Commit**

```bash
git add -A
git commit -m "chore: move merchant to apps/merchant"
```

---

### Task 1.4: 建立 shared package 結構

**Files:**
- Create: `packages/shared/package.json`
- Create: `packages/shared/tsconfig.json`
- Create: `packages/shared/src/index.ts`

**Step 1: 建立目錄結構**

```bash
mkdir -p packages/shared/src/{interfaces,lib,providers,i18n}
```

**Step 2: 建立 packages/shared/package.json**

```json
{
  "name": "@morgan-ustd/shared",
  "version": "0.1.0",
  "private": true,
  "main": "src/index.ts",
  "types": "src/index.ts",
  "scripts": {
    "lint": "eslint src --ext .ts,.tsx",
    "typecheck": "tsc --noEmit"
  },
  "peerDependencies": {
    "react": "^18.2.0",
    "react-dom": "^18.2.0"
  },
  "devDependencies": {
    "@types/react": "^18.0.15",
    "@types/react-dom": "^18.0.6",
    "typescript": "^5.0.0"
  }
}
```

**Step 3: 建立 packages/shared/tsconfig.json**

```json
{
  "compilerOptions": {
    "target": "ES2020",
    "lib": ["ES2020", "DOM", "DOM.Iterable"],
    "module": "ESNext",
    "moduleResolution": "bundler",
    "jsx": "react-jsx",
    "strict": true,
    "esModuleInterop": true,
    "skipLibCheck": true,
    "forceConsistentCasingInFileNames": true,
    "declaration": true,
    "declarationMap": true,
    "composite": true,
    "outDir": "dist",
    "rootDir": "src"
  },
  "include": ["src/**/*"],
  "exclude": ["node_modules", "dist"]
}
```

**Step 4: 建立 packages/shared/src/index.ts**

```typescript
// Interfaces
export * from './interfaces';

// Lib utilities
export * from './lib';

// Providers
export * from './providers';

// i18n
export * from './i18n';
```

**Step 5: 建立空的 barrel exports**

建立 `packages/shared/src/interfaces/index.ts`:
```typescript
// Will export interfaces here
export {};
```

建立 `packages/shared/src/lib/index.ts`:
```typescript
// Will export utilities here
export {};
```

建立 `packages/shared/src/providers/index.ts`:
```typescript
// Will export providers here
export {};
```

建立 `packages/shared/src/i18n/index.ts`:
```typescript
// Will export i18n config here
export {};
```

**Step 6: Commit**

```bash
git add -A
git commit -m "chore: create shared package structure"
```

---

### Task 1.5: 驗證 pnpm workspace 結構

**Step 1: 安裝依賴**

```bash
pnpm install
```

**Step 2: 驗證 workspace 結構**

```bash
pnpm ls -r --depth 0
```

Expected: 顯示 @morgan-ustd/admin, @morgan-ustd/merchant, @morgan-ustd/shared

**Step 3: 添加 shared 依賴到 apps**

在 `apps/admin/package.json` 添加：
```json
{
  "dependencies": {
    "@morgan-ustd/shared": "workspace:*"
  }
}
```

在 `apps/merchant/package.json` 添加：
```json
{
  "dependencies": {
    "@morgan-ustd/shared": "workspace:*"
  }
}
```

**Step 4: 重新安裝依賴**

```bash
pnpm install
```

**Step 5: Commit**

```bash
git add -A
git commit -m "chore: link shared package to apps"
```

---

## Phase 2: 抽取共用程式碼

### Task 2.1: 抽取 interfaces

**Files:**
- Copy: `apps/admin/src/interfaces/*.ts` → `packages/shared/src/interfaces/`
- Modify: `packages/shared/src/interfaces/index.ts`

**Step 1: 複製共用的 interface 檔案**

從 admin 複製以下檔案到 `packages/shared/src/interfaces/`：
- `antd.ts`
- `bank.ts`
- `channel.ts`
- `channelAmounts.ts`
- `channelGroup.ts`
- `common.d.ts`
- `merchant.ts`
- `merchantWallet.ts`
- `transaction.ts`
- `user.ts`
- `userChannel.ts`
- `withdraw.ts`

```bash
cp apps/admin/src/interfaces/{antd,bank,channel,channelAmounts,channelGroup,merchant,merchantWallet,transaction,user,userChannel,withdraw}.ts packages/shared/src/interfaces/
cp apps/admin/src/interfaces/common.d.ts packages/shared/src/interfaces/
```

**Step 2: 更新 packages/shared/src/interfaces/index.ts**

```typescript
export * from './antd';
export * from './bank';
export * from './channel';
export * from './channelAmounts';
export * from './channelGroup';
export * from './merchant';
export * from './merchantWallet';
export * from './transaction';
export * from './user';
export * from './userChannel';
export * from './withdraw';
```

**Step 3: 驗證 TypeScript 編譯**

```bash
cd packages/shared && pnpm typecheck
```

**Step 4: Commit**

```bash
git add -A
git commit -m "feat(shared): extract common interfaces"
```

---

### Task 2.2: 抽取 lib 工具函數

**Files:**
- Copy: `apps/admin/src/lib/*.ts` → `packages/shared/src/lib/`
- Modify: `packages/shared/src/lib/index.ts`
- Modify: `packages/shared/package.json` (添加依賴)

**Step 1: 複製 lib 檔案**

```bash
cp apps/admin/src/lib/{date,color,number,channel,resouce}.ts packages/shared/src/lib/
```

注意：`number.tsx` 在 admin 是 `.tsx`，需要檢查是否有 JSX，如果有需要保留 `.tsx`

**Step 2: 添加必要的依賴到 shared**

更新 `packages/shared/package.json`：

```json
{
  "dependencies": {
    "dayjs": "^1.11.7",
    "numeral": "^2.0.6"
  },
  "devDependencies": {
    "@types/numeral": "^2.0.2"
  }
}
```

**Step 3: 更新 packages/shared/src/lib/index.ts**

```typescript
export * from './date';
export * from './color';
export * from './number';
export * from './channel';
export * from './resouce';
```

**Step 4: 安裝依賴並驗證**

```bash
pnpm install
cd packages/shared && pnpm typecheck
```

**Step 5: Commit**

```bash
git add -A
git commit -m "feat(shared): extract lib utilities"
```

---

### Task 2.3: 抽取 dataProvider

**Files:**
- Create: `packages/shared/src/providers/dataProvider/index.ts`
- Create: `packages/shared/src/providers/dataProvider/axiosInstance.ts`
- Create: `packages/shared/src/providers/dataProvider/filters.ts`
- Create: `packages/shared/src/providers/dataProvider/types.ts`
- Modify: `packages/shared/package.json` (添加依賴)

**Step 1: 添加依賴到 shared**

更新 `packages/shared/package.json`：

```json
{
  "dependencies": {
    "axios": "^1.2.2",
    "dayjs": "^1.11.7",
    "numeral": "^2.0.6",
    "query-string": "^8.1.0"
  }
}
```

**Step 2: 建立 types.ts**

`packages/shared/src/providers/dataProvider/types.ts`:

```typescript
export interface IRes<T = any> {
  data: T;
  meta?: {
    total: number;
    current_page: number;
    per_page: number;
    last_page: number;
  };
}

export interface DataProviderConfig {
  apiUrl: string;
  getLocale?: () => string;
}
```

**Step 3: 建立 axiosInstance.ts**

`packages/shared/src/providers/dataProvider/axiosInstance.ts`:

```typescript
import axios, { AxiosInstance } from 'axios';

export const createAxiosInstance = (getLocale?: () => string): AxiosInstance => {
  const instance = axios.create();

  // 設置預設 headers
  const defaultHeaders = {
    accept: 'application/json, text/plain, */*',
    'content-type': 'application/json;charset=UTF-8',
  };

  instance.defaults.headers.get = { ...defaultHeaders };
  instance.defaults.headers.post = { ...defaultHeaders };
  instance.defaults.headers.put = { ...defaultHeaders };
  instance.defaults.headers.delete = { ...defaultHeaders };

  // 設置 locale interceptor
  if (getLocale) {
    instance.interceptors.request.use(
      (config) => {
        const locale = getLocale();
        const backendLocale = locale.replace('-', '_');
        config.headers = config.headers || {};
        config.headers['X-Locale'] = backendLocale;
        return config;
      },
      (error) => Promise.reject(error)
    );
  }

  return instance;
};
```

**Step 4: 建立 filters.ts**

`packages/shared/src/providers/dataProvider/filters.ts`:

```typescript
import { CrudOperators, LogicalFilter } from '@refinedev/core';

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
```

**Step 5: 建立主要 dataProvider**

`packages/shared/src/providers/dataProvider/index.ts`:

```typescript
import { DataProvider } from '@refinedev/core';
import { AxiosInstance } from 'axios';
import { stringify } from 'query-string';
import { createAxiosInstance } from './axiosInstance';
import { generateFilter } from './filters';
import { IRes, DataProviderConfig } from './types';

export { createAxiosInstance } from './axiosInstance';
export { generateFilter, mapOperator } from './filters';
export type { IRes, DataProviderConfig } from './types';

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

      const queryFilters = generateFilter(filters as any);
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
        const filterQuery = generateFilter(filters as any);
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
```

**Step 6: 更新 providers/index.ts**

```typescript
export * from './dataProvider';
```

**Step 7: 安裝依賴並驗證**

```bash
pnpm install
cd packages/shared && pnpm typecheck
```

**Step 8: Commit**

```bash
git add -A
git commit -m "feat(shared): extract and refactor dataProvider for Refine v5"
```

---

### Task 2.4: 更新 admin 使用 shared

**Files:**
- Modify: `apps/admin/src/dataProvider.ts`
- Modify: 所有 import interfaces 的檔案

**Step 1: 更新 admin dataProvider**

`apps/admin/src/dataProvider.ts`:

```typescript
import { createDataProvider, createAxiosInstance } from '@morgan-ustd/shared';
import i18n from './i18n';

const axiosInstance = createAxiosInstance(() => i18n.language || 'zh-CN');

const dataProvider = createDataProvider(
  { apiUrl: process.env.REACT_APP_API_URL || '' },
  axiosInstance
);

export default dataProvider;
export { axiosInstance };
```

**Step 2: 更新 interface imports**

使用搜尋替換，將：
```typescript
import { XXX } from '../interfaces/xxx';
// 或
import { XXX } from './interfaces/xxx';
```

替換為：
```typescript
import { XXX } from '@morgan-ustd/shared';
```

**Step 3: 更新 lib imports**

將 lib 的 import 也改為從 shared 引入。

**Step 4: 驗證 admin 可以啟動**

```bash
pnpm dev:admin
```

**Step 5: Commit**

```bash
git add -A
git commit -m "refactor(admin): use shared package for interfaces and dataProvider"
```

---

### Task 2.5: 更新 merchant 使用 shared

**Files:**
- Modify: `apps/merchant/src/dataProvider.ts`
- Modify: 所有 import interfaces 的檔案

**Step 1-4: 同 Task 2.4**

對 merchant 執行相同的操作。

**Step 5: 驗證 merchant 可以啟動**

```bash
pnpm dev:merchant
```

**Step 6: Commit**

```bash
git add -A
git commit -m "refactor(merchant): use shared package for interfaces and dataProvider"
```

---

## Phase 3: Refine v3 → v5 升級

### Task 3.1: 更新 shared 依賴為 Refine v5

**Files:**
- Modify: `packages/shared/package.json`

**Step 1: 更新 package.json**

```json
{
  "dependencies": {
    "@refinedev/core": "^4.47.0",
    "axios": "^1.2.2",
    "dayjs": "^1.11.7",
    "numeral": "^2.0.6",
    "query-string": "^8.1.0"
  }
}
```

**Step 2: 安裝依賴**

```bash
pnpm install
```

**Step 3: 驗證 shared 編譯通過**

```bash
cd packages/shared && pnpm typecheck
```

**Step 4: Commit**

```bash
git add -A
git commit -m "chore(shared): upgrade to Refine v5 dependencies"
```

---

### Task 3.2: 升級 admin 依賴

**Files:**
- Modify: `apps/admin/package.json`

**Step 1: 移除舊的 Refine 依賴，添加新的**

從：
```json
{
  "@pankod/refine-antd": "^4.1.1",
  "@pankod/refine-cli": "^1.2.0",
  "@pankod/refine-core": "^3.18.0",
  "@pankod/refine-inferencer": "^1.2.0",
  "@pankod/refine-react-router-v6": "^3.18.0",
  "@pankod/refine-simple-rest": "^3.18.0"
}
```

改為：
```json
{
  "@refinedev/antd": "^5.37.0",
  "@refinedev/cli": "^2.16.0",
  "@refinedev/core": "^4.47.0",
  "@refinedev/react-router-v6": "^4.5.0",
  "antd": "^5.12.0"
}
```

**Step 2: 安裝依賴**

```bash
cd apps/admin && pnpm install
```

**Step 3: Commit**

```bash
git add -A
git commit -m "chore(admin): upgrade to Refine v5 and Ant Design v5"
```

---

### Task 3.3: 更新 admin App.tsx 和路由

**Files:**
- Modify: `apps/admin/src/App.tsx`

**Step 1: 更新 imports**

將：
```typescript
import { Refine } from "@pankod/refine-core";
import routerProvider from "@pankod/refine-react-router-v6";
```

改為：
```typescript
import { Refine } from "@refinedev/core";
import routerBindings, { NavigateToResource, UnsavedChangesNotifier } from "@refinedev/react-router-v6";
import { BrowserRouter, Routes, Route, Outlet } from "react-router-dom";
```

**Step 2: 更新 Refine 元件結構**

參考 Refine v5 的新路由結構，使用 `<BrowserRouter>` 和 `<Routes>` 包裹。

**Step 3: 逐頁修復 breaking changes**

根據錯誤訊息逐一修復。

**Step 4: 驗證可以啟動**

```bash
pnpm dev:admin
```

**Step 5: Commit**

```bash
git add -A
git commit -m "refactor(admin): migrate to Refine v5 router structure"
```

---

### Task 3.4: 升級 merchant（同 Task 3.2-3.3）

對 merchant 執行相同的升級步驟。

---

### Task 3.5: 移除自訂封裝，使用 Refine 官方方式

**Files:**
- Modify: 所有使用自訂 useTable 的頁面
- Delete: 不需要的自訂 hooks

**Step 1: 移除自訂 table 封裝**

將：
```typescript
import { useTable } from '../hooks/useTable';
```

改為使用 Refine 官方：
```typescript
import { useTable } from '@refinedev/antd';
```

**Step 2: 更新使用方式**

Refine v5 的 `useTable` 回傳結構不同，需要調整使用方式。

**Step 3: 逐頁測試**

**Step 4: Commit**

```bash
git add -A
git commit -m "refactor: remove custom table wrapper, use Refine useTable directly"
```

---

## Phase 4: 清理和驗證

### Task 4.1: 清理未使用的檔案

**Step 1: 刪除 apps 中已抽取到 shared 的重複檔案**

```bash
rm -rf apps/admin/src/interfaces
rm -rf apps/merchant/src/interfaces
rm -rf apps/admin/src/lib
rm -rf apps/merchant/src/lib
```

**Step 2: 驗證兩個 app 都能正常運行**

```bash
pnpm dev:admin
pnpm dev:merchant
```

**Step 3: Commit**

```bash
git add -A
git commit -m "chore: remove duplicated files migrated to shared package"
```

---

### Task 4.2: 最終驗證

**Step 1: 執行所有 build**

```bash
pnpm build
```

**Step 2: 檢查是否有 TypeScript 錯誤**

```bash
pnpm -r typecheck
```

**Step 3: 手動測試主要功能**

- 登入/登出
- 列表頁面分頁
- 新增/編輯/刪除
- 篩選功能

---

## 完成後的目錄結構

```
ustd/
├── api/                          # Laravel API（不變）
├── apps/
│   ├── admin/                    # @morgan-ustd/admin (Refine v5)
│   └── merchant/                 # @morgan-ustd/merchant (Refine v5)
├── packages/
│   └── shared/                   # @morgan-ustd/shared
│       └── src/
│           ├── interfaces/
│           ├── lib/
│           ├── providers/
│           └── i18n/
├── docs/
│   └── plans/
├── package.json
├── pnpm-workspace.yaml
└── .npmrc
```
