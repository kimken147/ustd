# Tron 鏈上交易明細同步設計

## 概述

同步 TRC20 USDT 鏈上交易明細到本地資料庫，提供管理員查看和對帳功能。

**範圍：**
- 僅 USDT (TRC20) 交易
- 雙向（收款 + 付款）
- 啟用時回溯 30 天歷史，之後持續同步
- 自動比對 + 人工處理未匹配交易

## 資料庫設計

新建 `chain_transactions` 表：

```
chain_transactions
├── id (bigint, PK)
├── tx_hash (varchar 66, unique index)
├── user_channel_account_id (int, FK, nullable, index)
├── direction (enum: 'in', 'out')
├── from_address (varchar 42, index)
├── to_address (varchar 42, index)
├── amount (decimal 20,6)
├── block_number (bigint)
├── block_timestamp (timestamp, index)
├── confirmations (int, default 0)
├── match_status (enum: 'pending', 'matched', 'unmatched', 'ignored', default 'pending', index)
├── matched_transaction_id (int, nullable, FK → transactions.id)
├── matched_at (timestamp, nullable)
├── matched_by (int, nullable, FK → users.id)  -- null=自動比對, 有值=手動關聯
├── note (text, nullable)
├── raw_data (json, nullable)  -- TronGrid 原始回應
├── created_at (timestamp)
├── updated_at (timestamp)
```

**設計重點：**
- `tx_hash` 唯一索引防止重複寫入
- `user_channel_account_id` 透過地址比對自動填入
- `direction`：我方地址在 `to_address` → `in`，在 `from_address` → `out`
- `match_status` 四種狀態：`pending`（待比對）、`matched`（已匹配）、`unmatched`（確認無對應記錄）、`ignored`（管理員手動忽略）
- `raw_data` 存原始 JSON，未來需要額外欄位時不用改 schema

## 同步服務架構

新建 `ChainTransactionSyncService`：

```
ChainTransactionSyncService
├── syncRecentTransactions(UserChannelAccount $account)
├── syncAllAccounts()
├── backfillHistory(UserChannelAccount $account, int $days = 30)
└── processRawTransaction(array $txData, UserChannelAccount $account)
```

### 同步流程

1. **搭載現有輪詢（每 30 秒）**：修改 `CryptoMonitorService::pollDeposits()`，在現有邏輯拉到交易後，順便呼叫 `processRawTransaction()` 存入 `chain_transactions`。不增加額外 API 呼叫。

2. **獨立補漏排程（每小時）**：新增 Artisan command `chain:sync-transactions`，透過 ShortSchedule 每小時執行。對每個 USDT 類型的 `UserChannelAccount`，呼叫 TronGrid API 拉最近 200 筆交易，以 `tx_hash` 做 upsert 避免重複。

3. **歷史回溯（一次性 / 手動）**：新增 Artisan command `chain:backfill-history {--days=30} {--account_id=}`。使用 TronGrid 分頁 API（`fingerprint` 參數）逐頁拉取，每頁 200 筆，帶 `min_timestamp` 限制回溯範圍。

### API 用量估算（Pro Plan）

- 30 秒輪詢：不增加（搭載現有呼叫）
- 每小時補漏：50 帳號 × 1 次/帳號 = 50 calls/hr = 1,200 calls/day
- 歷史回溯：一次性，50 帳號 × 平均 5 頁 = 250 calls

## 自動比對服務

新建 `ChainTransactionMatchService`：

```
ChainTransactionMatchService
├── matchTransaction(ChainTransaction $chainTx)
├── matchPendingTransactions()
└── manualMatch(ChainTransaction $chainTx, int $transactionId, int $userId)
```

### 自動比對策略（按優先順序）

1. **tx_hash 精確匹配**：查詢 `transactions` 表的 `tx_hash` 欄位。出款交易在建立時已寫入 tx_hash。命中 → `matched`。

2. **金額 + 時間窗口匹配**：對收款交易（direction=in），搜尋相同 `user_channel_account_id`、金額一致、時間差 ±10 分鐘的記錄。唯一命中 → `matched`；多筆命中 → 維持 `pending` 待人工處理。

3. **無匹配**：超過 24 小時仍為 `pending` → 自動標記 `unmatched`。

### 比對時機

- 新交易存入時立即嘗試比對
- 每小時補漏排程結束後，對所有 `pending` 記錄重新比對
- 每天執行一次 `chain:mark-unmatched`，將超過 24 小時的 `pending` 標記為 `unmatched`

### 手動操作

- 管理員可將 `unmatched` 交易手動關聯到一筆 Transaction（記錄 `matched_by`）
- 管理員可將交易標記為 `ignored`（例如測試交易、誤轉）

## API 端點

```
GET    /api/v1/admin/chain-transactions            -- 列表（篩選、分頁）
GET    /api/v1/admin/chain-transactions/:id         -- 單筆詳情
PUT    /api/v1/admin/chain-transactions/:id/match   -- 手動關聯
PUT    /api/v1/admin/chain-transactions/:id/ignore  -- 標記忽略
POST   /api/v1/admin/chain-transactions/sync        -- 手動觸發同步
```

### 列表頁篩選條件

- `match_status`：pending / matched / unmatched / ignored
- `direction`：in / out
- `user_channel_account_id`：指定帳號
- `address`：搜尋 from 或 to 地址
- `tx_hash`：精確搜尋
- `amount_min` / `amount_max`：金額範圍
- `date_range`：鏈上交易時間範圍

### 列表頁欄位

- 交易時間、tx_hash（連結到 Tronscan）、方向（收/付）、金額
- from → to 地址、所屬帳號、比對狀態
- 關聯的系統訂單號、操作按鈕

### 操作按鈕

- `pending` / `unmatched`：「關聯」（彈窗搜尋 Transaction）、「忽略」
- `matched`：「查看關聯訂單」
- `ignored`：「恢復」（改回 pending 重新比對）
- 頁面頂部：「手動同步」按鈕

### 權限

- 查看：`chain-transactions` action `1`（讀取）
- 操作（關聯/忽略/同步）：`chain-transactions` action `5`（編輯）

## 檔案結構

### 後端新增

```
api/
├── app/Models/ChainTransaction.php
├── app/Http/Controllers/Admin/ChainTransactionController.php
├── app/Http/Resources/ChainTransactionResource.php
├── app/Services/Crypto/ChainTransactionSyncService.php
├── app/Services/Crypto/ChainTransactionMatchService.php
├── app/Console/Commands/SyncChainTransactions.php
├── app/Console/Commands/BackfillChainHistory.php
├── app/Console/Commands/MarkUnmatchedChainTransactions.php
├── database/migrations/xxxx_create_chain_transactions_table.php
└── routes/api-v1.php  -- 新增路由
```

### 前端新增

```
apps/admin/src/pages/chainTransaction/
├── list.tsx
├── show.tsx（或 drawer）
└── components/
    └── MatchModal.tsx

packages/shared/src/interfaces/
└── chainTransaction.ts

apps/admin/public/locales/
├── zh-CN/chainTransaction.json
└── en/chainTransaction.json
```

### 修改現有檔案

- `CryptoMonitorService.php` — 在 `pollDeposits()` 中呼叫 `processRawTransaction()`
- `Trc20Adapter.php` — 新增 `fetchTransactionHistory()` 方法（支援分頁、時間範圍）
- `routes/api-v1.php` — 新增 chain-transactions 路由群組
- Admin `App.tsx` — 新增 chainTransaction resource 路由
- Permission seed — 新增 `chain-transactions` 權限

## 實作順序

1. Migration + Model
2. `ChainTransactionSyncService` + `Trc20Adapter` 擴充
3. 修改 `CryptoMonitorService` 搭載同步
4. `ChainTransactionMatchService`
5. Artisan commands（sync、backfill、mark-unmatched）
6. API Controller + Resource + 路由
7. 前端頁面
