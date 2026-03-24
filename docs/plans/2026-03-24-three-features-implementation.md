# Three Features Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement three features: (1) parent address search filter, (2) batch transfer gas consolidation, (3) system setting toggles for count columns.

**Architecture:** Feature 1 adds `parent_account` filter to 3 backend builders + 3 frontend filter forms. Feature 2 extends `BatchTransferUsdt` job to transfer remaining native tokens after USDT. Feature 3 adds 2 new FeatureToggles (IDs 55, 56) and wires them to column visibility.

**Tech Stack:** PHP/Laravel, React/Refine/Ant Design, TypeScript

---

## Task 1: Parent Address Filter — Backend (UserChannelAccount Builder)

**Files:**
- Modify: `api/app/Builders/UserChannelAccount.php:128` (after `receive_status` filter)
- Modify: `api/app/Http/Controllers/Admin/UserChannelAccountController.php:86` (add validation)

**Step 1: Add `parent_account` filter to Builder**

In `api/app/Builders/UserChannelAccount.php`, add after line 128 (the `receive_status` filter):

```php
$userChannelAccounts->when($request->filled('parent_account'), function ($builder) use ($request) {
    $parentIds = UserChannelAccountModel::where('address_type', UserChannelAccountModel::ADDRESS_TYPE_MASTER)
        ->where('account', 'like', "%{$request->parent_account}%")
        ->pluck('id');

    $builder->where(function ($q) use ($parentIds) {
        $q->whereIn('parent_account_id', $parentIds)
          ->orWhereIn('id', $parentIds);
    });
});
```

**Step 2: Add validation in Controller**

In `api/app/Http/Controllers/Admin/UserChannelAccountController.php`, add to the validation rules at line 86:

```php
"parent_account" => "nullable|string",
```

**Step 3: Verify**

Run: `cd api && php artisan route:list --path=user-channel-accounts`
Expected: routes exist, no errors.

**Step 4: Commit**

```bash
git add api/app/Builders/UserChannelAccount.php api/app/Http/Controllers/Admin/UserChannelAccountController.php
git commit -m "feat: add parent_account filter to UserChannelAccount builder"
```

---

## Task 2: Parent Address Filter — Backend (Transaction Builder)

**Files:**
- Modify: `api/app/Builders/Transaction.php` — `transactions()` method and `withdraws()` method

**Step 1: Add `parent_account` filter to `transactions()` method (收款單)**

In the `transactions()` method, add after the existing `account` filter (around line 173):

```php
$transactions->when($request->filled('parent_account'), function ($builder) use ($request) {
    $parentIds = UserChannelAccount::where('address_type', UserChannelAccount::ADDRESS_TYPE_MASTER)
        ->where('account', 'like', "%{$request->parent_account}%")
        ->pluck('id');

    $accountIds = UserChannelAccount::whereIn('parent_account_id', $parentIds)
        ->orWhereIn('id', $parentIds)
        ->pluck('id');

    $builder->whereIn('from_channel_account_id', $accountIds);
});
```

**Step 2: Add `parent_account` filter to `withdraws()` method (付款單)**

In the `withdraws()` method, add after the existing `account` filter (around line 421):

```php
$withdraws->when($request->filled('parent_account'), function ($builder) use ($request) {
    $parentIds = UserChannelAccount::where('address_type', UserChannelAccount::ADDRESS_TYPE_MASTER)
        ->where('account', 'like', "%{$request->parent_account}%")
        ->pluck('id');

    $accountIds = UserChannelAccount::whereIn('parent_account_id', $parentIds)
        ->orWhereIn('id', $parentIds)
        ->pluck('id');

    $builder->whereIn('to_channel_account_id', $accountIds);
});
```

**Step 3: Commit**

```bash
git add api/app/Builders/Transaction.php
git commit -m "feat: add parent_account filter to transaction builders (deposit + withdraw)"
```

---

## Task 3: Parent Address Filter — Frontend (3 list pages)

**Files:**
- Modify: `apps/admin/src/pages/userChannel/list.tsx:339` (after note filter)
- Modify: `apps/admin/src/pages/transaction/collection/list.tsx` (after existing account filter)
- Modify: `apps/admin/src/pages/transaction/PayForAnother/components/FilterForm.tsx` (after existing account filter)

**Step 1: Add filter to UserChannelAccount list page**

In `apps/admin/src/pages/userChannel/list.tsx`, add after the note filter (after line 340):

```tsx
<Col xs={24} md={6}>
  <ListPageLayout.Filter.Item label={t('fields.parentAccount')} name="parent_account">
    <Input allowClear />
  </ListPageLayout.Filter.Item>
</Col>
```

**Step 2: Add filter to collection (deposit) list page**

In `apps/admin/src/pages/transaction/collection/list.tsx`, add after the account filter:

```tsx
<Col {...colProps}>
  <ListPageLayout.Filter.Item label={t('fields.parentAccount')} name="parent_account">
    <Input allowClear />
  </ListPageLayout.Filter.Item>
</Col>
```

**Step 3: Add filter to PayForAnother (withdraw) filter form**

In `apps/admin/src/pages/transaction/PayForAnother/components/FilterForm.tsx`, add after the account filter:

```tsx
<Col {...colProps}>
  <ListPageLayout.Filter.Item label={t('fields.parentAccount')} name="parent_account">
    <Input allowClear />
  </ListPageLayout.Filter.Item>
</Col>
```

**Step 4: Add i18n key**

Check existing i18n files for `parentAccount` key. It likely already exists in the userChannel namespace (used by parentAccountColumn). If not, add to relevant translation files.

**Step 5: Verify**

Run: `cd apps/admin && pnpm run typecheck`
Expected: no type errors.

**Step 6: Commit**

```bash
git add apps/admin/src/pages/userChannel/list.tsx apps/admin/src/pages/transaction/collection/list.tsx apps/admin/src/pages/transaction/PayForAnother/components/FilterForm.tsx
git commit -m "feat: add parent address search filter to account, deposit, and withdraw list pages"
```

---

## Task 4: Batch Transfer Gas Consolidation — Config

**Files:**
- Modify: `api/config/services.php:39-73`

**Step 1: Add `min_gas_transfer_amount` to each chain config**

In `api/config/services.php`:

After line 44 (inside `trongrid` array, before `]`):
```php
'min_gas_transfer_amount' => env('TRONGRID_MIN_GAS_TRANSFER', '1'),
```

After line 58 (inside `ethereum` array, before `]`):
```php
'min_gas_transfer_amount' => env('ETH_MIN_GAS_TRANSFER', '0.001'),
```

After line 72 (inside `bsc` array, before `]`):
```php
'min_gas_transfer_amount' => env('BSC_MIN_GAS_TRANSFER', '0.001'),
```

**Step 2: Commit**

```bash
git add api/config/services.php
git commit -m "feat: add min_gas_transfer_amount config for batch transfer gas consolidation"
```

---

## Task 5: Batch Transfer Gas Consolidation — Trc20Adapter Bandwidth Check

**Files:**
- Modify: `api/app/Services/Crypto/Adapters/Trc20Adapter.php` (add `getAvailableBandwidth` method)

**Step 1: Add bandwidth check method**

Add after the `estimateTransferFee` method (after line 388):

```php
/**
 * 取得帳號可用的 Bandwidth
 *
 * @return int 可用 bandwidth 點數
 */
public function getAvailableBandwidth(string $address): int
{
    $response = $this->buildHttpClient()
        ->post($this->getBaseUrl() . '/wallet/getaccountresource', [
            'address' => $this->base58ToHex($address),
            'visible' => false,
        ]);

    if (!$response->successful()) {
        return 0;
    }

    $data = $response->json();

    // 免費 bandwidth
    $freeLimit = (int) ($data['freeNetLimit'] ?? 600);
    $freeUsed = (int) ($data['freeNetUsed'] ?? 0);
    $freeAvailable = max(0, $freeLimit - $freeUsed);

    // 質押 bandwidth
    $netLimit = (int) ($data['NetLimit'] ?? 0);
    $netUsed = (int) ($data['NetUsed'] ?? 0);
    $stakedAvailable = max(0, $netLimit - $netUsed);

    return $freeAvailable + $stakedAvailable;
}

/**
 * 預估原生 TRX 轉帳所需的手續費
 *
 * @return string 預估 TRX 費用（如有足夠 bandwidth 則為 0）
 */
public function estimateNativeTransferFee(string $fromAddress): string
{
    // TRX 轉帳約需 267 bandwidth
    $requiredBandwidth = 267;
    $available = $this->getAvailableBandwidth($fromAddress);

    if ($available >= $requiredBandwidth) {
        return '0';
    }

    // bandwidth 不足時，約 0.267 TRX + buffer
    return '1';
}
```

**Step 2: Commit**

```bash
git add api/app/Services/Crypto/Adapters/Trc20Adapter.php
git commit -m "feat: add bandwidth check and native transfer fee estimation to Trc20Adapter"
```

---

## Task 6: Batch Transfer Gas Consolidation — BatchTransferUsdt Job

**Files:**
- Modify: `api/app/Jobs/BatchTransferUsdt.php:111` (after USDT transfer success, before ConfirmUsdtWithdraw dispatch)

**Step 1: Add gas consolidation logic after USDT transfer**

In `api/app/Jobs/BatchTransferUsdt.php`, replace the section from line 98 to 119 with the following expanded logic. The USDT transfer try-catch stays the same, but we add gas transfer logic after the successful USDT transfer and before the ConfirmUsdtWithdraw dispatch:

After line 113 (`$this->log($transaction, "交易已廣播，等待鏈上確認 (tx_hash: {$chainTx->txHash})");`) and before `} catch (\Throwable $e) {` on line 114:

```php
            // 歸集剩餘原生代幣到目標地址
            $this->transferRemainingGas($adapter, $source, $targetAddress, $chainNetwork, $gasTokenName, $transaction);
```

Then add the new private method at the end of the class (before the closing `}`):

```php
private function transferRemainingGas(
    $adapter,
    UserChannelAccount $source,
    string $targetAddress,
    string $chainNetwork,
    string $gasTokenName,
    Transaction $transaction,
): void {
    try {
        // 查詢剩餘原生代幣餘額
        $remainingBalance = $adapter->getNativeBalance($source->account);

        // 計算原生轉帳手續費
        $transferFee = match ($chainNetwork) {
            'trc20' => $adapter instanceof \App\Services\Crypto\Adapters\Trc20Adapter
                ? $adapter->estimateNativeTransferFee($source->account)
                : '1',
            default => $this->estimateEvmNativeTransferFee($adapter, $chainNetwork),
        };

        // 可轉出金額 = 餘額 - 手續費
        $transferable = bcsub($remainingBalance, $transferFee, 6);

        // 取得最低轉帳門檻
        $minAmount = match ($chainNetwork) {
            'trc20' => config('services.trongrid.min_gas_transfer_amount', '1'),
            'erc20' => config('services.ethereum.min_gas_transfer_amount', '0.001'),
            'bep20' => config('services.bsc.min_gas_transfer_amount', '0.001'),
            default => '0',
        };

        if (bccomp($transferable, $minAmount, 6) <= 0) {
            $this->log($transaction, "剩餘 {$gasTokenName} 不足轉出門檻 (餘額: {$remainingBalance}, 手續費: {$transferFee}, 門檻: {$minAmount})");
            return;
        }

        // 執行原生代幣轉帳
        $privateKey = decrypt(data_get($source->detail, UserChannelAccount::DETAIL_KEY_ENCRYPTED_PRIVATE_KEY));
        $gasTxHash = $adapter->sendNativeToken($source->account, $targetAddress, $transferable, $privateKey);
        $privateKey = null;

        $this->log($transaction, "已歸集 {$transferable} {$gasTokenName} 到目標地址 (tx_hash: {$gasTxHash})");
    } catch (\Throwable $e) {
        // Gas 轉帳失敗不影響 USDT 轉帳結果
        $this->log($transaction, "歸集 {$gasTokenName} 失敗: {$e->getMessage()}", 'warning');
    }
}

private function estimateEvmNativeTransferFee($adapter, string $chainNetwork): string
{
    $configKey = $chainNetwork === 'erc20' ? 'ethereum' : 'bsc';
    $gasLimit = config("services.{$configKey}.gas_limit_native_transfer", 21000);

    // 取得當前 gas price
    $gasPrice = $adapter->getGasPrice();

    // fee = gasLimit × gasPrice (in wei), convert to ETH/BNB
    $feeWei = bcmul((string) $gasLimit, $gasPrice, 0);
    $fee = bcdiv($feeWei, bcpow('10', '18'), 18);

    // +10% buffer
    return bcmul($fee, '1.1', 18);
}
```

**Step 2: Verify syntax**

Run: `cd api && php -l app/Jobs/BatchTransferUsdt.php`
Expected: No syntax errors detected.

**Step 3: Commit**

```bash
git add api/app/Jobs/BatchTransferUsdt.php
git commit -m "feat: consolidate remaining native tokens (TRX/ETH/BNB) to target in batch transfer"
```

---

## Task 7: System Setting — Backend (FeatureToggle model + migration)

**Files:**
- Modify: `api/app/Models/FeatureToggle.php:68` (add new constants)
- Create: `api/database/migrations/2026_03_24_000001_add_count_limit_feature_toggles.php`

**Step 1: Add constants to FeatureToggle model**

In `api/app/Models/FeatureToggle.php`, add after line 68 (`MULTI_DEVICES_LOGIN = 54`):

```php
const USER_CHANNEL_ACCOUNT_DAILY_COUNT = 55; //当日各收/出款号笔数
const USER_CHANNEL_ACCOUNT_MONTHLY_COUNT = 56; //当月各收/出款号笔数
```

**Step 2: Create migration**

Create `api/database/migrations/2026_03_24_000001_add_count_limit_feature_toggles.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('feature_toggles')->insert([
            [
                'id'         => 55,
                'hidden'     => false,
                'enabled'    => false,
                'input'      => json_encode([
                    'type'  => 'boolean',
                    'value' => '',
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id'         => 56,
                'hidden'     => false,
                'enabled'    => false,
                'input'      => json_encode([
                    'type'  => 'boolean',
                    'value' => '',
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('feature_toggles')->whereIn('id', [55, 56])->delete();
    }
};
```

**Step 3: Add to seeder for consistency**

In `api/database/seeds/FeatureToggleSeeder.php`, add entries for IDs 55 and 56 in the `$features` array (after the `MULTI_DEVICES_LOGIN` entry):

```php
FeatureToggle::USER_CHANNEL_ACCOUNT_DAILY_COUNT => [
    'hidden'  => false,
    'enabled' => false,
    'input'   => [
        'type'  => 'boolean',
        'value' => ''
    ],
],
FeatureToggle::USER_CHANNEL_ACCOUNT_MONTHLY_COUNT => [
    'hidden'  => false,
    'enabled' => false,
    'input'   => [
        'type'  => 'boolean',
        'value' => ''
    ],
],
```

**Step 4: Commit**

```bash
git add api/app/Models/FeatureToggle.php api/database/migrations/2026_03_24_000001_add_count_limit_feature_toggles.php api/database/seeds/FeatureToggleSeeder.php
git commit -m "feat: add FeatureToggle constants and migration for daily/monthly count toggles (IDs 55, 56)"
```

---

## Task 8: System Setting — Backend (Controller + Resource)

**Files:**
- Modify: `api/app/Http/Controllers/Admin/UserChannelAccountController.php:94-139`

**Step 1: Add count toggle queries to `index()` method**

After line 102 (the `$monthlyLimitvalue` line), add:

```php
$dailyCountId = FeatureToggle::USER_CHANNEL_ACCOUNT_DAILY_COUNT;
$dailyCountEnabled = $featureToggleRepository->enabled($dailyCountId);

$monthlyCountId = FeatureToggle::USER_CHANNEL_ACCOUNT_MONTHLY_COUNT;
$monthlyCountEnabled = $featureToggleRepository->enabled($monthlyCountId);
```

**Step 2: Add to data transform**

In the `$data->transform()` closure (lines 110-121), add after line 119:

```php
$value->user_channel_account_daily_count_enabled = $dailyCountEnabled;
$value->user_channel_account_monthly_count_enabled = $monthlyCountEnabled;
```

Update the closure's `use` statement to include the new variables.

**Step 3: Add to meta response**

In the `additional` meta array (lines 124-138), add after line 131:

```php
"user_channel_account_daily_count_enabled" => $dailyCountEnabled,
"user_channel_account_monthly_count_enabled" => $monthlyCountEnabled,
```

**Step 4: Verify syntax**

Run: `cd api && php -l app/Http/Controllers/Admin/UserChannelAccountController.php`
Expected: No syntax errors.

**Step 5: Commit**

```bash
git add api/app/Http/Controllers/Admin/UserChannelAccountController.php
git commit -m "feat: return daily/monthly count toggle status in UserChannelAccount API response"
```

---

## Task 9: System Setting — Frontend (Column visibility)

**Files:**
- Modify: `apps/admin/src/pages/userChannel/columns/types.ts:14` (add new deps)
- Modify: `apps/admin/src/pages/userChannel/list.tsx:58-59` (read new toggles)
- Modify: `apps/admin/src/pages/userChannel/columns/dailyCountColumns.tsx` (add toggle guard)
- Modify: `apps/admin/src/pages/userChannel/columns/monthlyCountColumns.tsx` (add toggle guard)
- Modify: `apps/admin/src/pages/userChannel/columns/index.ts:66-69` (pass deps)

**Step 1: Add new deps to ColumnDependencies**

In `apps/admin/src/pages/userChannel/columns/types.ts`, add after line 14 (`monthEnable: boolean;`):

```typescript
dayCountEnable: boolean;
monthCountEnable: boolean;
```

**Step 2: Read new toggles in list page**

In `apps/admin/src/pages/userChannel/list.tsx`, add after line 59:

```typescript
const dayCountEnable = systemSetting?.find(x => x.id === 55)?.enabled ?? false;
const monthCountEnable = systemSetting?.find(x => x.id === 56)?.enabled ?? false;
```

And add to `columnDeps` object (after `monthEnable` on line 225):

```typescript
dayCountEnable,
monthCountEnable,
```

**Step 3: Add toggle guard to daily count columns**

In `apps/admin/src/pages/userChannel/columns/dailyCountColumns.tsx`:

Change `createDailyReceiveCountLimitColumn` return type to `UserChannelColumn | null` and add at the top:
```typescript
export function createDailyReceiveCountLimitColumn(deps: ColumnDependencies): UserChannelColumn | null {
  if (!deps.dayCountEnable) return null;
  // ... rest stays the same
```

Same for `createDailyPayoutCountLimitColumn`:
```typescript
export function createDailyPayoutCountLimitColumn(deps: ColumnDependencies): UserChannelColumn | null {
  if (!deps.dayCountEnable) return null;
  // ... rest stays the same
```

Same for `createDailyReceiveAmountAndCountColumn`:
```typescript
export function createDailyReceiveAmountAndCountColumn(deps: ColumnDependencies): UserChannelColumn | null {
  if (!deps.dayCountEnable) return null;
  // ... rest stays the same
```

Same for `createDailyPayoutAmountAndCountColumn`:
```typescript
export function createDailyPayoutAmountAndCountColumn(deps: ColumnDependencies): UserChannelColumn | null {
  if (!deps.dayCountEnable) return null;
  // ... rest stays the same
```

**Step 4: Add toggle guard to monthly count columns**

In `apps/admin/src/pages/userChannel/columns/monthlyCountColumns.tsx`, same pattern:

Change all 4 exported functions to return `UserChannelColumn | null` and add `if (!deps.monthCountEnable) return null;` at the top of each.

**Step 5: Update column assembly types**

In `apps/admin/src/pages/userChannel/columns/index.ts`, the columns array already filters nulls (line 74), so no change needed there. The return types of the count column functions just need to match `UserChannelColumn | null`.

**Step 6: Verify**

Run: `cd apps/admin && pnpm run typecheck`
Expected: no type errors.

**Step 7: Commit**

```bash
git add apps/admin/src/pages/userChannel/columns/types.ts apps/admin/src/pages/userChannel/list.tsx apps/admin/src/pages/userChannel/columns/dailyCountColumns.tsx apps/admin/src/pages/userChannel/columns/monthlyCountColumns.tsx
git commit -m "feat: toggle daily/monthly count columns based on FeatureToggle IDs 55, 56"
```

---

## Task 10: System Setting — Frontend (System Setting page)

**Files:**
- Modify: `apps/admin/src/pages/systemSetting/list.tsx:43`

**Step 1: Add new toggle IDs to accounts category**

In `apps/admin/src/pages/systemSetting/list.tsx`, change line 43 from:

```typescript
const accounts = filterData([35, 45, 39]);
```

to:

```typescript
const accounts = filterData([35, 45, 55, 56, 39]);
```

**Step 2: Add i18n labels for new toggles**

Check the i18n translation files for system settings. The labels for toggle IDs are typically stored in the backend `feature_toggles` table `input` JSON or in frontend i18n files. Find and add labels for IDs 55 ("当日各收/出款号笔数") and 56 ("当月各收/出款号笔数").

The labels may come from the backend `label` field in the `feature_toggles` table. If so, update the migration to include labels:

In the migration, update the insert data to include a `label` column if it exists (check the `feature_toggles` table schema). Otherwise the backend may auto-generate labels from the FeatureToggle constant names.

**Step 3: Verify**

Run: `cd apps/admin && pnpm run typecheck`
Expected: no type errors.

**Step 4: Commit**

```bash
git add apps/admin/src/pages/systemSetting/list.tsx
git commit -m "feat: display daily/monthly count toggles in system settings page"
```

---

## Task 11: Final Verification

**Step 1: Run frontend typecheck**

Run: `cd /Users/apple/projects/morgan/ustd && pnpm -r typecheck`
Expected: all packages pass.

**Step 2: Run PHP syntax check on all modified files**

Run: `cd api && php -l app/Jobs/BatchTransferUsdt.php && php -l app/Builders/UserChannelAccount.php && php -l app/Builders/Transaction.php && php -l app/Http/Controllers/Admin/UserChannelAccountController.php && php -l app/Models/FeatureToggle.php`
Expected: No syntax errors.

**Step 3: Run unit tests (background)**

Run: `cd api && php artisan test tests/Unit` (background)
Expected: all tests pass.
