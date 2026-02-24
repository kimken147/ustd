# USDT Phase 2 Enhancements — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Complete USDT integration with withdrawal confirmation, balance sync, merchant UI, cashier views, address validation, and admin account management improvements.

**Architecture:** Extend existing `Trc20Adapter` with balance query and tx confirmation methods. Add `ConfirmUsdtWithdraw` delayed job for post-broadcast monitoring. Implement sync endpoint via TronGrid `getaccount` + `triggerconstantcontract`. Frontend changes follow existing Refine + Ant Design patterns.

**Tech Stack:** Laravel 11, PHP 8.2+, TronGrid REST API, React 18, Refine v5, Ant Design v5, TypeScript

---

## Task 1: Extend Trc20Adapter with balance query methods

Add `getBalance()` (TRX) and `getTokenBalance()` (USDT) to the adapter for use by sync and pre-flight checks.

**Files:**
- Modify: `api/app/Services/Crypto/Adapters/ChainAdapterInterface.php`
- Modify: `api/app/Services/Crypto/Adapters/Trc20Adapter.php`

**Step 1: Add balance query methods to ChainAdapterInterface**

```php
// In ChainAdapterInterface.php, add after sendTransaction():

/**
 * Get native token balance (e.g. TRX) in sun/wei.
 */
public function getNativeBalance(string $address): string;

/**
 * Get USDT token balance (6 decimals, human-readable).
 */
public function getTokenBalance(string $address): string;
```

**Step 2: Implement in Trc20Adapter**

```php
public function getNativeBalance(string $address): string
{
    $response = $this->buildHttpClient()
        ->post($this->getBaseUrl() . '/wallet/getaccount', [
            'address' => $this->base58ToHex($address),
            'visible' => false,
        ]);

    if (!$response->successful()) {
        return '0';
    }

    $balance = $response->json('balance', 0);
    // Convert sun to TRX (1 TRX = 1,000,000 sun)
    return bcdiv((string) $balance, '1000000', 6);
}

public function getTokenBalance(string $address): string
{
    $addressParam = str_pad(substr($this->base58ToHex($address), 2), 64, '0', STR_PAD_LEFT);

    $response = $this->buildHttpClient()
        ->post($this->getBaseUrl() . '/wallet/triggerconstantcontract', [
            'owner_address' => $this->base58ToHex($address),
            'contract_address' => $this->base58ToHex(self::USDT_CONTRACT),
            'function_selector' => 'balanceOf(address)',
            'parameter' => $addressParam,
            'visible' => false,
        ]);

    if (!$response->successful()) {
        return '0';
    }

    $result = $response->json('constant_result.0', '0');
    $rawBalance = gmp_strval(gmp_init($result, 16));
    return bcdiv($rawBalance, bcpow('10', '6'), 6);
}
```

**Step 3: Verify syntax**

```bash
php -l api/app/Services/Crypto/Adapters/Trc20Adapter.php
php -l api/app/Services/Crypto/Adapters/ChainAdapterInterface.php
```

**Step 4: Commit**

```bash
git add api/app/Services/Crypto/Adapters/ChainAdapterInterface.php api/app/Services/Crypto/Adapters/Trc20Adapter.php
git commit -m "feat: add getNativeBalance and getTokenBalance to Trc20Adapter"
```

---

## Task 2: Add onchain balance fields and migration

Add `onchain_usdt_balance` and `onchain_trx_balance` columns to `user_channel_accounts` table.

**Files:**
- Create: `api/database/migrations/2026_02_24_000001_add_onchain_balance_to_user_channel_accounts.php`
- Modify: `api/app/Models/UserChannelAccount.php` — add to `$fillable`
- Modify: `api/app/Http/Resources/UserChannelAccount.php` — expose fields

**Step 1: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->decimal('onchain_usdt_balance', 20, 6)->default(0)->after('balance_limit');
            $table->decimal('onchain_trx_balance', 20, 6)->default(0)->after('onchain_usdt_balance');
            $table->timestamp('onchain_synced_at')->nullable()->after('onchain_trx_balance');
        });
    }

    public function down(): void
    {
        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->dropColumn(['onchain_usdt_balance', 'onchain_trx_balance', 'onchain_synced_at']);
        });
    }
};
```

**Step 2: Add to UserChannelAccount model $fillable**

In `api/app/Models/UserChannelAccount.php`, add to `$fillable` array after `balance_limit`:
```php
'onchain_usdt_balance',
'onchain_trx_balance',
'onchain_synced_at',
```

Add to `$casts`:
```php
'onchain_synced_at' => 'datetime',
```

**Step 3: Expose in API resource**

In `api/app/Http/Resources/UserChannelAccount.php`, add to the `toArray()` return:
```php
'onchain_usdt_balance' => $this->onchain_usdt_balance,
'onchain_trx_balance'  => $this->onchain_trx_balance,
'onchain_synced_at'    => optional($this->onchain_synced_at)->toIso8601String(),
```

**Step 4: Verify and commit**

```bash
php -l api/app/Models/UserChannelAccount.php
php -l api/app/Http/Resources/UserChannelAccount.php
git add api/database/migrations/2026_02_24_000001_add_onchain_balance_to_user_channel_accounts.php api/app/Models/UserChannelAccount.php api/app/Http/Resources/UserChannelAccount.php
git commit -m "feat: add onchain USDT/TRX balance fields to user_channel_accounts"
```

---

## Task 3: Implement sync balance endpoint

Replace the stub `sync()` method in UserChannelAccountController with real TronGrid balance query.

**Files:**
- Modify: `api/app/Http/Controllers/Admin/UserChannelAccountController.php:670-673`
- Modify: `api/app/Services/Crypto/Adapters/Trc20Adapter.php` (already done in Task 1)

**Step 1: Implement sync method**

Replace the existing stub in `UserChannelAccountController.php`:

```php
public function sync(Request $request)
{
    $this->validate($request, [
        'id' => 'required|integer',
    ]);

    $account = UserChannelAccount::findOrFail($request->input('id'));

    if ($account->channel_code !== Channel::CODE_USDT) {
        return response()->json(['message' => '僅支援 USDT 帳號同步'], Response::HTTP_BAD_REQUEST);
    }

    if (empty($account->account)) {
        return response()->json(['message' => '帳號未設定錢包地址'], Response::HTTP_BAD_REQUEST);
    }

    $chainNetwork = data_get($account->detail, UserChannelAccount::DETAIL_KEY_CHAIN_NETWORK, 'trc20');
    $adapter = $this->resolveChainAdapter($chainNetwork);

    if (!$adapter) {
        return response()->json(['message' => '不支援的鏈網路'], Response::HTTP_BAD_REQUEST);
    }

    $account->update([
        'onchain_usdt_balance' => $adapter->getTokenBalance($account->account),
        'onchain_trx_balance'  => $adapter->getNativeBalance($account->account),
        'onchain_synced_at'    => now(),
    ]);

    return \App\Http\Resources\UserChannelAccount::make($account->refresh());
}

private function resolveChainAdapter(string $chainNetwork): ?\App\Services\Crypto\Adapters\ChainAdapterInterface
{
    return match ($chainNetwork) {
        'trc20' => app(\App\Services\Crypto\Adapters\Trc20Adapter::class),
        default => null,
    };
}
```

**Step 2: Add import at top of controller**

```php
use App\Models\Channel;
```

Check if `Channel` is already imported — if so, skip this step.

**Step 3: Verify and commit**

```bash
php -l api/app/Http/Controllers/Admin/UserChannelAccountController.php
git add api/app/Http/Controllers/Admin/UserChannelAccountController.php
git commit -m "feat: implement USDT onchain balance sync via TronGrid API"
```

---

## Task 4: Add TRX balance pre-flight check in UsdtWithdrawHandler

Before broadcasting a withdrawal, check the sender has enough TRX for gas.

**Files:**
- Modify: `api/app/Services/Crypto/UsdtWithdrawHandler.php`
- Modify: `api/config/services.php` — add `min_trx_balance` setting

**Step 1: Add config**

In `api/config/services.php`, update the `trongrid` section:

```php
'trongrid' => [
    'api_key'         => env('TRONGRID_API_KEY'),
    'base_url'        => env('TRONGRID_BASE_URL', 'https://api.trongrid.io'),
    'fee_limit'       => (int) env('TRONGRID_FEE_LIMIT', 100000000),
    'min_trx_balance' => env('TRONGRID_MIN_TRX_BALANCE', '30'),
],
```

**Step 2: Update Trc20Adapter to use config fee_limit**

In `Trc20Adapter.php`, replace the hardcoded `FEE_LIMIT` constant usage in `createTriggerSmartContract()`:

```php
// Change:
'fee_limit' => self::FEE_LIMIT,
// To:
'fee_limit' => config('services.trongrid.fee_limit', self::FEE_LIMIT),
```

**Step 3: Add pre-flight check in UsdtWithdrawHandler**

In `UsdtWithdrawHandler.php`, add balance check after resolving the adapter and before calling `sendTransaction()`:

```php
// After: $adapter = $this->resolveAdapter($chainNetwork);
// Before: $privateKey = decrypt($encryptedKey);

$minTrxBalance = config('services.trongrid.min_trx_balance', '30');
$trxBalance = $adapter->getNativeBalance($fromAddress);

if (bccomp($trxBalance, $minTrxBalance, 6) < 0) {
    Log::error('UsdtWithdrawHandler: TRX 餘額不足支付 Gas', [
        'transaction_id' => $transaction->id,
        'trx_balance'    => $trxBalance,
        'min_required'   => $minTrxBalance,
    ]);
    throw new InsufficientBalanceException(
        "TRX balance {$trxBalance} below minimum {$minTrxBalance} for gas fees"
    );
}
```

**Step 4: Verify and commit**

```bash
php -l api/app/Services/Crypto/UsdtWithdrawHandler.php
php -l api/app/Services/Crypto/Adapters/Trc20Adapter.php
php -l api/config/services.php
git add api/app/Services/Crypto/UsdtWithdrawHandler.php api/app/Services/Crypto/Adapters/Trc20Adapter.php api/config/services.php
git commit -m "feat: add TRX balance pre-flight check before USDT withdrawal"
```

---

## Task 5: Implement withdrawal confirmation job

After broadcasting, schedule a delayed job to check if the transaction was confirmed on-chain.

**Files:**
- Create: `api/app/Jobs/ConfirmUsdtWithdraw.php`
- Modify: `api/app/Services/Crypto/Adapters/ChainAdapterInterface.php` — add `getTransactionInfo()`
- Modify: `api/app/Services/Crypto/Adapters/Trc20Adapter.php` — implement `getTransactionInfo()`
- Modify: `api/app/Services/Crypto/UsdtWithdrawHandler.php` — dispatch confirmation job after broadcast

**Step 1: Add getTransactionInfo to interface**

```php
// In ChainAdapterInterface.php:

/**
 * Check transaction status on-chain.
 * Returns ['confirmed' => bool, 'success' => bool] or null if not found yet.
 */
public function getTransactionInfo(string $txHash): ?array;
```

**Step 2: Implement in Trc20Adapter**

```php
public function getTransactionInfo(string $txHash): ?array
{
    $response = $this->buildHttpClient()
        ->post($this->getBaseUrl() . '/walletsolidity/gettransactioninfobyid', [
            'value' => $txHash,
        ]);

    if (!$response->successful()) {
        return null;
    }

    $data = $response->json();

    // Empty response means transaction not yet confirmed
    if (empty($data) || !isset($data['id'])) {
        return null;
    }

    return [
        'confirmed' => true,
        'success'   => ($data['receipt']['result'] ?? '') === 'SUCCESS',
        'fee'       => bcdiv((string) ($data['fee'] ?? 0), '1000000', 6),
    ];
}
```

**Step 3: Create ConfirmUsdtWithdraw job**

```php
<?php

namespace App\Jobs;

use App\Models\Channel;
use App\Models\Transaction;
use App\Services\Crypto\Adapters\ChainAdapterInterface;
use App\Services\Crypto\Adapters\Trc20Adapter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ConfirmUsdtWithdraw implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 10;
    public int $backoff = 15;

    public function __construct(
        public readonly int $transactionId
    ) {}

    public function handle(): void
    {
        $transaction = Transaction::find($this->transactionId);
        if (!$transaction || empty($transaction->tx_hash)) {
            return;
        }

        $adapter = $this->resolveAdapter($transaction->chain_network ?? 'trc20');
        if (!$adapter) {
            return;
        }

        $info = $adapter->getTransactionInfo($transaction->tx_hash);

        if ($info === null) {
            // Not confirmed yet — let retry mechanism handle it
            Log::info('ConfirmUsdtWithdraw: 交易尚未確認', [
                'transaction_id' => $transaction->id,
                'tx_hash' => $transaction->tx_hash,
                'attempt' => $this->attempts(),
            ]);
            $this->release($this->backoff);
            return;
        }

        if ($info['confirmed'] && $info['success']) {
            Log::info('ConfirmUsdtWithdraw: 鏈上交易已確認成功', [
                'transaction_id' => $transaction->id,
                'tx_hash' => $transaction->tx_hash,
                'fee' => $info['fee'],
            ]);
        } else {
            Log::error('ConfirmUsdtWithdraw: 鏈上交易失敗', [
                'transaction_id' => $transaction->id,
                'tx_hash' => $transaction->tx_hash,
                'info' => $info,
            ]);
        }
    }

    private function resolveAdapter(string $chainNetwork): ?ChainAdapterInterface
    {
        return match ($chainNetwork) {
            'trc20' => app(Trc20Adapter::class),
            default => null,
        };
    }
}
```

**Step 4: Dispatch confirmation job from UsdtWithdrawHandler**

In `UsdtWithdrawHandler.php`, after the successful `$transaction->update(...)` call, add:

```php
// Dispatch delayed confirmation check
ConfirmUsdtWithdraw::dispatch($transaction->id)->delay(now()->addSeconds(15));
```

Add import at top:
```php
use App\Jobs\ConfirmUsdtWithdraw;
```

**Step 5: Verify and commit**

```bash
php -l api/app/Jobs/ConfirmUsdtWithdraw.php
php -l api/app/Services/Crypto/Adapters/Trc20Adapter.php
php -l api/app/Services/Crypto/Adapters/ChainAdapterInterface.php
php -l api/app/Services/Crypto/UsdtWithdrawHandler.php
git add api/app/Jobs/ConfirmUsdtWithdraw.php api/app/Services/Crypto/Adapters/ChainAdapterInterface.php api/app/Services/Crypto/Adapters/Trc20Adapter.php api/app/Services/Crypto/UsdtWithdrawHandler.php
git commit -m "feat: add ConfirmUsdtWithdraw job for post-broadcast on-chain verification"
```

---

## Task 6: Add tx_hash and chain_network to merchant notification callback

Include USDT-specific fields in the merchant callback payload.

**Files:**
- Modify: `api/app/Jobs/NotifyTransaction.php:89-95`

**Step 1: Update mainData in NotifyTransaction**

In `NotifyTransaction.php`, after line 95 (`'status' => $this->transaction->status,`), add:

```php
$mainData = [
    'order_number'        => $this->transaction->order_number,
    'system_order_number' => $this->transaction->system_order_number,
    'username'            => $targetUser->username,
    'amount'              => $this->transaction->amount,
    'status'              => $this->transaction->status,
];

// Append USDT-specific fields if present
if ($this->transaction->chain_network) {
    $mainData['chain_network'] = $this->transaction->chain_network;
}
if ($this->transaction->tx_hash) {
    $mainData['tx_hash'] = $this->transaction->tx_hash;
}
```

**Step 2: Verify and commit**

```bash
php -l api/app/Jobs/NotifyTransaction.php
git add api/app/Jobs/NotifyTransaction.php
git commit -m "feat: include chain_network and tx_hash in merchant notification callback"
```

---

## Task 7: Add chain_network/tx_hash to merchant transaction interfaces and columns

Update merchant portal to display USDT chain info in both collection and PayForAnother lists.

**Files:**
- Modify: `apps/merchant/src/interfaces/transaction.ts` — add fields
- Modify: `apps/merchant/src/interfaces/withdraw.ts` — add fields
- Modify: `apps/merchant/src/pages/collection/columns/index.tsx` — add column
- Modify: `apps/merchant/src/pages/PayForAnother/list.tsx` — add columns

**Step 1: Update transaction interface**

In `apps/merchant/src/interfaces/transaction.ts`, add after `usdt_rate: string;`:

```typescript
chain_network?: string;
tx_hash?: string;
```

**Step 2: Update withdraw interface**

In `apps/merchant/src/interfaces/withdraw.ts`, add after `_search1: any;` (line 24):

```typescript
chain_network?: string;
tx_hash?: string;
```

**Step 3: Add columns to collection list**

In `apps/merchant/src/pages/collection/columns/index.tsx`, add import at top:

```typescript
import { Typography } from 'antd';
```

Add two columns after the `channel_code` column (after line 30):

```typescript
{
  title: t('collection.fields.chainNetwork'),
  dataIndex: 'chain_network',
  render: (value: string) => value ? value.toUpperCase() : '-',
},
```

Add tx_hash column before `created_at`:

```typescript
{
  title: 'Tx Hash',
  dataIndex: 'tx_hash',
  render: (value: string) => value
    ? <Typography.Text copyable ellipsis style={{ maxWidth: 120 }}>{value}</Typography.Text>
    : '-',
},
```

**Step 4: Add columns to PayForAnother list**

In `apps/merchant/src/pages/PayForAnother/list.tsx`, find the columns array and add after the `bank_card_number` column:

```typescript
{
  title: t('payForAnother.fields.chainNetwork', 'Chain'),
  dataIndex: 'chain_network',
  responsive: ['lg', 'xl', 'xxl'] as Breakpoint[],
  render: (value: string) => value ? value.toUpperCase() : '-',
},
{
  title: 'Tx Hash',
  dataIndex: 'tx_hash',
  responsive: ['xl', 'xxl'] as Breakpoint[],
  width: 140,
  render: (value: string) => value
    ? <Typography.Text copyable ellipsis style={{ maxWidth: 120 }}>{value}</Typography.Text>
    : '-',
},
```

Add `Typography` to the antd import.

**Step 5: Add i18n keys**

In `apps/merchant/public/locales/zh-CN/common.json`, add to `collection.fields`:
```json
"chainNetwork": "链网络"
```

In `apps/merchant/public/locales/en/common.json`, add to `collection.fields`:
```json
"chainNetwork": "Chain"
```

In `apps/merchant/public/locales/th/common.json`, add to `collection.fields`:
```json
"chainNetwork": "เครือข่าย"
```

**Step 6: Commit**

```bash
git add apps/merchant/src/interfaces/transaction.ts apps/merchant/src/interfaces/withdraw.ts apps/merchant/src/pages/collection/columns/index.tsx apps/merchant/src/pages/PayForAnother/list.tsx apps/merchant/public/locales/*/common.json
git commit -m "feat: add chain_network and tx_hash columns to merchant transaction lists"
```

---

## Task 8: Add USDT fields to admin account edit modal

Support editing `chain_network` and `private_key` in the update modal.

**Files:**
- Modify: `apps/admin/src/pages/userChannel/list.tsx` — add form items to modal config
- Modify: `apps/admin/src/pages/userChannel/useUpdateUserChannelModal.tsx` — populate chain_network from detail

**Step 1: Add form items to update modal in list.tsx**

In `apps/admin/src/pages/userChannel/list.tsx`, find the `formItems` array passed to `useUpdateModal()`. Add these items after the existing ones (before `note`):

```typescript
{
  name: 'chain_network',
  label: t('userChannel.fields.chainNetwork'),
  component: (
    <Select
      options={[
        { label: 'TRC-20', value: 'trc20' },
        { label: 'ERC-20', value: 'erc20' },
        { label: 'BEP-20', value: 'bep20' },
      ]}
      allowClear
    />
  ),
},
{
  name: 'private_key',
  label: t('userChannel.fields.privateKey'),
  component: <Input.Password placeholder={t('userChannel.placeholders.privateKey')} />,
},
```

Add `Select` and `Input` to the antd imports if not present.

**Step 2: Populate chain_network in useUpdateUserChannelModal**

In `apps/admin/src/pages/userChannel/useUpdateUserChannelModal.tsx`, update the `setFieldsValue` to include:

```typescript
chain_network: userChannel?.detail?.chain_network,
```

**Step 3: Commit**

```bash
git add apps/admin/src/pages/userChannel/list.tsx apps/admin/src/pages/userChannel/useUpdateUserChannelModal.tsx
git commit -m "feat: add chain_network and private_key to admin account edit modal"
```

---

## Task 9: Add USDT info to admin account show page

Display wallet address, chain network, onchain balances, and last sync time.

**Files:**
- Modify: `apps/admin/src/pages/userChannel/show.tsx`
- Modify: `apps/admin/src/interfaces/userChannel.ts` — add onchain balance fields

**Step 1: Update interface**

In `apps/admin/src/interfaces/userChannel.ts`, add to the `UserChannel` interface:

```typescript
onchain_usdt_balance?: string;
onchain_trx_balance?: string;
onchain_synced_at?: string;
```

**Step 2: Add fields to show page**

In `apps/admin/src/pages/userChannel/show.tsx`, find the `<Descriptions>` component. Add USDT-specific items conditionally when `channel_code === 'USDT'`:

```tsx
{record?.channel_code === 'USDT' && (
  <>
    <Descriptions.Item label={t('userChannel.fields.account')}>
      <Typography.Text copyable>{record?.account}</Typography.Text>
    </Descriptions.Item>
    <Descriptions.Item label={t('userChannel.fields.chainNetwork')}>
      {record?.detail?.chain_network?.toUpperCase() ?? '-'}
    </Descriptions.Item>
    <Descriptions.Item label="USDT 餘額 (鏈上)">
      {record?.onchain_usdt_balance ?? '-'}
    </Descriptions.Item>
    <Descriptions.Item label="TRX 餘額 (Gas)">
      {record?.onchain_trx_balance ?? '-'}
    </Descriptions.Item>
    <Descriptions.Item label="上次同步">
      {record?.onchain_synced_at ? <DateField value={record.onchain_synced_at} format="YYYY-MM-DD HH:mm:ss" /> : '-'}
    </Descriptions.Item>
  </>
)}
```

Add `Typography` and `DateField` to imports if not present.

**Step 3: Commit**

```bash
git add apps/admin/src/pages/userChannel/show.tsx apps/admin/src/interfaces/userChannel.ts
git commit -m "feat: display USDT onchain balance and chain network in admin show page"
```

---

## Task 10: Add sync balance button to admin account list

Add a button in the admin account list to trigger onchain balance sync.

**Files:**
- Modify: `apps/admin/src/pages/userChannel/columns/` — add onchain balance columns with sync button
- Modify: `apps/admin/src/pages/userChannel/list.tsx` — wire up sync API call

**Step 1: Add onchain balance columns**

Create columns that show `onchain_usdt_balance`, `onchain_trx_balance`, and a sync button. The sync button calls `PUT /admin/user-channel-accounts/sync` with `{ id: record.id }`.

In the list page, add a custom mutation for sync:

```typescript
const { mutate: syncBalance, isLoading: isSyncing } = useCustomMutation();

const handleSync = (id: number) => {
  syncBalance({
    url: 'admin/user-channel-accounts/sync',
    method: 'put',
    values: { id },
  }, {
    onSuccess: () => {
      // Refresh the table
      tableQueryResult?.refetch();
    },
  });
};
```

Add columns for onchain balances with a `SyncOutlined` icon button:

```typescript
{
  title: 'USDT (鏈上)',
  dataIndex: 'onchain_usdt_balance',
  render: (value: string, record: UserChannel) =>
    record.channel_code === 'USDT' ? (value ?? '-') : '-',
},
{
  title: 'TRX (Gas)',
  dataIndex: 'onchain_trx_balance',
  render: (value: string, record: UserChannel) =>
    record.channel_code === 'USDT' ? (
      <>
        {value ?? '-'}{' '}
        <Button type="link" size="small" icon={<SyncOutlined />} onClick={() => handleSync(record.id)} />
      </>
    ) : '-',
},
```

**Step 2: Commit**

```bash
git add apps/admin/src/pages/userChannel/list.tsx apps/admin/src/pages/userChannel/columns/
git commit -m "feat: add onchain balance display and sync button in admin account list"
```

---

## Task 11: Add TRON address validation

Validate wallet addresses on both frontend and backend.

**Files:**
- Modify: `api/app/Http/Controllers/Admin/UserChannelAccountController.php` — add validation in store/update
- Modify: `apps/admin/src/pages/userChannel/create/create.tsx` — add frontend validation rule

**Step 1: Backend validation**

In `UserChannelAccountController.php`, add a helper method:

```php
private function validateTronAddress(?string $address): void
{
    if ($address === null) {
        return;
    }

    if (!preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $address)) {
        abort(Response::HTTP_BAD_REQUEST, '無效的 TRON 錢包地址格式（應以 T 開頭，34 字元 base58）');
    }
}
```

Call it in `store()` method when `channel_code === 'USDT'`:

```php
if ($channelAmount->channel_code === Channel::CODE_USDT) {
    $this->validateTronAddress($request->input('bank_card_number'));
}
```

Also call in `update()` when account field is being modified.

**Step 2: Frontend validation**

In `apps/admin/src/pages/userChannel/create/create.tsx`, add a validation rule to the account input when channel is USDT:

```typescript
const tronAddressRule = {
  pattern: /^T[1-9A-HJ-NP-Za-km-z]{33}$/,
  message: t('userChannel.validation.invalidTronAddress', 'Invalid TRON address format'),
};
```

Apply to the `bank_card_number` Form.Item rules when `curChannelCode === 'USDT'`.

**Step 3: Add i18n key**

In `apps/admin/public/locales/zh-CN/userChannel.json`, add:
```json
"validation": {
  "invalidTronAddress": "無效的 TRON 地址格式（T 開頭，34 字元）"
}
```

**Step 4: Verify and commit**

```bash
php -l api/app/Http/Controllers/Admin/UserChannelAccountController.php
git add api/app/Http/Controllers/Admin/UserChannelAccountController.php apps/admin/src/pages/userChannel/create/create.tsx apps/admin/public/locales/*/userChannel.json
git commit -m "feat: add TRON address validation on frontend and backend"
```

---

## Task 12: Create USDT cashier Blade views

Create matching and matched Blade views for USDT payment pages.

**Files:**
- Create: `api/resources/views/v1/transactions/cn/usdt/matching.blade.php`
- Create: `api/resources/views/v1/transactions/cn/usdt/matched.blade.php`
- Create: `api/resources/views/v1/transactions/cn/matching-timed-out.blade.php` (if not exists)
- Create: `api/resources/views/v1/transactions/cn/paying-timed-out.blade.php` (if not exists)
- Create: `api/resources/views/v1/transactions/cn/paying-success.blade.php` (if not exists)
- Create: `api/resources/views/v1/transactions/cn/please-try-later.blade.php` (if not exists)
- Create: `api/resources/views/v1/transactions/error.blade.php` (if not exists)

**Context:** The `CreateTransactionController` resolves views as `v1.transactions.{country}.{code}.matched`. The country comes from `$channel->country` (database column on channels table). The code is `strtolower($channel->code)` → `usdt`.

**Step 1: Create directory structure**

```bash
mkdir -p api/resources/views/v1/transactions/cn/usdt
```

**Step 2: Create matching view (waiting page)**

`api/resources/views/v1/transactions/cn/usdt/matching.blade.php`:

This page shows while the system is matching a provider account. It receives only `$transaction`. It should poll an API endpoint to check if the transaction has been matched, then redirect to the cashier.

```blade
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>匹配中</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .card { background: #fff; border-radius: 12px; padding: 40px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1); max-width: 400px; width: 90%; }
        .spinner { width: 40px; height: 40px; border: 3px solid #e0e0e0; border-top: 3px solid #1890ff; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 20px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        h2 { color: #333; margin-bottom: 10px; font-size: 18px; }
        p { color: #999; font-size: 14px; }
        .amount { font-size: 24px; color: #1890ff; font-weight: bold; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="card">
        <div class="spinner"></div>
        <h2>正在匹配收款帐号</h2>
        <div class="amount">{{ number_format($transaction->floating_amount, 2) }} USDT</div>
        <p>订单号：{{ $transaction->system_order_number }}</p>
        <p style="margin-top: 10px;">请稍候...</p>
    </div>
    <script>
        const orderId = '{{ $transaction->system_order_number }}';
        const checkInterval = setInterval(async () => {
            try {
                const res = await fetch(`/api/v1/cashier/${orderId}`);
                if (res.redirected) {
                    window.location.href = res.url;
                    clearInterval(checkInterval);
                } else if (res.ok) {
                    window.location.reload();
                }
            } catch (e) {}
        }, 3000);
    </script>
</body>
</html>
```

**Step 3: Create matched view (payment page)**

`api/resources/views/v1/transactions/cn/usdt/matched.blade.php`:

This page shows the USDT wallet address and amount for the buyer to send. Variables available: `$transaction`, `$channel`, `$note`, `$payingLimitEnabled`, `$payingLimitSeconds`, `$code`, `$apiHost`.

For USDT, the key data is:
- Wallet address: `$transaction->fromChannelAccount->account`
- Chain network: from `$transaction->from_channel_account` detail
- Amount: `$transaction->floating_amount`

```blade
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>USDT 付款</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f5; min-height: 100vh; padding: 20px; }
        .card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); max-width: 440px; margin: 0 auto; }
        .header { text-align: center; padding-bottom: 16px; border-bottom: 1px solid #f0f0f0; }
        .header h2 { font-size: 18px; color: #333; }
        .chain-badge { display: inline-block; background: #e6f7ff; color: #1890ff; padding: 2px 10px; border-radius: 4px; font-size: 12px; margin-top: 6px; }
        .amount-section { text-align: center; padding: 20px 0; }
        .amount { font-size: 32px; font-weight: bold; color: #1890ff; }
        .amount-label { color: #999; font-size: 13px; margin-top: 4px; }
        .info-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f5f5f5; }
        .info-label { color: #999; font-size: 13px; }
        .info-value { color: #333; font-size: 13px; word-break: break-all; max-width: 65%; text-align: right; }
        .copy-btn { background: #1890ff; color: #fff; border: none; padding: 6px 12px; border-radius: 4px; font-size: 12px; cursor: pointer; margin-left: 6px; }
        .copy-btn:active { background: #096dd9; }
        .address-box { background: #fafafa; border: 1px solid #e8e8e8; border-radius: 8px; padding: 12px; margin: 16px 0; word-break: break-all; font-family: monospace; font-size: 14px; text-align: center; }
        .note { background: #fffbe6; border: 1px solid #ffe58f; border-radius: 4px; padding: 10px; font-size: 13px; color: #d48806; margin-top: 16px; }
        .timer { text-align: center; color: #ff4d4f; font-size: 14px; margin-top: 12px; }
        .warn { text-align: center; color: #ff4d4f; font-size: 12px; margin-top: 8px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h2>USDT 转账付款</h2>
            <span class="chain-badge">
                {{ strtoupper(data_get($transaction->from_channel_account, 'chain_network', 'TRC-20')) }}
            </span>
        </div>

        <div class="amount-section">
            <div class="amount">{{ $transaction->floating_amount }}</div>
            <div class="amount-label">USDT</div>
        </div>

        <div class="info-row">
            <span class="info-label">收款地址</span>
            <span class="info-value">
                <button class="copy-btn" onclick="copyText('{{ $transaction->fromChannelAccount->account ?? '' }}')">复制</button>
            </span>
        </div>
        <div class="address-box" id="wallet-address">
            {{ $transaction->fromChannelAccount->account ?? '' }}
        </div>

        <div class="info-row">
            <span class="info-label">订单号</span>
            <span class="info-value">{{ $transaction->system_order_number }}</span>
        </div>

        @if($note)
        <div class="note">备注：{{ $note }}</div>
        @endif

        <div class="warn">请务必转账精确金额，否则系统无法自动确认</div>

        @if($payingLimitEnabled)
        <div class="timer" id="timer"></div>
        <script>
            let seconds = {{ $payingLimitSeconds }};
            const timerEl = document.getElementById('timer');
            const countdown = setInterval(() => {
                const m = Math.floor(seconds / 60);
                const s = seconds % 60;
                timerEl.textContent = `剩余支付时间: ${m}:${String(s).padStart(2, '0')}`;
                if (--seconds < 0) {
                    clearInterval(countdown);
                    timerEl.textContent = '支付超时';
                    window.location.reload();
                }
            }, 1000);
        </script>
        @endif
    </div>

    <script>
        function copyText(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('已复制');
            }).catch(() => {
                const ta = document.createElement('textarea');
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                alert('已复制');
            });
        }
    </script>
</body>
</html>
```

**Step 4: Create shared status views**

Create these minimal views if they don't exist:

`api/resources/views/v1/transactions/cn/matching-timed-out.blade.php`:
```blade
<!DOCTYPE html>
<html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>匹配超时</title>
<style>body{font-family:sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;background:#f5f5f5;}.card{background:#fff;border-radius:12px;padding:40px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.1);max-width:400px;width:90%}h2{color:#ff4d4f;margin-bottom:10px}p{color:#999;font-size:14px}</style></head>
<body><div class="card"><h2>匹配超时</h2><p>订单号：{{ $transaction->system_order_number }}</p><p>请重新提交订单</p></div></body></html>
```

`api/resources/views/v1/transactions/cn/paying-timed-out.blade.php`:
```blade
<!DOCTYPE html>
<html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>支付超时</title>
<style>body{font-family:sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;background:#f5f5f5;}.card{background:#fff;border-radius:12px;padding:40px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.1);max-width:400px;width:90%}h2{color:#ff4d4f;margin-bottom:10px}p{color:#999;font-size:14px}</style></head>
<body><div class="card"><h2>支付超时</h2><p>订单号：{{ $transaction->system_order_number }}</p><p>如已转账请联系客服</p></div></body></html>
```

`api/resources/views/v1/transactions/cn/paying-success.blade.php`:
```blade
<!DOCTYPE html>
<html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>支付成功</title>
<style>body{font-family:sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;background:#f5f5f5;}.card{background:#fff;border-radius:12px;padding:40px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.1);max-width:400px;width:90%}.check{font-size:48px;color:#52c41a;margin-bottom:10px}h2{color:#333;margin-bottom:10px}p{color:#999;font-size:14px}</style></head>
<body><div class="card"><div class="check">✓</div><h2>支付成功</h2><p>订单号：{{ $transaction->system_order_number }}</p></div></body></html>
```

`api/resources/views/v1/transactions/cn/please-try-later.blade.php`:
```blade
<!DOCTYPE html>
<html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>请稍后再试</title>
<style>body{font-family:sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;background:#f5f5f5;}.card{background:#fff;border-radius:12px;padding:40px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.1);max-width:400px;width:90%}h2{color:#faad14;margin-bottom:10px}p{color:#999;font-size:14px}</style></head>
<body><div class="card"><h2>系统繁忙</h2><p>请稍后再试</p></div></body></html>
```

`api/resources/views/v1/transactions/error.blade.php`:
```blade
<!DOCTYPE html>
<html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>错误</title>
<style>body{font-family:sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;background:#f5f5f5;}.card{background:#fff;border-radius:12px;padding:40px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.1);max-width:400px;width:90%}h2{color:#ff4d4f;margin-bottom:10px}p{color:#999;font-size:14px}</style></head>
<body><div class="card"><h2>发生错误</h2><p>{{ $errorMessage }}</p></div></body></html>
```

**Step 5: Commit**

```bash
git add api/resources/views/
git commit -m "feat: create USDT cashier Blade views for matching, matched, and status pages"
```

---

## Task 13: Final verification

Run PHP syntax check, TypeScript check, and review all changes.

**Step 1: PHP syntax check**

```bash
find api/app api/config -name "*.php" -newer api/app/Models/Channel.php | xargs -I{} php -l {}
```

**Step 2: TypeScript check**

```bash
pnpm --filter @morgan-ustd/shared run typecheck
cd apps/admin && npx tsc --noEmit
```

**Step 3: Review all changes**

```bash
git log --oneline --since="today"
git diff --stat HEAD~12..HEAD
```

**Step 4: Commit plan update and finalize**

```bash
git add docs/plans/2026-02-24-usdt-phase2-enhancements.md
git commit -m "docs: add USDT Phase 2 enhancements implementation plan"
```
