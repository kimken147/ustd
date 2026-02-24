# USDT 通道重新設計 Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** 移除所有舊通道 CODE_* 常數及相關邏輯，保留 CODE_USDT，並新增鏈上 Polling 監控實現自動收款上分。

**Architecture:** Phase 1 清理所有舊通道硬編碼邏輯（50 個 CODE_* 常數 + 19 個受影響檔案 + UsdtUtil）。Phase 2 新增 DB schema（usdt_deposit_monitors 表、transaction 欄位）和 USDT Model/Detail Key。Phase 3 實作 CryptoMonitorService + Trc20Adapter 鏈上 Polling，整合到收款流程。Phase 4 清理代付流程中的 USDT 匯率邏輯。

**Tech Stack:** Laravel 11, PHP 8.2+, TronGrid API (TRC-20 Polling), Redis, MySQL

**Design Doc:** `docs/plans/2026-02-23-usdt-channel-redesign.md`

---

## Phase 1: 清理舊通道邏輯

### Task 1: 移除 Channel Model 中所有非 USDT 的 CODE_* 常數

**Files:**
- Modify: `api/app/Models/Channel.php:26-86` (刪除常數)
- Modify: `api/app/Models/Channel.php:95` (刪除 RESPONSE_GCASH)
- Modify: `api/app/Models/Channel.php:162-173` (刪除 scanQrcodeUrlScheme 方法)

**Step 1: 移除所有非 USDT 的 CODE_* 常數和 scanQrcodeUrlScheme**

在 `Channel.php` 中：
- 刪除 line 26-44 的所有常數（CODE_ALIPAY_BANK 到 CODE_QR_QQ）
- 保留 line 45 `const CODE_USDT = 'USDT';`
- 刪除 line 46-86 的所有常數（CODE_PHONE_H5 到 CODE_DC_BANK）
- 刪除 line 95 `const RESPONSE_GCASH = 5;`
- 刪除 line 162-173 的 `scanQrcodeUrlScheme()` 方法

最終 Channel.php 常數區只保留：
```php
class Channel extends Model
{
    const CODE_USDT = 'USDT';

    const STATUS_DISABLE = 0;
    const STATUS_ENABLE = 1;

    const RESPONSE_QRCODE = 1;
    const RESPONSE_URL = 2;
    const RESPONSE_BANK_CARD = 3;
    const RESPONSE_FORM = 4;

    const NOTE_GROCERIES = 1;
    const NOTE_TREASURE = 2;

    const TYPE_DEPOSIT_WITHDRAW = 1;
    const TYPE_DEPOSIT_ONLY = 2;
    const TYPE_WITHDRAW_ONLY = 3;
    // ... rest unchanged
```

**Step 2: 確認無語法錯誤**

Run: `cd /Users/apple/projects/morgan/ustd/api && php -l app/Models/Channel.php`
Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add api/app/Models/Channel.php
git commit -m "cleanup: remove all non-USDT CODE_* constants and scanQrcodeUrlScheme from Channel model"
```

---

### Task 2: 移除 UserChannelAccount 未使用的 DETAIL_KEY 常數

**Files:**
- Modify: `api/app/Models/UserChannelAccount.php:51,54`

**Step 1: 移除未使用的常數**

在 `UserChannelAccount.php` 中刪除：
- Line 51: `const DETAIL_KEY_BANK_ID = 'bank_id';`
- Line 54: `const DETAIL_KEY_ALIPAY_BANK_CODE = 'alipay_bank_code';`

**Step 2: 確認無語法錯誤**

Run: `cd /Users/apple/projects/morgan/ustd/api && php -l app/Models/UserChannelAccount.php`
Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add api/app/Models/UserChannelAccount.php
git commit -m "cleanup: remove unused DETAIL_KEY_BANK_ID and DETAIL_KEY_ALIPAY_BANK_CODE"
```

---

### Task 3: 重構 MatchedJsonResponse — 移除所有舊通道判斷

**Files:**
- Modify: `api/app/Http/Controllers/ThirdParty/MatchedJsonResponse.php:50-99`

**Step 1: 重構 responseOf 方法中的通道判斷**

目前 `responseOf()` 有三段 if 判斷（QR 通道、BANK_CARD、USDT），移除前兩段，僅保留 USDT：

刪除 line 39 的 MAYA cashier URL 判斷，改為通用 cashier URL：
```php
$cashierUrl = urldecode(
    route("api.v1.cashier", $transaction->system_order_number)
);
```

刪除 line 50-81 整段 QR code if 判斷區塊。

刪除 line 83-92 整段 BANK_CARD if 判斷區塊。

保留 line 94-99 的 USDT 區塊，但改用新的 detail key：
```php
if ($channel->code === Channel::CODE_USDT) {
    $info['wallet_address'] =
        data_get($transaction->from_channel_account, 'wallet_address', '');
    $info['chain_network'] =
        data_get($transaction->from_channel_account, 'chain_network', '');
}
```

同時移除未使用的 import：
- Line 9: `use App\Models\UserChannelAccount;` (如果 USDT 區塊不再用 DETAIL_KEY 常數)
- Line 15: `use Illuminate\Support\Str;`

移除 `qrCodeS3Path()` 私有方法（line 111-124），因為不再有 QR 通道。

**Step 2: 確認無語法錯誤**

Run: `cd /Users/apple/projects/morgan/ustd/api && php -l app/Http/Controllers/ThirdParty/MatchedJsonResponse.php`
Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add api/app/Http/Controllers/ThirdParty/MatchedJsonResponse.php
git commit -m "cleanup: remove all non-USDT channel logic from MatchedJsonResponse"
```

---

### Task 4: 簡化 CreateTransactionController — 移除 getChannelCode

**Files:**
- Modify: `api/app/Http/Controllers/CreateTransactionController.php:175-198`

**Step 1: 簡化 getChannelCode 方法**

目前方法將多個 Alipay/WeChat 變體正規化為基礎代碼。移除所有舊通道映射，簡化為：
```php
private function getChannelCode(Channel $channel, Transaction $transaction): string
{
    return strtolower($channel->code);
}
```

同時移除檔案中對已刪除 Channel 常數的 import（如有）。

**Step 2: 確認無語法錯誤**

Run: `cd /Users/apple/projects/morgan/ustd/api && php -l app/Http/Controllers/CreateTransactionController.php`
Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add api/app/Http/Controllers/CreateTransactionController.php
git commit -m "cleanup: simplify getChannelCode, remove old channel code normalization"
```

---

### Task 5: 移除 AccountMatchingQueryBuilder 中的 Alipay 特殊分組

**Files:**
- Modify: `api/app/Services/Transaction/AccountMatchingQueryBuilder.php:154-159`

**Step 1: 移除 isAlipay 分組邏輯**

在 `applyPayingTransactionsRestriction()` 中，刪除 `$isAlipay` 判斷和對應的 Feature Toggle 分支。

將 line 154-163 改為統一使用通用的 Feature Toggle：
```php
if (!request()->input('match_last_account') && !$this->featureToggleRepository->enabled(FeatureToggle::CANCEL_PAUFEN_MECHANISM)) {
    $featureToggle = FeatureToggle::ALLOW_USER_CHANNEL_CONCURRENT_FOR_SAME_AMOUNT;

    $query->whereDoesntHave('devicePayingTransactions.transaction', function ($q) use ($transaction, $channel, $featureToggle) {
```

即刪除 line 154-158 (`$isAlipay` 和 in_array) 以及 line 161-163 的三元運算，直接使用 `FeatureToggle::ALLOW_USER_CHANNEL_CONCURRENT_FOR_SAME_AMOUNT`。

**Step 2: 確認無語法錯誤**

Run: `cd /Users/apple/projects/morgan/ustd/api && php -l app/Services/Transaction/AccountMatchingQueryBuilder.php`
Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add api/app/Services/Transaction/AccountMatchingQueryBuilder.php
git commit -m "cleanup: remove Alipay-specific grouping from AccountMatchingQueryBuilder"
```

---

### Task 6: 清理 CreateTransactionService — 移除 DC_BANK 和 RE_ALIPAY 邏輯

**Files:**
- Modify: `api/app/Services/Transaction/CreateTransactionService.php:280-301`

**Step 1: 移除 USDT 匯率取得（不再需要）、DC_BANK 和 RE_ALIPAY 邏輯**

在 `createTransaction()` 方法中：

刪除 line 280-286（USDT 匯率取得區塊）：
```php
// 刪除整段
$usdtRate = null;
$binanceUsdtRate = null;
if ($channel->code == Channel::CODE_USDT) {
    $usdtUtil = app(UsdtUtil::class);
    $binanceUsdtRate = $usdtUtil->getRate()["rate"];
    $usdtRate = $context->usdtRate ?? $binanceUsdtRate;
}
```

刪除 line 288-291（DC_BANK 銀行名稱區塊）：
```php
// 刪除整段
$toData = [];
if ($channel->code == Channel::CODE_DC_BANK) {
    $toData["bank_name"] = $context->bankName;
}
```
改為只保留：
```php
$toData = [];
```

刪除 line 296-301 中 RE_ALIPAY 的特殊判斷：
```php
// 原本
if ($channel->note_type || $channel->code == Channel::CODE_RE_ALIPAY) {
// 改為
if ($channel->note_type) {
```

同時移除檔案頂部的 `use App\Utils\UsdtUtil;`（line 31）。

調整後續引用 `$usdtRate` 和 `$binanceUsdtRate` 的地方，改為 null：
- TransactionParams 建構時的 `usdtRate` 和 `binanceUsdtRate` 參數設為 null

**Step 2: 確認無語法錯誤**

Run: `cd /Users/apple/projects/morgan/ustd/api && php -l app/Services/Transaction/CreateTransactionService.php`
Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add api/app/Services/Transaction/CreateTransactionService.php
git commit -m "cleanup: remove UsdtUtil, DC_BANK, and RE_ALIPAY logic from CreateTransactionService"
```

---

### Task 7: 移除 TransactionValidationService 中的 DC_BANK 驗證

**Files:**
- Modify: `api/app/Services/Transaction/TransactionValidationService.php:48-53`

**Step 1: 刪除 DC_BANK 驗證區塊**

刪除 line 48-53：
```php
// DC_BANK 需要 bank_name
if ($channel->code == Channel::CODE_DC_BANK && !$context->bankName) {
    throw new TransactionValidationException(
        ThirdPartyErrorResponse::missingParameter('bank_name')
    );
}
```

**Step 2: 確認無語法錯誤**

Run: `cd /Users/apple/projects/morgan/ustd/api && php -l app/Services/Transaction/TransactionValidationService.php`
Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add api/app/Services/Transaction/TransactionValidationService.php
git commit -m "cleanup: remove DC_BANK validation from TransactionValidationService"
```

---

### Task 8: 移除 TransactionStatusService 中的 GCASH Redis 清理

**Files:**
- Modify: `api/app/Services/Transaction/TransactionStatusService.php:151-153`

**Step 1: 刪除 GCASH Redis 清理區塊**

刪除 line 151-153：
```php
if ($transaction->channel_code == Channel::CODE_GCASH) {
    Redis::del($transaction->id . ':gcash:daifu:mpin', $transaction->id . ':gcash:daifu:pay');
}
```

**Step 2: 確認無語法錯誤**

Run: `cd /Users/apple/projects/morgan/ustd/api && php -l app/Services/Transaction/TransactionStatusService.php`
Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add api/app/Services/Transaction/TransactionStatusService.php
git commit -m "cleanup: remove GCASH Redis cleanup from TransactionStatusService"
```

---

### Task 9: 移除 UsdtUtil + 清理 BaseWithdrawService 和 WithdrawService 的匯率邏輯

**Files:**
- Delete: `api/app/Utils/UsdtUtil.php`
- Modify: `api/app/Services/Withdraw/BaseWithdrawService.php:21,41,432-449`
- Modify: `api/app/Services/Withdraw/WithdrawService.php:103-120`

**Step 1: 清理 BaseWithdrawService**

1. 移除 line 21: `use App\Utils\UsdtUtil;`
2. 移除 line 41 的 constructor 參數: `protected readonly UsdtUtil $usdtUtil,`
3. 修改 `resolveUsdtRate()` 方法（line 432-440），移除 UsdtUtil 呼叫：
```php
protected function resolveUsdtRate(Request $request): ?string
{
    if ($request->input('bank_name') !== Channel::CODE_USDT) {
        return null;
    }

    return $request->input('usdt_rate');
}
```
4. 修改 `resolveBinanceUsdtRate()` 方法（line 442-449），直接回傳 null：
```php
protected function resolveBinanceUsdtRate(Request $request): ?string
{
    return null;
}
```

**Step 2: 清理 WithdrawService**

修改 `resolveUsdtRateForBankCard()` 方法（line 103-111）：
```php
private function resolveUsdtRateForBankCard(BankCard $bankCard, Request $request): ?string
{
    if ($bankCard->bank_name !== \App\Models\Channel::CODE_USDT) {
        return null;
    }

    return $request->input('usdt_rate');
}
```

修改 `resolveBinanceUsdtRateForBankCard()` 方法（line 113-120）：
```php
private function resolveBinanceUsdtRateForBankCard(BankCard $bankCard): ?string
{
    return null;
}
```

**Step 3: 刪除 UsdtUtil.php**

刪除 `api/app/Utils/UsdtUtil.php`。

**Step 4: 確認無語法錯誤**

Run:
```bash
cd /Users/apple/projects/morgan/ustd/api
php -l app/Services/Withdraw/BaseWithdrawService.php
php -l app/Services/Withdraw/WithdrawService.php
```
Expected: `No syntax errors detected` for both

**Step 5: Commit**

```bash
git add api/app/Utils/UsdtUtil.php api/app/Services/Withdraw/BaseWithdrawService.php api/app/Services/Withdraw/WithdrawService.php
git commit -m "cleanup: remove UsdtUtil, strip Binance rate calls from withdraw services"
```

---

### Task 10: 移除 UsdtController 和 UsdtRateController + 路由

**Files:**
- Delete: `api/app/Http/Controllers/ThirdParty/UsdtController.php`
- Delete: `api/app/Http/Controllers/Merchant/UsdtRateController.php`
- Modify: `api/routes/api-v1.php:667-669,688`

**Step 1: 移除路由**

在 `routes/api-v1.php` 中：
- 刪除 line 667-669（merchant getUsdtRate route）
- 刪除 line 688（third-party rate route）

**Step 2: 刪除控制器檔案**

- 刪除 `api/app/Http/Controllers/ThirdParty/UsdtController.php`
- 刪除 `api/app/Http/Controllers/Merchant/UsdtRateController.php`

**Step 3: 確認路由無語法錯誤**

Run: `cd /Users/apple/projects/morgan/ustd/api && php -l routes/api-v1.php`
Expected: `No syntax errors detected`

**Step 4: Commit**

```bash
git add -A api/app/Http/Controllers/ThirdParty/UsdtController.php api/app/Http/Controllers/Merchant/UsdtRateController.php api/routes/api-v1.php
git commit -m "cleanup: remove UsdtController, UsdtRateController and their routes"
```

---

### Task 11: 清理其餘散落引用

**Files:**
- Modify: `api/app/Http/Controllers/Admin/UserChannelAccountController.php:651-678`
- Modify: `api/app/Http/Controllers/Provider/TransactionController.php:234-282`
- Modify: `api/app/Http/Resources/Admin/Transaction.php:155-173`
- Modify: `api/app/Http/Resources/Provider/Transaction.php:131-139`
- Modify: `api/app/Utils/TransactionNoteUtil.php:95-99`
- Modify: `api/app/Utils/TransactionFactory.php:536-550`
- Modify: `api/app/Console/Commands/DisableTimeLimitUserChannelAccount.php:75-77`

**Step 1: UserChannelAccountController — 移除 sync 方法中 MAYA/GCASH 邏輯**

刪除整個 `sync()` 方法 body（line 651-678），因為 MAYA 和 GCASH 帳號同步不再需要。改為：
```php
public function sync(Request $request)
{
    return response()->json(null, Response::HTTP_NO_CONTENT);
}
```
移除檔案頂部 `use App\Jobs\SyncGcashAccount;` 和 `use App\Jobs\SyncMayaAccountJob;` import。

**Step 2: Provider/TransactionController — 移除 updatePassword 和 updateQRcode**

刪除 `updatePassword()` 方法（line 234-256）— 紅包密碼功能。
刪除 `updateQRcode()` 方法（line 258 往後）— QQ 面對面紅包 QR code 上傳。

> 注意：同時需要移除這兩個方法對應的路由（在 `routes/api-v1.php` 中搜尋 `updatePassword` 和 `updateQRcode`）。

**Step 3: Admin/Transaction Resource — 簡化 getProviderAccountVendorName**

將 `getProviderAccountVendorName()` 方法（line 155-173）簡化：
```php
private function getProviderAccountVendorName()
{
    if ($this->channel_code === Channel::CODE_USDT) {
        return 'USDT';
    }
    return data_get($this->from_channel_account, UserChannelAccount::DETAIL_KEY_BANK_NAME);
}
```
注意：此檔案中引用了 `\App\Model\Channel`（可能是舊的 namespace，需確認是 `\App\Models\Channel`）。

**Step 4: Provider/Transaction Resource — 移除紅包確認邏輯**

在 `getConfirmable()` 方法（line 131-139），移除 RE_ALIPAY 判斷：
```php
private function getConfirmable()
{
    return (
        !$this->locked
        && $this->from->isSelfOrDescendantOf(auth()->user())
        && $this->status === \App\Models\Transaction::STATUS_PAYING
        && $this->type === \App\Models\Transaction::TYPE_PAUFEN_TRANSACTION
    );
}
```

**Step 5: TransactionNoteUtil — 移除 RE_ALIPAY 特殊判斷**

在 `randomNote()` 方法（line 95-99）刪除 RE_ALIPAY 判斷：
```php
public function randomNote($amount, $channel): string
{
    // 刪除以下兩行：
    // if ($channel->code == Channel::CODE_RE_ALIPAY) {
    //     return $this->reNotes->random();
    // }

    if ($channel->note_type == Channel::NOTE_GROCERIES) {
```

**Step 6: TransactionFactory — 移除 MAYA extra_withdraw_fee 邏輯**

在 `paufenDepositToAccount()` 方法中（line 536-550），移除 MAYA 特殊判斷。將整段 if/else 簡化：
```php
$fromChannelAccount["extra_withdraw_fee"] = 0;
```
因為不再需要根據通道代碼決定 extra_withdraw_fee。

**Step 7: DisableTimeLimitUserChannelAccount — 移除 whereIn 通道過濾**

在 line 75-76，移除 `whereIn('channel_code', [...])` 限制，因為不再需要只針對 ALIPAY_BANK 和 BANK_CARD：
```php
// 改為不過濾 channel_code，或根據實際需求保留
$baseUserChannelAccountQuery = UserChannelAccount::withTrashed()
    ->when($this->userChannelAccount, function (Builder $builder) {
        $builder->where('id', $this->userChannelAccount->getKey());
    });
```
（移除 `->whereHas('channelAmount', ...)` 整個 chain）

**Step 8: 確認所有修改的檔案無語法錯誤**

Run:
```bash
cd /Users/apple/projects/morgan/ustd/api
php -l app/Http/Controllers/Admin/UserChannelAccountController.php
php -l app/Http/Controllers/Provider/TransactionController.php
php -l app/Http/Resources/Admin/Transaction.php
php -l app/Http/Resources/Provider/Transaction.php
php -l app/Utils/TransactionNoteUtil.php
php -l app/Utils/TransactionFactory.php
php -l app/Console/Commands/DisableTimeLimitUserChannelAccount.php
```
Expected: `No syntax errors detected` for all

**Step 9: Commit**

```bash
git add api/app/Http/Controllers/Admin/UserChannelAccountController.php \
  api/app/Http/Controllers/Provider/TransactionController.php \
  api/app/Http/Resources/Admin/Transaction.php \
  api/app/Http/Resources/Provider/Transaction.php \
  api/app/Utils/TransactionNoteUtil.php \
  api/app/Utils/TransactionFactory.php \
  api/app/Console/Commands/DisableTimeLimitUserChannelAccount.php
git commit -m "cleanup: remove all remaining old channel code references from controllers, resources, and utils"
```

---

### Task 12: 更新 ChannelSeeder

**Files:**
- Modify: `api/database/seeds/ChannelSeeder.php`

**Step 1: 替換 seeder 內容**

將整個 seeder 改為只 seed USDT 通道：
```php
public function run()
{
    DB::table('channels')->insertOrIgnore([
        'code' => Channel::CODE_USDT,
        'name' => 'USDT',
        'status' => true,
        'type' => Channel::TYPE_DEPOSIT_WITHDRAW,
        'order_timeout' => 30,
        'order_timeout_enable' => true,
        'transaction_timeout' => 30,
        'transaction_timeout_enable' => true,
        'floating' => 0,
        'floating_enable' => false,
        'present_result' => Channel::RESPONSE_FORM,
    ]);
}
```

**Step 2: 確認無語法錯誤**

Run: `cd /Users/apple/projects/morgan/ustd/api && php -l database/seeds/ChannelSeeder.php`
Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add api/database/seeds/ChannelSeeder.php
git commit -m "cleanup: update ChannelSeeder to only seed USDT channel"
```

---

### Task 13: Phase 1 全域搜尋驗證

**Step 1: 搜尋殘留的 CODE_ 引用（排除 CODE_USDT）**

Run:
```bash
cd /Users/apple/projects/morgan/ustd/api
grep -rn "Channel::CODE_" --include="*.php" app/ database/seeds/ routes/ | grep -v "CODE_USDT" | grep -v "vendor/"
```
Expected: 空結果（無殘留引用）

**Step 2: 搜尋 UsdtUtil 殘留引用**

Run:
```bash
grep -rn "UsdtUtil" --include="*.php" app/ routes/ | grep -v "vendor/"
```
Expected: 空結果

**Step 3: PHP 語法檢查整個 app 目錄**

Run:
```bash
find app/ -name "*.php" -exec php -l {} \; 2>&1 | grep -v "No syntax errors"
```
Expected: 空結果

**Step 4: 如有殘留，逐一修復後 commit**

```bash
git add -A && git commit -m "cleanup: fix remaining old channel code references"
```

---

## Phase 2: USDT 基礎設施

### Task 14: 新增 UserChannelAccount DETAIL_KEY 常數

**Files:**
- Modify: `api/app/Models/UserChannelAccount.php:55`

**Step 1: 新增 USDT detail key 常數**

在現有 DETAIL_KEY 常數之後新增：
```php
const DETAIL_KEY_WALLET_ADDRESS = 'wallet_address';  // USDT 收款地址
const DETAIL_KEY_CHAIN_NETWORK = 'chain_network';    // 鏈網絡: trc20, erc20, bep20
```

**Step 2: 確認無語法錯誤**

Run: `cd /Users/apple/projects/morgan/ustd/api && php -l app/Models/UserChannelAccount.php`
Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add api/app/Models/UserChannelAccount.php
git commit -m "feat: add USDT wallet_address and chain_network detail keys to UserChannelAccount"
```

---

### Task 15: Migration — Transaction 表新增 chain_network 和 tx_hash

**Files:**
- Create: `api/database/migrations/2026_02_23_000001_add_chain_network_and_tx_hash_to_transactions.php`

**Step 1: 建立 migration 檔案**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('chain_network', 10)->nullable()->after('usdt_rate');
            $table->string('tx_hash', 100)->nullable()->after('chain_network');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['chain_network', 'tx_hash']);
        });
    }
};
```

**Step 2: 確認無語法錯誤**

Run: `cd /Users/apple/projects/morgan/ustd/api && php -l database/migrations/2026_02_23_000001_add_chain_network_and_tx_hash_to_transactions.php`
Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add api/database/migrations/2026_02_23_000001_add_chain_network_and_tx_hash_to_transactions.php
git commit -m "feat: add chain_network and tx_hash columns to transactions table"
```

---

### Task 16: Migration — 建立 usdt_deposit_monitors 表

**Files:**
- Create: `api/database/migrations/2026_02_23_000002_create_usdt_deposit_monitors_table.php`

**Step 1: 建立 migration 檔案**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usdt_deposit_monitors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id')->unique();
            $table->unsignedBigInteger('user_channel_account_id');
            $table->string('address', 100)->index();
            $table->string('chain_network', 10);
            $table->decimal('expected_amount', 20, 6);
            $table->decimal('received_amount', 20, 6)->default(0);
            $table->string('tx_hash', 100)->nullable();
            $table->tinyInteger('status')->default(0)->comment('0=pending 1=matched 2=confirmed 3=expired');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamps();

            $table->foreign('transaction_id')->references('id')->on('transactions');
            $table->foreign('user_channel_account_id')->references('id')->on('user_channel_accounts');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usdt_deposit_monitors');
    }
};
```

**Step 2: 確認無語法錯誤**

Run: `cd /Users/apple/projects/morgan/ustd/api && php -l database/migrations/2026_02_23_000002_create_usdt_deposit_monitors_table.php`
Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add api/database/migrations/2026_02_23_000002_create_usdt_deposit_monitors_table.php
git commit -m "feat: create usdt_deposit_monitors table for on-chain polling"
```

---

### Task 17: 建立 UsdtDepositMonitor Model

**Files:**
- Create: `api/app/Models/UsdtDepositMonitor.php`

**Step 1: 建立 Model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsdtDepositMonitor extends Model
{
    const STATUS_PENDING = 0;
    const STATUS_MATCHED = 1;
    const STATUS_CONFIRMED = 2;
    const STATUS_EXPIRED = 3;

    protected $fillable = [
        'transaction_id',
        'user_channel_account_id',
        'address',
        'chain_network',
        'expected_amount',
        'received_amount',
        'tx_hash',
        'status',
        'expires_at',
        'matched_at',
        'confirmed_at',
        'last_polled_at',
    ];

    protected $casts = [
        'expected_amount' => 'decimal:6',
        'received_amount' => 'decimal:6',
        'expires_at' => 'datetime',
        'matched_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'last_polled_at' => 'datetime',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function userChannelAccount()
    {
        return $this->belongsTo(UserChannelAccount::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }
}
```

**Step 2: 確認無語法錯誤**

Run: `cd /Users/apple/projects/morgan/ustd/api && php -l app/Models/UsdtDepositMonitor.php`
Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add api/app/Models/UsdtDepositMonitor.php
git commit -m "feat: add UsdtDepositMonitor model"
```

---

## Phase 3: 鏈上監控 — 收款上分

### Task 18: 建立 ChainAdapterInterface 和 ChainTransaction DTO

**Files:**
- Create: `api/app/Services/Crypto/Adapters/ChainAdapterInterface.php`
- Create: `api/app/Services/Crypto/DTO/ChainTransaction.php`

**Step 1: 建立介面**

```php
<?php

namespace App\Services\Crypto\Adapters;

use App\Services\Crypto\DTO\ChainTransaction;
use Illuminate\Support\Collection;

interface ChainAdapterInterface
{
    /**
     * 取得指定地址最近的 USDT 轉入交易
     *
     * @return Collection<ChainTransaction>
     */
    public function fetchIncomingTransactions(string $address, ?string $sinceTimestamp = null): Collection;
}
```

**Step 2: 建立 DTO**

```php
<?php

namespace App\Services\Crypto\DTO;

class ChainTransaction
{
    public function __construct(
        public readonly string $txHash,
        public readonly string $from,
        public readonly string $to,
        public readonly string $amount,
        public readonly int $timestamp,
        public readonly int $confirmations,
    ) {}
}
```

**Step 3: 確認無語法錯誤**

Run:
```bash
cd /Users/apple/projects/morgan/ustd/api
php -l app/Services/Crypto/Adapters/ChainAdapterInterface.php
php -l app/Services/Crypto/DTO/ChainTransaction.php
```
Expected: `No syntax errors detected` for both

**Step 4: Commit**

```bash
git add api/app/Services/Crypto/
git commit -m "feat: add ChainAdapterInterface and ChainTransaction DTO"
```

---

### Task 19: 實作 Trc20Adapter

**Files:**
- Create: `api/app/Services/Crypto/Adapters/Trc20Adapter.php`

**Step 1: 實作 TronGrid TRC-20 Polling**

```php
<?php

namespace App\Services\Crypto\Adapters;

use App\Services\Crypto\DTO\ChainTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Trc20Adapter implements ChainAdapterInterface
{
    // USDT TRC-20 合約地址 (Mainnet)
    private const USDT_CONTRACT = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
    private const TRONGRID_BASE_URL = 'https://api.trongrid.io';

    public function fetchIncomingTransactions(string $address, ?string $sinceTimestamp = null): Collection
    {
        try {
            $params = [
                'only_to' => 'true',
                'contract_address' => self::USDT_CONTRACT,
                'limit' => 20,
                'order_by' => 'block_timestamp,desc',
            ];

            if ($sinceTimestamp) {
                $params['min_timestamp'] = $sinceTimestamp;
            }

            $response = Http::timeout(10)
                ->get(self::TRONGRID_BASE_URL . "/v1/accounts/{$address}/transactions/trc20", $params);

            if (!$response->successful()) {
                Log::warning('Trc20Adapter: TronGrid API failed', [
                    'address' => $address,
                    'status' => $response->status(),
                ]);
                return collect();
            }

            $data = $response->json('data', []);

            return collect($data)
                ->filter(fn ($tx) => ($tx['token_info']['address'] ?? '') === self::USDT_CONTRACT)
                ->map(function ($tx) {
                    $decimals = (int) ($tx['token_info']['decimals'] ?? 6);
                    $rawAmount = $tx['value'] ?? '0';
                    $amount = bcdiv($rawAmount, bcpow('10', (string) $decimals), 6);

                    return new ChainTransaction(
                        txHash: $tx['transaction_id'],
                        from: $tx['from'],
                        to: $tx['to'],
                        amount: $amount,
                        timestamp: (int) ($tx['block_timestamp'] ?? 0),
                        confirmations: 1, // TronGrid 回傳的交易已確認
                    );
                });
        } catch (\Exception $e) {
            Log::error('Trc20Adapter: Exception', [
                'address' => $address,
                'exception' => $e->getMessage(),
            ]);
            return collect();
        }
    }
}
```

**Step 2: 確認無語法錯誤**

Run: `cd /Users/apple/projects/morgan/ustd/api && php -l app/Services/Crypto/Adapters/Trc20Adapter.php`
Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add api/app/Services/Crypto/Adapters/Trc20Adapter.php
git commit -m "feat: implement Trc20Adapter for TronGrid TRC-20 USDT polling"
```

---

### Task 20: 實作 CryptoMonitorService

**Files:**
- Create: `api/app/Services/Crypto/CryptoMonitorService.php`

**Step 1: 建立核心 Polling 服務**

```php
<?php

namespace App\Services\Crypto;

use App\Models\Transaction;
use App\Models\UsdtDepositMonitor;
use App\Services\Crypto\Adapters\ChainAdapterInterface;
use App\Services\Crypto\Adapters\Trc20Adapter;
use App\Services\Transaction\TransactionStatusService;
use App\Utils\BCMathUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CryptoMonitorService
{
    public function __construct(
        private readonly TransactionStatusService $transactionStatusService,
        private readonly BCMathUtil $bcMath,
    ) {}

    public function poll(): void
    {
        $pendingMonitors = UsdtDepositMonitor::pending()
            ->with(['transaction', 'userChannelAccount'])
            ->get();

        if ($pendingMonitors->isEmpty()) {
            return;
        }

        // 按 chain_network 分組處理
        $grouped = $pendingMonitors->groupBy('chain_network');

        foreach ($grouped as $network => $monitors) {
            $adapter = $this->resolveAdapter($network);
            if (!$adapter) {
                Log::warning("CryptoMonitorService: No adapter for network {$network}");
                continue;
            }

            // 按地址分組，減少 API 呼叫次數
            $byAddress = $monitors->groupBy('address');

            foreach ($byAddress as $address => $addressMonitors) {
                $this->pollAddress($adapter, $address, $addressMonitors);
            }
        }

        // 處理過期的監控記錄
        $this->expireTimedOutMonitors();
    }

    private function pollAddress(ChainAdapterInterface $adapter, string $address, $monitors): void
    {
        $chainTxs = $adapter->fetchIncomingTransactions($address);

        if ($chainTxs->isEmpty()) {
            UsdtDepositMonitor::where('address', $address)
                ->where('status', UsdtDepositMonitor::STATUS_PENDING)
                ->update(['last_polled_at' => now()]);
            return;
        }

        foreach ($monitors as $monitor) {
            foreach ($chainTxs as $chainTx) {
                if ($this->isAmountMatch($monitor->expected_amount, $chainTx->amount)) {
                    // 檢查此 tx_hash 是否已被其他 monitor 使用
                    $alreadyUsed = UsdtDepositMonitor::where('tx_hash', $chainTx->txHash)->exists();
                    if ($alreadyUsed) {
                        continue;
                    }

                    $this->confirmDeposit($monitor, $chainTx);
                    break; // 此 monitor 已匹配，跳到下一個
                }
            }

            $monitor->update(['last_polled_at' => now()]);
        }
    }

    private function isAmountMatch(string $expected, string $actual): bool
    {
        // 精確匹配（6 位小數）
        return bccomp($expected, $actual, 6) === 0;
    }

    private function confirmDeposit(UsdtDepositMonitor $monitor, $chainTx): void
    {
        DB::transaction(function () use ($monitor, $chainTx) {
            $monitor->update([
                'status' => UsdtDepositMonitor::STATUS_CONFIRMED,
                'received_amount' => $chainTx->amount,
                'tx_hash' => $chainTx->txHash,
                'matched_at' => now(),
                'confirmed_at' => now(),
            ]);

            $transaction = Transaction::lockForUpdate()->find($monitor->transaction_id);

            if ($transaction && $transaction->status === Transaction::STATUS_PAYING) {
                $transaction->update([
                    'tx_hash' => $chainTx->txHash,
                    'chain_network' => $monitor->chain_network,
                ]);

                $this->transactionStatusService->markAsSuccess($transaction);

                Log::info('CryptoMonitorService: Deposit confirmed', [
                    'transaction_id' => $transaction->id,
                    'tx_hash' => $chainTx->txHash,
                    'amount' => $chainTx->amount,
                ]);
            }
        });
    }

    private function expireTimedOutMonitors(): void
    {
        UsdtDepositMonitor::where('status', UsdtDepositMonitor::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => UsdtDepositMonitor::STATUS_EXPIRED]);
    }

    private function resolveAdapter(string $network): ?ChainAdapterInterface
    {
        return match ($network) {
            'trc20' => app(Trc20Adapter::class),
            // 'erc20' => app(Erc20Adapter::class),  // 後續
            // 'bep20' => app(Bep20Adapter::class),  // 後續
            default => null,
        };
    }
}
```

**Step 2: 確認無語法錯誤**

Run: `cd /Users/apple/projects/morgan/ustd/api && php -l app/Services/Crypto/CryptoMonitorService.php`
Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add api/app/Services/Crypto/CryptoMonitorService.php
git commit -m "feat: implement CryptoMonitorService for on-chain USDT deposit polling"
```

---

### Task 21: 建立 PollUsdtDeposits 排程命令

**Files:**
- Create: `api/app/Console/Commands/PollUsdtDeposits.php`
- Modify: `api/app/Console/Kernel.php:72-74`

**Step 1: 建立 Artisan Command**

```php
<?php

namespace App\Console\Commands;

use App\Services\Crypto\CryptoMonitorService;
use Illuminate\Console\Command;

class PollUsdtDeposits extends Command
{
    protected $signature = 'usdt:poll-deposits';
    protected $description = 'Poll blockchain for incoming USDT deposits';

    public function handle(CryptoMonitorService $service): int
    {
        $service->poll();
        return self::SUCCESS;
    }
}
```

**Step 2: 加入排程（ShortSchedule — 每 30 秒）**

在 `Kernel.php` 的 `shortSchedule()` 方法中加入：
```php
protected function shortSchedule(ShortSchedule $shortSchedule)
{
    $shortSchedule->command('usdt:poll-deposits')->everySeconds(30);
}
```

**Step 3: 確認無語法錯誤**

Run:
```bash
cd /Users/apple/projects/morgan/ustd/api
php -l app/Console/Commands/PollUsdtDeposits.php
php -l app/Console/Kernel.php
```
Expected: `No syntax errors detected` for both

**Step 4: Commit**

```bash
git add api/app/Console/Commands/PollUsdtDeposits.php api/app/Console/Kernel.php
git commit -m "feat: add PollUsdtDeposits command with 30-second short schedule"
```

---

### Task 22: 整合收款流程 — CreateTransactionService 建立 Monitor 記錄

**Files:**
- Modify: `api/app/Services/Transaction/CreateTransactionService.php`

**Step 1: 在交易匹配成功後建立 monitor 記錄**

找到 `paufenTransactionFrom()`（或匹配成功後設定 STATUS_PAYING 的地方），在交易狀態變為 STATUS_PAYING 後新增：

```php
use App\Models\UsdtDepositMonitor;
use App\Models\UserChannelAccount;

// 在匹配成功後（STATUS_PAYING），如果是 USDT 通道，建立 monitor
if ($transaction->channel_code === Channel::CODE_USDT) {
    $account = UserChannelAccount::find($transaction->from_channel_account_id);
    if ($account) {
        UsdtDepositMonitor::create([
            'transaction_id' => $transaction->id,
            'user_channel_account_id' => $account->id,
            'address' => data_get($account->detail, UserChannelAccount::DETAIL_KEY_WALLET_ADDRESS, ''),
            'chain_network' => data_get($account->detail, UserChannelAccount::DETAIL_KEY_CHAIN_NETWORK, 'trc20'),
            'expected_amount' => $transaction->floating_amount,
            'status' => UsdtDepositMonitor::STATUS_PENDING,
            'expires_at' => $transaction->channel->transaction_timeout_enable
                ? now()->addMinutes($transaction->channel->transaction_timeout)
                : null,
        ]);
    }
}
```

> 注意：需要根據實際的匹配流程位置決定在哪裡插入此邏輯。最可能的位置是 `attemptMatching()` 成功後，或 `matchWithLocalProvider()` 成功後。

**Step 2: 確認無語法錯誤**

Run: `cd /Users/apple/projects/morgan/ustd/api && php -l app/Services/Transaction/CreateTransactionService.php`
Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add api/app/Services/Transaction/CreateTransactionService.php
git commit -m "feat: create UsdtDepositMonitor record when USDT transaction enters PAYING status"
```

---

## Phase 4: 代付流程清理

### Task 23: 清理代付流程 — 移除 USDT 匯率相關邏輯

**Files:**
- Modify: `api/app/Services/Withdraw/DTO/WithdrawContext.php:25-26`
- Modify: `api/app/Http/Resources/ThirdParty/Transaction.php:49-52`
- Modify: `api/app/Jobs/NotifyTransaction.php:98-101`

**Step 1: WithdrawContext — 移除 usdt_rate 相關屬性**

因為不再需要匯率功能，移除 `usdtRate` 和 `binanceUsdtRate` 參數：

```php
public function __construct(
    public readonly User $merchant,
    public readonly Wallet $wallet,
    public readonly string $amount,
    public readonly BankCardTransferObject $bankCard,
    public readonly string $orderNumber,
    public readonly ?string $notifyUrl,
    public readonly string $source,
) {}
```

同時更新所有建構 `WithdrawContext` 的地方，移除 `usdtRate` 和 `binanceUsdtRate` 參數。

> 注意：`isUsdt()` 方法保留，因為代付時仍需判斷是否為 USDT 通道。

**Step 2: ThirdParty/Transaction Resource — 移除 USDT 匯率回傳**

移除 line 49-52 的 USDT 條件判斷：
```php
// 刪除
if ($this->channel_code == Channel::CODE_USDT) {
    $data['usdt_rate'] = $this->usdt_rate;
    $data['rate_amount'] = $this->rateAmount;
}
```

**Step 3: NotifyTransaction Job — 移除 USDT 匯率通知**

移除 line 98-101 的 USDT 條件判斷：
```php
// 刪除
if ($this->transaction->channel_code == Channel::CODE_USDT) {
    $mainData['usdt_rate'] = $this->transaction->usdt_rate;
    $mainData['rate_amount'] = $this->transaction->rate_amount;
}
```

**Step 4: 確認無語法錯誤**

Run:
```bash
cd /Users/apple/projects/morgan/ustd/api
php -l app/Services/Withdraw/DTO/WithdrawContext.php
php -l app/Http/Resources/ThirdParty/Transaction.php
php -l app/Jobs/NotifyTransaction.php
```
Expected: `No syntax errors detected` for all

**Step 5: Commit**

```bash
git add api/app/Services/Withdraw/DTO/WithdrawContext.php \
  api/app/Http/Resources/ThirdParty/Transaction.php \
  api/app/Jobs/NotifyTransaction.php
git commit -m "cleanup: remove USDT rate logic from WithdrawContext, ThirdParty response, and notification job"
```

---

### Task 24: 最終全域驗證

**Step 1: 搜尋所有殘留的舊通道引用**

Run:
```bash
cd /Users/apple/projects/morgan/ustd/api
grep -rn "CODE_ALIPAY\|CODE_BANK_CARD\|CODE_GCASH\|CODE_MAYA\|CODE_DC_\|CODE_RE_\|CODE_QR_\|CODE_WECHATPAY\|CODE_UNION\|CODE_ECNY\|CODE_PHONE\|CODE_CRYSTAL\|CODE_ELITE\|UsdtUtil" --include="*.php" app/ routes/ database/seeds/ | grep -v "vendor/"
```
Expected: 空結果

**Step 2: PHP 語法全域檢查**

Run:
```bash
cd /Users/apple/projects/morgan/ustd/api
find app/ routes/ database/seeds/ -name "*.php" -exec php -l {} \; 2>&1 | grep -v "No syntax errors"
```
Expected: 空結果

**Step 3: 確認新服務的結構完整性**

Run:
```bash
cd /Users/apple/projects/morgan/ustd/api
php -l app/Models/UsdtDepositMonitor.php
php -l app/Services/Crypto/CryptoMonitorService.php
php -l app/Services/Crypto/Adapters/Trc20Adapter.php
php -l app/Services/Crypto/Adapters/ChainAdapterInterface.php
php -l app/Services/Crypto/DTO/ChainTransaction.php
php -l app/Console/Commands/PollUsdtDeposits.php
```
Expected: `No syntax errors detected` for all

**Step 4: 最終 commit（如有修復）**

```bash
git add -A && git commit -m "feat: USDT channel redesign complete — Phase 1-4"
```
