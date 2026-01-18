# Monorepo 重構與 Refine v5 升級設計

## 概述

將 admin 和 merchant 前端專案重構為 pnpm monorepo 架構，並從 Refine v3 升級至 v5。

## 決策摘要

| 項目 | 決策 |
|------|------|
| 重構順序 | 先 monorepo，再升級 Refine |
| API 是否納入 | 否，保留在同一 repo 但不納入 pnpm workspace |
| Monorepo 工具 | pnpm workspaces |
| 共用 package 策略 | 單一 `@morgan-ustd/shared` |
| 命名空間 | `@morgan-ustd/*` |
| 自訂元件原則 | 優先使用 Refine 官方元件，避免過度封裝 |

## 目錄結構

```
ustd/
├── api/                          # Laravel API（不納入 pnpm workspace）
├── apps/
│   ├── admin/                    # @morgan-ustd/admin
│   │   ├── src/
│   │   ├── package.json
│   │   └── ...
│   └── merchant/                 # @morgan-ustd/merchant
│       ├── src/
│       ├── package.json
│       └── ...
├── packages/
│   └── shared/                   # @morgan-ustd/shared
│       ├── src/
│       │   ├── interfaces/       # TypeScript 型別定義
│       │   ├── lib/              # 工具函數（date, color, number）
│       │   ├── providers/        # dataProvider, authProvider, accessControlProvider
│       │   ├── i18n/             # 共用翻譯配置
│       │   └── index.ts          # 統一匯出
│       ├── package.json
│       └── tsconfig.json
├── package.json                  # workspace root
├── pnpm-workspace.yaml
└── .npmrc
```

## pnpm Workspace 配置

### pnpm-workspace.yaml

```yaml
packages:
  - 'apps/*'
  - 'packages/*'
```

### 根目錄 package.json

```json
{
  "name": "morgan-ustd",
  "private": true,
  "scripts": {
    "dev:admin": "pnpm --filter @morgan-ustd/admin dev",
    "dev:merchant": "pnpm --filter @morgan-ustd/merchant dev",
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

### Apps 引用 Shared

```json
{
  "name": "@morgan-ustd/admin",
  "dependencies": {
    "@morgan-ustd/shared": "workspace:*"
  }
}
```

## Shared Package 內容

### 設計原則

- 優先使用 Refine 官方元件和 hooks
- 只有業務邏輯相關或 API 對接相關才放入 shared
- 避免過度封裝 Refine 的功能

### 保留在 shared 的內容

| 類別 | 內容 | 原因 |
|------|------|------|
| interfaces/ | 所有業務型別定義 | 業務相關，必須統一 |
| lib/ | date, number, color 工具 | 通用工具函數 |
| providers/ | dataProvider, authProvider, accessControlProvider | 需要對接自訂 API |
| i18n/ | 共用的翻譯配置 | 統一語系設定 |

### 不放入 shared 的內容

| 類別 | 原因 |
|------|------|
| table 封裝 | 使用 Refine 官方 `useTable` + Ant Design `<Table>` |
| layout 元件 | 評估使用 Refine 的 `<ThemedLayoutV2>` |
| authPage 元件 | 評估使用 Refine 的 `<AuthPage>` |
| 自訂 hooks | 多數可用 Refine 官方 hooks 取代 |

## dataProvider 設計

### 後端 API 回傳格式（Laravel）

```typescript
// 列表 API
{
  data: [...],
  meta: {
    total: number,
    current_page: number,
    per_page: number,
  }
}

// 單筆 API
{
  data: {...}
}
```

### 轉換處理

| 項目 | 後端格式 | Refine v5 期望 | 處理方式 |
|------|----------|---------------|----------|
| 分頁參數 | `page`, `per_page`, `no_paginate` | `pagination.current`, `pagination.pageSize` | 轉換查詢參數 |
| 列表回傳 | `{ data, meta }` | `{ data, total }` | 從 `meta.total` 取值 |
| 語系 | `X-Locale` header | - | 保留 axios interceptor |
| 篩選 | 自訂格式 | Refine CrudFilters | 保留 `generateFilter` |

### 結構

```
packages/shared/src/providers/
├── dataProvider/
│   ├── index.ts           # 主要 dataProvider
│   ├── axiosInstance.ts   # 共用 axios 設定
│   ├── filters.ts         # generateFilter 工具
│   └── types.ts           # IRes 等型別
└── index.ts               # 匯出
```

### 使用方式

```typescript
import { createDataProvider } from '@morgan-ustd/shared';

const dataProvider = createDataProvider(API_URL);
```

## Refine 升級路徑

### v3 → v4 變更

| 項目 | v3 | v4 |
|------|----|----|
| 套件名稱 | `@pankod/refine-*` | `@refinedev/*` |
| Ant Design | v4 | v5 |
| authProvider | `login()` 回傳 `Promise<void>` | 回傳 `Promise<AuthActionResponse>` |
| dataProvider | `getList` 回傳 `data[]` | 回傳 `{ data[], total }` |
| 路由 | `useRouterContext` | `useGo`, `useParsed`, `useBack` |
| 資源定義 | `<Resource>` 元件 | `resources` prop 陣列 |

### v4 → v5 變更

| 項目 | v4 | v5 |
|------|----|----|
| React | 17/18 | 18+ 必須 |
| 套件整合 | `@refinedev/antd` 獨立安裝 | 更精簡的依賴 |
| inferencer | 獨立套件 | 內建或移除 |
| Legacy Router | 支援 | 完全移除 |

### 升級後的依賴

```json
{
  "@refinedev/core": "^4.x",
  "@refinedev/antd": "^5.x",
  "@refinedev/react-router": "^1.x",
  "antd": "^5.x"
}
```

## 實施步驟

### 階段一：建立 Monorepo 結構

1. 安裝 pnpm（如尚未安裝）
2. 建立 `pnpm-workspace.yaml` 和根目錄 `package.json`
3. 將 `admin/` 移至 `apps/admin/`
4. 將 `merchant/` 移至 `apps/merchant/`
5. 建立 `packages/shared/` 結構
6. 更新各專案的 package.json 名稱和依賴
7. 執行 `pnpm install` 驗證結構正確

### 階段二：抽取共用程式碼到 shared

1. 抽取 `interfaces/` 型別定義
2. 抽取 `lib/` 工具函數
3. 抽取並重構 `dataProvider`
4. 抽取 `authProvider`、`accessControlProvider`
5. 抽取 i18n 配置
6. 更新 admin/merchant 的 import 路徑
7. 驗證兩個專案都能正常運行

### 階段三：Refine v3 → v5 升級

1. 更新 shared 的 Refine 依賴（`@pankod/refine-*` → `@refinedev/*`）
2. 更新 dataProvider 符合 v5 API
3. 更新 authProvider 符合 v5 API
4. 升級 Ant Design v4 → v5
5. 移除已棄用的 Legacy Router，改用新路由
6. 移除自訂 table/form 封裝，改用 Refine 官方方式
7. 逐頁測試並修復 breaking changes

## 參考資料

- [Refine v3 to v4 Migration Guide](https://refine.dev/docs/migration-guide/3x-to-4x/)
- [Refine v4 to v5 Migration Guide](https://refine.dev/docs/migration-guide/4x-to-5x/)
- [pnpm Workspaces](https://pnpm.io/workspaces)
