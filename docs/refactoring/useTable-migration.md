# useTable Migration Tracker

## 重構 Pattern
參考：`apps/admin/src/pages/transaction/PayForAnother/`

### 新架構
- 使用 Refine 官方 `useTable` from `@refinedev/antd`
- 使用 `ListPageLayout` from `@morgan-ustd/shared`
- 抽取 `FilterForm` 元件
- 抽取 `useColumns` hook

### 共用 Hooks (from @morgan-ustd/shared)
- `useSelector` - 通用下拉選擇器
- `useWithdrawStatus` - 提領狀態
- `useTransactionCallbackStatus` - 交易回調狀態
- `useUpdateModal` - 更新 Modal

## 狀態說明
- ✅ 已完成
- 🔄 進行中
- ⬚ 待處理

## Admin (33 個檔案)

### Transaction 相關
- ✅ transaction/PayForAnother/list.tsx
- ✅ transaction/collection/list.tsx (1,434 行，高優先)
- ⬚ transaction/deposit/list.tsx (806 行)
- ⬚ transaction/deposit/systemBankCard/list.tsx
- ⬚ transaction/fund/list.tsx
- ⬚ transaction/message/list.tsx

### Channel 相關
- ⬚ userChannel/list.tsx (1,432 行，高優先)
- ⬚ channel/list.tsx
- ⬚ thirdChannel/list.tsx
- ⬚ thirdChannel/setting/list.tsx

### 用戶管理
- ⬚ merchant/list.tsx (601 行)
- ⬚ merchant/wallet-history/list.tsx
- ⬚ merchant/user-wallet-history/list.tsx
- ⬚ providers/list.tsx (658 行)
- ⬚ providers/wallet-history/list.tsx
- ⬚ providers/user-wallet-history/list.tsx
- ⬚ provider/list.tsx
- ⬚ provider/deposit/list.tsx
- ⬚ provider/transaction/list.tsx

### 其他
- ⬚ systemSetting/list.tsx
- ⬚ tag/list.tsx
- ⬚ permissions/list.tsx
- ⬚ loginWhiteList/list.tsx
- ⬚ financeStatitic/list.tsx
- ⬚ live/list.tsx
- ⬚ posts/list.tsx

## Merchant (7 個檔案)
- ⬚ collection/list.tsx
- ⬚ member/list.tsx
- ✅ PayForAnother/list.tsx
- ⬚ bankCard/list.tsx
- ⬚ subAccount/list.tsx
- ⬚ wallet-history/index.tsx

## 完成後
當所有檔案都標記為 ✅ 後，可以：
1. 刪除 `apps/admin/src/hooks/useTable.tsx`
2. 刪除 `apps/merchant/src/hooks/useTable.tsx`
3. 刪除備份檔案 `list.backup.tsx`
