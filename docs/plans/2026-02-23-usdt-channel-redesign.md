# USDT 通道重新設計

**日期**: 2026-02-23
**狀態**: Draft

---

## 1. 目標

1. **移除所有 `CODE_*` 通道常數**（包含 31 個未使用 + 19 個仍在使用），清理硬編碼的通道判斷邏輯
2. **保留 `CODE_USDT`** 作為唯一通道代碼，重新設計為完整的 USDT 收付款通道
3. **新增鏈上監控**（Polling + TronGrid），實現自動上分
4. **混合模式**：同時支援自營收付款 + 第三方通道（ThirdChannel）

---

## 2. 移除範圍

### 2.1 Channel Model — 移除所有 CODE_* 常數（共 50 個）

#### 完全未使用（31 個）— 直接刪除常數定義

```
CODE_ECNY, CODE_O_ALIPAY, CODE_ZH_ALIPAY, CODE_UNION_QUICK_PASS, CODE_UNION_QR,
CODE_UNION_H5, CODE_QR_GCASH, CODE_PHONE_H5, CODE_ALIPAY_H5, CODE_ALIPAY_VM,
CODE_CRYSTAL_CARD, CODE_ELITE_CARD, CODE_WECHATPAY_H5, CODE_WEHCHATPAY_O,
CODE_RE_DINGDING, CODE_QR_QQ,
CODE_QR_ACB, CODE_QR_AGR, CODE_QR_BIDV, CODE_QR_EIB, CODE_QR_MB, CODE_QR_MSB,
CODE_QR_TCB, CODE_QR_TPB, CODE_QR_VCB, CODE_QR_VIB, CODE_QR_VPB, CODE_QR_VTB,
CODE_DC_ACB, CODE_DC_BIDV, CODE_DC_EIB, CODE_DC_MB, CODE_DC_STB, CODE_DC_TCB,
CODE_DC_TPB, CODE_DC_VCB, CODE_DC_VTB
```

#### 仍有引用（19 個）— 刪除常數 + 重構引用處

| 常數 | 應用程式碼引用數 | 受影響檔案 |
|------|----------------|-----------|
| CODE_USDT | 9 | **保留此常數**，重構引用 |
| CODE_ALIPAY_BANK | 1 | DisableTimeLimitUserChannelAccount |
| CODE_BANK_CARD | 1 | MatchedJsonResponse |
| CODE_QR_ALIPAY | 3 | Channel, AccountMatchingQueryBuilder, CreateTransactionController |
| CODE_ALIPAY_SAC | 3 | Channel, MatchedJsonResponse, AccountMatchingQueryBuilder |
| CODE_ALIPAY_BAC | 3 | Channel, MatchedJsonResponse, AccountMatchingQueryBuilder |
| CODE_ALIPAY_GC | 3 | Channel, MatchedJsonResponse, AccountMatchingQueryBuilder |
| CODE_ALIPAY_COPY | 2 | MatchedJsonResponse |
| CODE_WECHATPAY_BAC | 1 | MatchedJsonResponse |
| CODE_WECHATPAY_SAC | 1 | MatchedJsonResponse |
| CODE_GCASH | 2 | TransactionStatusService, UserChannelAccountController |
| CODE_MAYA | 2 | TransactionFactory, MatchedJsonResponse |
| CODE_DC_BANK | 2 | TransactionValidationService, CreateTransactionService |
| CODE_RE_ALIPAY | 3 | CreateTransactionService, TransactionNoteUtil, Provider/TransactionController |
| CODE_RE_QQ | 1 | Admin/Transaction Resource |
| CODE_QR_WECHATPAY | 1 | CreateTransactionController |
| CODE_QR_YFB | 1 | Admin/Transaction Resource |
| CODE_QR_BANK | 0 | 僅在 migrations |
| CODE_QR_MOMOPAY | 0 | 僅在 migrations |

### 2.2 需重構的應用程式碼檔案（19 個）

> **原則**: Migrations 已執行過不修改。Seeders 可更新。

| # | 檔案路徑 | 需移除的邏輯 | 處理方式 |
|---|---------|-------------|---------|
| 1 | `app/Models/Channel.php` | 所有 CODE_* 常數（除 CODE_USDT）+ `scanQrcodeUrlScheme()` 方法 + `RESPONSE_GCASH` | 刪除常數，刪除 `scanQrcodeUrlScheme()` |
| 2 | `app/Http/Controllers/ThirdParty/MatchedJsonResponse.php` | 整個 trait 中基於 channel code 的 if/else 判斷（BANK_CARD, QR_*, ALIPAY_*, USDT, MAYA） | **重構為基於 Channel 的 `deposit_account_fields` JSON 動態回傳**，僅保留 USDT 特殊處理 |
| 3 | `app/Http/Controllers/CreateTransactionController.php` | `getChannelCode()` 方法中的 ALIPAY_*, WECHATPAY_*, DC_BANK 判斷 | 刪除整個 `getChannelCode()` 方法或簡化 |
| 4 | `app/Services/Transaction/AccountMatchingQueryBuilder.php` | `applyPayingTransactionsRestriction()` 中 QR_ALIPAY/ALIPAY_SAC/BAC/GC 分組判斷 | 移除 Alipay 特殊分組邏輯 |
| 5 | `app/Services/Transaction/CreateTransactionService.php` | CODE_USDT（匯率）、CODE_RE_ALIPAY（備註）、CODE_DC_BANK（銀行名） | 保留 USDT 邏輯，移除 RE_ALIPAY 和 DC_BANK |
| 6 | `app/Services/Transaction/TransactionValidationService.php` | CODE_DC_BANK 驗證 | 移除 DC_BANK 特殊驗證 |
| 7 | `app/Services/Transaction/TransactionStatusService.php` | CODE_GCASH Redis 清理 | 移除 GCASH 特殊處理 |
| 8 | `app/Services/Withdraw/BaseWithdrawService.php` | CODE_USDT 匯率解析 | 保留，改用新匯率服務 |
| 9 | `app/Services/Withdraw/DTO/WithdrawContext.php` | `isUsdt()` | 保留 |
| 10 | `app/Services/Withdraw/WithdrawService.php` | CODE_USDT bank_name 比對 | 保留 |
| 11 | `app/Http/Controllers/Admin/UserChannelAccountController.php` | CODE_MAYA 同步 Job | 移除 Maya 同步相關 |
| 12 | `app/Http/Controllers/Provider/TransactionController.php` | CODE_RE_ALIPAY（紅包密碼）、CODE_RE_QQ（QR 更新） | 移除紅包特殊處理 |
| 13 | `app/Http/Resources/ThirdParty/Transaction.php` | CODE_USDT 匯率回傳 | 保留 |
| 14 | `app/Http/Resources/Admin/Transaction.php` | RE_QQ、QR_WECHATPAY、QR_YFB vendor name 查找 | 移除 vendor name 硬編碼 |
| 15 | `app/Http/Resources/Provider/Transaction.php` | CODE_RE_ALIPAY 紅包確認 | 移除紅包邏輯 |
| 16 | `app/Jobs/NotifyTransaction.php` | CODE_USDT 通知處理 | 保留 |
| 17 | `app/Utils/TransactionNoteUtil.php` | CODE_RE_ALIPAY 隨機備註 | 移除整個方法或類別 |
| 18 | `app/Utils/TransactionFactory.php` | CODE_MAYA strtoupper | 移除 |
| 19 | `app/Console/Commands/DisableTimeLimitUserChannelAccount.php` | CODE_ALIPAY_BANK、CODE_BANK_CARD whereIn | 移除或改為通用邏輯 |
| 20 | `database/seeds/ChannelSeeder.php` | QR_ALIPAY、ALIPAY_BANK seed 資料 | 移除舊通道 seed，新增 USDT seed |

### 2.3 移除 UsdtUtil

- **刪除**: `app/Utils/UsdtUtil.php`（Binance API 取匯率）
- 匯率功能將由新的 `UsdtRateService` 取代（見 §4.3）

### 2.4 UserChannelAccount — 移除未使用的 DETAIL_KEY

```php
// 移除
const DETAIL_KEY_ALIPAY_BANK_CODE = 'alipay_bank_code';  // 完全未使用
const DETAIL_KEY_BANK_ID = 'bank_id';                      // 已有同名 DB column
```

---

## 3. 資料模型變更

### 3.1 Channel Model — 僅保留 CODE_USDT

```php
class Channel extends Model
{
    const CODE_USDT = 'USDT';

    // 保留以下（非 CODE_ 開頭，且仍在使用）
    const STATUS_DISABLE = 0;
    const STATUS_ENABLE = 1;
    const RESPONSE_QRCODE = 1;
    const RESPONSE_URL = 2;
    const RESPONSE_BANK_CARD = 3;
    const RESPONSE_FORM = 4;
    // 移除 RESPONSE_GCASH = 5
    const NOTE_GROCERIES = 1;
    const NOTE_TREASURE = 2;
    const TYPE_DEPOSIT_WITHDRAW = 1;
    const TYPE_DEPOSIT_ONLY = 2;
    const TYPE_WITHDRAW_ONLY = 3;

    // 刪除 scanQrcodeUrlScheme() 方法
}
```

### 3.2 UserChannelAccount — 新增 USDT detail keys

```php
// 新增
const DETAIL_KEY_WALLET_ADDRESS = 'wallet_address';  // USDT 收款地址
const DETAIL_KEY_CHAIN_NETWORK = 'chain_network';    // 鏈網絡: trc20, erc20, bep20
```

不需要新增 DB column，`detail` JSON 欄位已足夠。

### 3.3 Transaction 表 — 新增欄位（Migration）

```php
Schema::table('transactions', function (Blueprint $table) {
    $table->string('chain_network', 10)->nullable()->after('usdt_rate');  // trc20, erc20, bep20
    $table->string('tx_hash', 100)->nullable()->after('chain_network');   // 鏈上交易 hash
});
```

### 3.4 新表：`usdt_deposit_monitors`

用於追蹤鏈上監控狀態，Polling 服務查詢此表決定要監控哪些地址。

```php
Schema::create('usdt_deposit_monitors', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('transaction_id')->unique();          // 對應的交易訂單
    $table->unsignedBigInteger('user_channel_account_id');           // 對應的收款帳號
    $table->string('address', 100)->index();                         // 監控地址
    $table->string('chain_network', 10);                             // trc20, erc20, bep20
    $table->decimal('expected_amount', 20, 6);                       // 預期收到的 USDT 金額
    $table->decimal('received_amount', 20, 6)->default(0);           // 實際收到金額
    $table->string('tx_hash', 100)->nullable();                      // 匹配到的鏈上 tx hash
    $table->tinyInteger('status')->default(0);                       // 0=待監控 1=已匹配 2=已確認 3=逾時
    $table->timestamp('expires_at')->nullable();                     // 監控截止時間
    $table->timestamp('matched_at')->nullable();                     // 匹配到交易的時間
    $table->timestamp('confirmed_at')->nullable();                   // 確認完成的時間
    $table->timestamp('last_polled_at')->nullable();                 // 上次 polling 時間
    $table->timestamps();

    $table->foreign('transaction_id')->references('id')->on('transactions');
    $table->foreign('user_channel_account_id')->references('id')->on('user_channel_accounts');
});
```

---

## 4. 新增服務

### 4.1 CryptoMonitorService — 鏈上監控核心

```
app/Services/Crypto/
├── CryptoMonitorService.php       // 排程入口：查詢待監控記錄 → 分派給對應 Adapter
├── Adapters/
│   ├── ChainAdapterInterface.php  // 介面：fetchTransactions(address, since)
│   ├── Trc20Adapter.php           // TronGrid API (TRC-20 USDT)
│   ├── Erc20Adapter.php           // Etherscan/公開 RPC (ERC-20 USDT) [後續]
│   └── Bep20Adapter.php           // BscScan/公開 RPC (BEP-20 USDT) [後續]
└── DTO/
    └── ChainTransaction.php       // DTO: txHash, from, to, amount, timestamp, confirmations
```

**Polling 流程**:
1. `CryptoMonitorService::poll()` 由 `artisan schedule` 每 30 秒執行
2. 查詢 `usdt_deposit_monitors` 中 `status=0`（待監控）且未逾時的記錄
3. 按 `chain_network` 分組，呼叫對應 Adapter
4. Adapter 查詢地址的最新 TRC-20/ERC-20 轉入交易
5. 比對 `expected_amount`（含浮動容差）
6. 匹配成功 → 更新 monitor 記錄 + 呼叫 `TransactionStatusService::markAsSuccess()`
7. 將 `tx_hash` 寫入 `transactions.tx_hash`

**第一階段僅實作 `Trc20Adapter`**，使用 TronGrid 免費 API：
```
GET https://api.trongrid.io/v1/accounts/{address}/transactions/trc20
?only_to=true&contract_address=TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t&limit=20
```

### 4.2 UsdtPayoutService — 自營代付（後續實作）

```
app/Services/Crypto/
└── UsdtPayoutService.php   // 從熱錢包發送 USDT 到指定地址
```

第一階段代付走 ThirdChannel 模式，自營代付後續再接。

### 4.3 UsdtRateService — 暫不實作

USDT 匯率功能目前不需要，後續再實作。移除 `UsdtUtil` 後，相關引用一併清除即可。

---

## 5. 收款上分流程（Deposit）

### 5.1 自營模式

```
商戶透過 API 發起充值（指定 channel_code=USDT）
    ↓
CreateTransactionService 建立訂單
    ↓
匹配 Provider 的 USDT UserChannelAccount（STATUS_MATCHING → STATUS_PAYING）
    ↓
回傳 Provider 的 wallet_address + chain_network + expected_amount
    ↓
同時建立 usdt_deposit_monitors 記錄（status=待監控）
    ↓
CryptoMonitorService Polling 偵測鏈上交易
    ↓
比對成功 → markAsSuccess() + 記錄 tx_hash
```

### 5.2 第三方模式

與現有 ThirdChannel 流程一致，由第三方 callback 觸發成功。

### 5.3 MatchedJsonResponse 重構

移除所有基於 channel code 的 if/else，改為：

```php
// USDT 通道特殊回傳
if ($channel->code === Channel::CODE_USDT) {
    $info['wallet_address'] = $transaction->from_channel_account[UserChannelAccount::DETAIL_KEY_WALLET_ADDRESS] ?? '';
    $info['chain_network'] = $transaction->from_channel_account[UserChannelAccount::DETAIL_KEY_CHAIN_NETWORK] ?? '';
    $info['usdt_rate'] = $transaction->usdt_rate;
    $info['rate_amount'] = $transaction->rate_amount;
}
```

其他舊通道的 QR code / bank card / receiver 回傳邏輯全部移除。

---

## 6. 代付流程（Withdraw）

### 6.1 商戶發起代付

```
POST /api/v1/merchant/withdraws
{
    "bank_name": "USDT",              // 保留現有欄位名（bank_name 在此語境指付款方式）
    "bank_card_number": "TXxxxx...",  // 收款地址
    "chain_network": "trc20",         // 新增欄位
    "amount": 100.00
}
```

### 6.2 BaseWithdrawService 處理

1. `WithdrawContext::isUsdt()` 判斷為 USDT 代付
2. 取得匯率（`UsdtRateService::getRate()`）
3. 建立訂單
4. `ThirdChannelDispatcher` 嘗試第三方代付
5. 第三方不可用 → 進入自營代付佇列（後續實作）

---

## 7. 實作階段

### Phase 1：清理 CODE_* 常數 + 移除舊通道邏輯
- 移除 Channel 中所有 CODE_* 常數（除 CODE_USDT）
- 移除/重構 19 個受影響的應用程式碼檔案
- 移除 `scanQrcodeUrlScheme()` 方法
- 移除 `DETAIL_KEY_ALIPAY_BANK_CODE`、`DETAIL_KEY_BANK_ID`
- 移除 `UsdtUtil`
- 更新 ChannelSeeder

### Phase 2：資料模型 + USDT 基礎設施
- Migration：Transaction 表新增 `chain_network`、`tx_hash`
- Migration：建立 `usdt_deposit_monitors` 表
- 新增 UserChannelAccount DETAIL_KEY（WALLET_ADDRESS、CHAIN_NETWORK）
- 新增 `UsdtRateService` 取代 `UsdtUtil`
- 新增 `UsdtDepositMonitor` Model

### Phase 3：收款上分
- 新增 `CryptoMonitorService` + `Trc20Adapter`
- 在 `CreateTransactionService` 中整合：建立訂單時同步建立 monitor 記錄
- 重構 `MatchedJsonResponse`：僅保留 USDT 回傳邏輯
- 排程任務：`schedule->call(CryptoMonitorService::poll())->everyThirtySeconds()`

### Phase 4：代付
- 重構 `BaseWithdrawService` USDT 匯率邏輯使用 `UsdtRateService`
- 確保 ThirdChannel 代付流程支援 USDT
- 自營代付（`UsdtPayoutService`）後續再接

---

## 8. 不修改的範圍

- **Migrations**：已執行過的歷史 migration 不修改（裡面的 channel code 字串無害）
- **ThirdChannel 模型和實作**：保持不變，繼續支援第三方通道
- **Transaction Model**：TYPE_* 和 STATUS_* 常數保持不變
- **UserChannelAccount**：現有 DB schema 不變（除新增 detail key）
- **Wallet / Fee 計算**：保持現有邏輯
