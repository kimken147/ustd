# Admin & Merchant 重構設計方案

## 概述

本文件描述 admin 和 merchant 應用程式的重構計劃，主要目標：

1. 抽取共用程式碼到 `packages/shared`
2. 移除過度設計的 `useTable` hook，改用 Refine 官方方案
3. 重構 `PayForAnother/list.tsx` 作為範例 pattern

## 重構目標架構

### 目錄結構變更

```
目前狀態                           重構後
─────────────────────────────────────────────────────
apps/admin/src/hooks/             packages/shared/src/hooks/
  ├── useTable.tsx (313行)          ├── useUpdateModal.tsx
  ├── useUpdateModal.tsx            ├── useTransactionStatus.tsx
  ├── useTransactionStatus.tsx      ├── useWithdrawStatus.tsx
  └── ...                           ├── useTransactionCallbackStatus.tsx
                                    └── useSelector.tsx
apps/merchant/src/hooks/
  ├── useTable.tsx (299行)        apps/admin/src/hooks/
  ├── useUpdateModal.tsx            └── useTable.tsx (保留，標記 deprecated)
  └── ...
                                  apps/merchant/src/hooks/
                                    └── useTable.tsx (保留，標記 deprecated)
```

### 新增共用元件

```
packages/shared/src/
  ├── hooks/                    # 搬移的共用 hooks
  │   ├── useUpdateModal.tsx
  │   ├── useTransactionStatus.tsx
  │   ├── useWithdrawStatus.tsx
  │   ├── useTransactionCallbackStatus.tsx
  │   ├── useSelector.tsx
  │   └── index.ts
  ├── components/
  │   ├── ListPageLayout.tsx    # 新增：Form + Table 排版元件
  │   ├── Table.tsx             # 搬移：Table wrapper
  │   └── index.ts
  └── index.ts                  # 統一匯出
```

---

## Shared Hooks 遷移

### 需要遷移的 Hooks

| Hook | 行數 (Admin) | 行數 (Merchant) | 處理方式 |
|------|-------------|-----------------|----------|
| useUpdateModal | 327 | 310 | 合併差異後移到 shared |
| useTransactionStatus | 77 | 66 | 移到 shared，支援 i18n key 參數化 |
| useWithdrawStatus | 69 | 66 | 移到 shared，支援 i18n key 參數化 |
| useTransactionCallbackStatus | 57 | 52 | 移到 shared |
| useSelector | 43 | 43 | 移到 shared |
| useTable | 313 | 299 | **不遷移**，保留原處並標記 deprecated |

### 合併策略

Admin 和 Merchant 版本的主要差異：

1. **i18n hook 不同**：Admin 用 `useTranslation()`，Merchant 用 `useTranslate()`
2. **細微預設值差異**

**解決方案**：統一使用 Refine 的 `useTranslate()`，因為兩個 app 都是 Refine 專案。

### 匯出方式

```typescript
// packages/shared/src/index.ts
export * from './hooks/useUpdateModal';
export * from './hooks/useTransactionStatus';
export * from './hooks/useWithdrawStatus';
export * from './hooks/useTransactionCallbackStatus';
export * from './hooks/useSelector';

export * from './components/ListPageLayout';
export * from './components/Table';
```

### 使用方式

```typescript
// apps/admin/src/pages/xxx/list.tsx
import {
  useUpdateModal,
  useTransactionStatus,
  ListPageLayout
} from '@morgan-ustd/shared';
```

---

## ListPageLayout 元件設計

### 取代 useTable 的新 Pattern

**舊的方式（useTable hook）：**

```typescript
// 問題：hook 返回 JSX，混合邏輯和 UI
const { table, form } = useTable({
  resource: 'transactions',
  columns: [...],
  filterFields: [...],
});

return (
  <div>
    {form}
    {table}
  </div>
);
```

**新的方式（Refine useTable + ListPageLayout）：**

```typescript
import { useTable } from '@refinedev/antd';
import { ListPageLayout } from '@morgan-ustd/shared';

const List = () => {
  const { tableProps, searchFormProps } = useTable({
    resource: 'pay-for-another',
    syncWithLocation: true,
  });

  return (
    <ListPageLayout>
      <ListPageLayout.Filter form={searchFormProps}>
        <Form.Item name="order_no" label="訂單號">
          <Input />
        </Form.Item>
        {/* 其他篩選欄位 */}
      </ListPageLayout.Filter>

      <ListPageLayout.Table {...tableProps}>
        <Table.Column title="訂單號" dataIndex="order_no" />
        {/* 其他欄位 */}
      </ListPageLayout.Table>
    </ListPageLayout>
  );
};
```

### ListPageLayout 元件實作

```typescript
// packages/shared/src/components/ListPageLayout.tsx
import { Card, Form, Button, Table } from 'antd';
import type { FormProps, TableProps } from 'antd';

interface ListPageLayoutProps {
  children: React.ReactNode;
}

// 主元件 + 子元件組合模式（Compound Components）
const ListPageLayout = ({ children }: ListPageLayoutProps) => {
  return <div className="list-page-layout">{children}</div>;
};

interface FilterProps {
  form: FormProps;
  children: React.ReactNode;
}

// 篩選區塊
ListPageLayout.Filter = ({ form, children }: FilterProps) => (
  <Card style={{ marginBottom: 16 }}>
    <Form {...form} layout="inline">
      {children}
      <Form.Item>
        <Button type="primary" htmlType="submit">查詢</Button>
        <Button onClick={() => form.resetFields?.()}>重置</Button>
      </Form.Item>
    </Form>
  </Card>
);

interface ListTableProps extends TableProps<any> {
  children?: React.ReactNode;
}

// 表格區塊
ListPageLayout.Table = ({ children, ...tableProps }: ListTableProps) => (
  <Table {...tableProps} scroll={{ x: 'max-content' }}>
    {children}
  </Table>
);

export { ListPageLayout };
```

### 優點

- **關注點分離**：UI 排版和資料邏輯分開
- **彈性**：每頁可自訂 filter 和 columns
- **可讀性**：看程式碼就知道頁面結構
- **標準化**：使用 Refine 官方 useTable

---

## PayForAnother 重構

### 目前結構（1,347 行）

預計包含：
- 篩選表單定義 (~150 行)
- Table columns 定義 (~300 行)
- 多個 Modal 元件 (~500 行)
- 狀態處理和 API 呼叫 (~200 行)
- 主元件渲染 (~200 行)

### 重構後結構

```
apps/admin/src/pages/transaction/PayForAnother/
├── list.tsx                    # 主頁面 (~100 行)
├── components/
│   ├── FilterForm.tsx          # 篩選表單 (~80 行)
│   ├── columns.tsx             # Columns 定義 (~150 行)
│   ├── StatusTag.tsx           # 狀態標籤元件 (~30 行)
│   ├── ActionButtons.tsx       # 操作按鈕 (~50 行)
│   └── modals/
│       ├── DetailModal.tsx     # 詳情 Modal
│       ├── EditModal.tsx       # 編輯 Modal
│       └── index.ts            # 統一匯出
├── hooks/
│   └── usePayForAnotherActions.ts  # 頁面專用邏輯 (如有需要)
└── __tests__/
    ├── list.test.tsx           # 主頁面測試
    ├── FilterForm.test.tsx     # 篩選表單測試
    └── columns.test.tsx        # Columns 測試
```

### 重構後的 list.tsx 範例

```typescript
// apps/admin/src/pages/transaction/PayForAnother/list.tsx
import { useState } from 'react';
import { useTable } from '@refinedev/antd';
import { ListPageLayout } from '@morgan-ustd/shared';
import { FilterForm } from './components/FilterForm';
import { useColumns } from './components/columns';
import { DetailModal, EditModal } from './components/modals';

export const PayForAnotherList = () => {
  const [detailId, setDetailId] = useState<string | null>(null);
  const [editId, setEditId] = useState<string | null>(null);

  const { tableProps, searchFormProps } = useTable({
    resource: 'pay-for-another',
    syncWithLocation: true,
  });

  const columns = useColumns({
    onDetail: setDetailId,
    onEdit: setEditId,
  });

  return (
    <ListPageLayout>
      <FilterForm formProps={searchFormProps} />
      <ListPageLayout.Table {...tableProps} columns={columns} />

      <DetailModal id={detailId} onClose={() => setDetailId(null)} />
      <EditModal id={editId} onClose={() => setEditId(null)} />
    </ListPageLayout>
  );
};
```

### 測試策略

| 測試類型 | 涵蓋範圍 |
|---------|---------|
| Unit Test | FilterForm、columns、StatusTag 等獨立元件 |
| Integration Test | list.tsx 整頁的互動流程 |
| Mock | API 呼叫使用 MSW 或 jest mock |

---

## 待重構追蹤

### useTable 標記 Deprecated

```typescript
// apps/admin/src/hooks/useTable.tsx
/**
 * @deprecated 請使用 Refine 官方的 useTable + ListPageLayout
 * 重構範例參考：src/pages/transaction/PayForAnother/
 * 追蹤文件：docs/refactoring/useTable-migration.md
 */
export const useTable = (...) => { ... }
```

### 追蹤文件

建立 `docs/refactoring/useTable-migration.md` 追蹤所有需要重構的檔案：

**Admin (33 個檔案)**

Transaction 相關：
- ✅ transaction/PayForAnother/list.tsx
- ⬚ transaction/collection/list.tsx (1,434 行，高優先)
- ⬚ transaction/deposit/list.tsx (806 行)
- ⬚ transaction/deposit/systemBankCard/list.tsx
- ⬚ transaction/fund/list.tsx
- ⬚ transaction/message/list.tsx

Channel 相關：
- ⬚ userChannel/list.tsx (1,432 行，高優先)
- ⬚ channel/list.tsx
- ⬚ thirdChannel/list.tsx
- ⬚ thirdChannel/setting/list.tsx

用戶管理：
- ⬚ merchant/list.tsx (601 行)
- ⬚ merchant/wallet-history/list.tsx
- ⬚ merchant/user-wallet-history/list.tsx
- ⬚ providers/list.tsx (658 行)
- ⬚ providers/wallet-history/list.tsx
- ⬚ providers/user-wallet-history/list.tsx
- ⬚ provider/list.tsx
- ⬚ provider/deposit/list.tsx
- ⬚ provider/transaction/list.tsx

其他：
- ⬚ systemSetting/list.tsx
- ⬚ tag/list.tsx
- ⬚ permissions/list.tsx
- ⬚ loginWhiteList/list.tsx
- ⬚ financeStatitic/list.tsx
- ⬚ live/list.tsx
- ⬚ posts/list.tsx

**Merchant (7 個檔案)**
- ⬚ collection/list.tsx
- ⬚ member/list.tsx
- ⬚ PayForAnother/list.tsx
- ⬚ bankCard/list.tsx
- ⬚ subAccount/list.tsx
- ⬚ wallet-history/index.tsx

---

## 實作順序

```
Phase 1: 建立共用基礎
├── 1.1 遷移 hooks 到 packages/shared
│     └── useUpdateModal, useTransactionStatus, useWithdrawStatus,
│         useTransactionCallbackStatus, useSelector
├── 1.2 遷移 components 到 packages/shared
│     └── Table.tsx
└── 1.3 建立 ListPageLayout 元件

Phase 2: 重構 PayForAnother
├── 2.1 拆分 Admin 版本
│     └── FilterForm, columns, modals, 主頁面
├── 2.2 補上測試
│     └── Unit tests, Integration tests
└── 2.3 驗證功能正常

Phase 3: 收尾
├── 3.1 標記舊 useTable 為 deprecated
├── 3.2 建立 useTable-migration.md 追蹤文件
└── 3.3 更新 CLAUDE.md 或 README（如需要）
```

---

## 預期成果

| 項目 | Before | After |
|------|--------|-------|
| 重複 hooks | 6 個 × 2 apps | 5 個在 shared |
| useTable | 混合邏輯+UI | Refine 官方 + Layout 元件 |
| PayForAnother/list.tsx | 1,347 行 | ~100 行 + 拆分元件 |
| 測試覆蓋 | 無 | Unit + Integration |
| 追蹤機制 | 無 | migration.md |

---

## 不在此次範圍

- 其他 39 個 useTable 頁面的重構
- collection/list.tsx、userChannel/list.tsx 的重構
- Merchant 版本 PayForAnother 的重構（可作為後續任務）
