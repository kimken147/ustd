# 2026-03-24 三項功能設計

## 功能一：母地址搜索 Filter

### 需求
在以下三個列表頁面新增「母地址」文字搜索欄位：
1. 收付款帳號列表（UserChannelAccount list）
2. 收款單列表（Deposit transaction list）
3. 付款單列表（Withdrawal transaction list）

### 後端

**UserChannelAccount Builder** (`api/app/Builders/UserChannelAccount.php`)
- 新增 `parent_account` filter
- 查詢邏輯：以地址字串模糊搜索 `UserChannelAccount`（address_type=master），取得匹配的母地址 ID
- 篩選條件：`parent_account_id IN (matched_ids) OR id IN (matched_ids)`（母地址本身 + 所有子地址）

**Transaction Builder**（收款單 / 付款單）
- 新增 `parent_account` filter
- 先查 UserChannelAccount 找到匹配的母地址及其子地址 ID
- 收款單：篩選 `from_channel_account_id IN (account_ids)`
- 付款單：篩選 `to_channel_account_id IN (account_ids)`

### 前端

三個列表頁面各新增一個 Input 搜索欄位「母地址」，參數名 `parent_account`。

---

## 功能二：批量轉帳同時轉 Gas

### 需求
批量轉帳時，除了轉 USDT，也把 source 帳號上多餘的原生代幣（TRX/ETH/BNB）歸集到 target 帳號。

### 流程（BatchTransferUsdt Job）

1. 檢查 source gas 是否足夠 → 不夠從母地址補充（現有邏輯）
2. 轉 USDT 到 target（現有邏輯）
3. **查詢 source 剩餘原生代幣餘額**
4. **計算原生代幣轉帳手續費**
   - TRC20：查 bandwidth，足夠則 0 TRX，不足則 ~0.267 TRX + buffer ≈ 1 TRX
   - ERC20/BEP20：21000 × gasPrice + 10% buffer
5. **剩餘金額 > config 門檻 → 轉原生代幣到 target**
6. Dispatch `ConfirmUsdtWithdraw`（現有邏輯不變）

### Gas 預留計算

| 鏈 | 手續費估算 | 預留 |
|---|---|---|
| TRC20（bandwidth 足夠） | 0 TRX | 0 TRX |
| TRC20（bandwidth 不足） | ~0.267 TRX | 1 TRX |
| ERC20 | 21000 × gasPrice | +10% buffer |
| BEP20 | 21000 × gasPrice | +10% buffer |

### Config（`config/services.php`）

```php
'trongrid' => [
    'min_gas_transfer_amount' => env('TRONGRID_MIN_GAS_TRANSFER', '1'),    // TRX
],
'ethereum' => [
    'min_gas_transfer_amount' => env('ETH_MIN_GAS_TRANSFER', '0.001'),     // ETH
],
'bsc' => [
    'min_gas_transfer_amount' => env('BSC_MIN_GAS_TRANSFER', '0.001'),     // BNB
],
```

### 狀態處理
- Gas 轉帳的成功/失敗不影響 USDT 轉帳的狀態判定
- Gas 轉帳失敗只記 log

---

## 功能三：系統設定控制統計欄位顯示

### 需求
系統設定新增 2 個 FeatureToggle，加上現有 2 個，共 4 個獨立控制帳號列表的統計欄位顯示：

| Toggle | 控制欄位 |
|--------|---------|
| 日額度（ID 35，現有） | 日收款額度、日出款額度 |
| 月額度（ID 45，現有） | 月收款額度、月出款額度 |
| 日筆數（新增） | 日收款筆數、日出款筆數 |
| 月筆數（新增） | 月收款筆數、月出款筆數 |

Toggle 關閉時，收付款帳號管理列表隱藏對應統計欄位。

### 後端

- 新增 migration 建立 2 筆 FeatureToggle 記錄
- `UserChannelAccountController` meta 回傳新增 `daily_count_enabled`、`monthly_count_enabled`
- `UserChannelAccount` Resource 帶上新 toggle 狀態

### 前端

- `useSystemSetting` hook 讀取 2 個新 toggle
- 帳號列表日筆數/月筆數欄位根據 toggle 顯示/隱藏
- 系統設定頁面「帳號」分類下顯示新 toggle
