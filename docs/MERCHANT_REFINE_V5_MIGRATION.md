# Merchant App - Refine v5 Migration Guide

此文件記錄 admin 應用升級到 Refine v5 時所做的更改，供 merchant 應用參考。

## 已完成的套件升級

```json
{
  "@refinedev/antd": "^6.0.3",
  "@refinedev/core": "^5.0.8",
  "@refinedev/react-router": "^2.0.3"
}
```

移除的套件：
- `@refinedev/react-router-v6` (已被 `@refinedev/react-router` 取代)

## 需要更改的模式

### 1. authProvider.ts - 更新為 v5 格式

**主要變更：**
- `checkAuth` → `check`
- `checkError` → `onError`
- `getUserIdentity` → `getIdentity`
- 所有方法需返回物件格式而非拋出錯誤

```typescript
// 舊版 (v3/v4)
checkAuth: async () => {
  if (!token) throw new Error("No token");
},
checkError: async (error) => {
  if (error.status === 401) throw error;
},
getUserIdentity: async () => {
  return user;
},

// 新版 (v5)
check: async () => {
  if (!token) return { authenticated: false, redirectTo: '/login' };
  return { authenticated: true };
},
onError: async (error) => {
  if (error.status === 401) return { logout: true, redirectTo: '/login' };
  return { error };
},
getIdentity: async () => {
  return user;
},
```

### 2. App.tsx - 路由結構重寫

**主要變更：**
- 移除 `legacyRouterProvider`
- 使用 `BrowserRouter` + `Routes` 替代
- 使用 `Authenticated` 組件替代 `AuthPage`

```tsx
// 新結構
<BrowserRouter>
  <Refine
    dataProvider={dataProvider}
    authProvider={authProvider}
    routerProvider={routerBindings}
    resources={[...]}
    options={{
      syncWithLocation: true,
      warnWhenUnsavedChanges: true,
      reactQuery: {
        clientConfig: {
          defaultOptions: {
            queries: { staleTime: 0, refetchOnWindowFocus: false },
          },
        },
      },
    }}
  >
    <Routes>
      <Route element={<Authenticated ... />}>
        <Route element={<ThemedLayout ... />}>
          {/* 資源路由 */}
        </Route>
      </Route>
      <Route element={<Authenticated ... />}>
        <Route path="/login" element={<Login />} />
      </Route>
    </Routes>
  </Refine>
</BrowserRouter>
```

### 3. dataProvider.ts - 參數變更

```typescript
// 舊版
getList: async ({ resource, metaData, pagination, sort, ... }) => {
  const { current, pageSize } = pagination;
  // ...
}

// 新版
getList: async ({ resource, meta, pagination, sorters, ... }) => {
  // pagination 可能是 { mode: "off" } 或 { current, pageSize }
  const paginationConfig = pagination as { current?: number; pageSize?: number } | undefined;
  // ...
}
```

### 4. useList 返回值變更

```typescript
// 舊版
const { data, isLoading } = useList({ resource: "xxx" });
const items = data?.data;

// 新版
const { result, query } = useList({ resource: "xxx" });
const items = result.data;
const isLoading = query.isLoading;
```

### 5. useOne 返回值變更

```typescript
// 舊版
const { data } = useOne({ resource: "xxx", id: 1 });
const item = data?.data;

// 新版
const { result } = useOne({ resource: "xxx", id: 1 });
const item = result; // 直接是物件，不需要 .data
```

### 6. useMany 返回值變更

```typescript
// 舊版
const { data, isLoading } = useMany({ resource: "xxx", ids: [...] });

// 新版
const { result, query } = useMany({ resource: "xxx", ids: [...] });
const data = result;
const isLoading = query.isLoading;
```

### 7. useCustom 返回值變更

```typescript
// 舊版
const { data, refetch } = useCustom({ url: "...", method: "get" });

// 新版
const { result, query } = useCustom({ url: "...", method: "get" });
const data = result;
const refetch = query.refetch;
```

### 8. useCustomMutation 返回值變更

```typescript
// 舊版
const { mutateAsync, isLoading } = useCustomMutation();

// 新版
const { mutateAsync, mutation } = useCustomMutation();
const isLoading = mutation.isPending;
```

### 9. useCreate 返回值變更

```typescript
// 舊版
const { mutateAsync, isLoading } = useCreate();

// 新版
const { mutateAsync, mutation } = useCreate();
const isLoading = mutation.isPending;
```

### 10. useLogin 返回值變更

```typescript
// 舊版
const { mutate, isLoading } = useLogin();

// 新版
const { mutate, isPending } = useLogin();
// 注意：isPending 直接在返回物件上，不在 mutation 中
```

### 11. useShow 返回值變更

```typescript
// 舊版
const { queryResult } = useShow();
const { data, isLoading } = queryResult;

// 新版
const { query } = useShow();
const { data, isLoading } = query;
```

### 12. useTable (Refine) 排序參數變更

```typescript
// 舊版
const { tableProps, sorter } = useTable({
  initialSorter: [{ field: "id", order: "desc" }],
});

// 新版
const { tableProps, sorters } = useTable({
  sorters: {
    initial: [{ field: "id", order: "desc" }],
  },
});
```

### 13. useList config 參數變更

```typescript
// 舊版
useList({
  resource: "xxx",
  config: {
    hasPagination: false,
    filters: [...],
  },
});

// 新版
useList({
  resource: "xxx",
  pagination: { mode: "off" },
  filters: [...],
});
```

### 14. 移除的 API

- `useTitle` - 使用 `DefaultTitle` 直接導入
- `useRouterContext` - 使用 react-router 的 `Link` 直接導入
- `goBack` 從 `useNavigation` - 使用 react-router 的 `useNavigate` 並調用 `navigate(-1)`
- `ITreeMenu` 類型 - 需要自行定義

### 15. 屬性重命名

```typescript
// 舊版
<ShowButton resourceNameOrRouteName="xxx" />

// 新版
<ShowButton resource="xxx" />
```

### 16. metaData → meta

所有使用 `metaData` 的地方改為 `meta`。

### 17. dayjs isoWeek 插件

如果使用 `dayjs().startOf('isoWeek')`，需要導入並擴展 isoWeek 插件：

```typescript
import isoWeek from 'dayjs/plugin/isoWeek';
dayjs.extend(isoWeek);
```

## 修改的檔案清單 (Admin)

以下是 admin 應用中修改過的檔案，merchant 應用中對應的檔案也需要檢查並更新：

### 核心檔案
- `src/App.tsx` - 完全重寫
- `src/authProvider.ts` - 更新 API
- `src/dataProvider.ts` - 參數變更

### Hooks
- `src/hooks/useTable.tsx`
- `src/hooks/useSelector.tsx`
- `src/hooks/useUpdateModal.tsx`
- `src/hooks/useBank.tsx`
- `src/hooks/useChannel.tsx`
- `src/hooks/useChannelAmounts.tsx`
- `src/hooks/useChannelGroup.tsx`
- `src/hooks/useChannelMutation.tsx`
- `src/hooks/useMerchant.tsx`
- `src/hooks/useProvider.tsx`
- `src/hooks/useSystemSetting.tsx`
- `src/hooks/useUser.tsx`
- `src/hooks/useUserChannelAccount.tsx`

### Components
- `src/components/EditableFormItem.tsx`
- `src/components/authPage/login.tsx`
- `src/components/contentHeader.tsx`
- `src/components/customDatePicker.tsx`
- `src/components/layout/sider/index.tsx`
- `src/components/layout/title/index.tsx`

### Pages
- 所有使用 `useShow`, `useList`, `useOne`, `useCreate` 等 hooks 的頁面都需要檢查

## 執行步驟

1. 更新 `package.json` 中的 Refine 相關套件版本
2. 運行 `pnpm install`
3. 按照上述模式更新 `authProvider.ts`
4. 重寫 `App.tsx` 路由結構
5. 更新 `dataProvider.ts` 參數
6. 逐一更新 hooks 和 components
7. 運行 `pnpm exec tsc --noEmit` 檢查 TypeScript 錯誤
8. 逐一修復錯誤直到編譯通過
9. 運行 `pnpm run local` 驗證應用啟動正常
