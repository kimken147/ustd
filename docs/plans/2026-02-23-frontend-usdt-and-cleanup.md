# Frontend USDT Integration & Channel Code Cleanup Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Clean up all old channel code references from frontend/backend, adjust USDT wallet_address to use `account` field, add USDT-specific UI to admin/merchant frontends, add chain_network/tx_hash to transaction views, implement self-operated USDT withdrawal with encrypted private key signing via TronGrid API.

**Architecture:** Four phases — (1) Backend adjustments: wallet_address→account, remove dead columns/code; (2) Frontend cleanup: remove all old channel code conditionals; (3) Frontend USDT features: add chain_network selector, private_key input to account forms, chain_network/tx_hash to transaction views; (4) Backend USDT withdrawal: extend ChainAdapterInterface with sendTransaction(), implement Trc20Adapter signing, integrate into WithdrawService.

**Tech Stack:** Laravel 11 (PHP 8.2+), React 18 + Refine v5 + Ant Design v5, TypeScript, TronGrid API, `simplito/elliptic-php` for ECDSA signing, bcmath for precision

---

## Phase 1: Backend Adjustments

### Task 1: Remove DETAIL_KEY_WALLET_ADDRESS and use account field

**Context:** Currently USDT wallet address is stored in `detail.wallet_address` JSON. The `account` field on `user_channel_accounts` already serves as the primary account identifier (bank card number, Alipay ID). USDT address should go there directly.

**Files:**
- Modify: `api/app/Models/UserChannelAccount.php:54` — remove DETAIL_KEY_WALLET_ADDRESS constant
- Modify: `api/app/Services/Transaction/CreateTransactionService.php:461` — read from `$account->account`
- Modify: `api/app/Http/Controllers/ThirdParty/MatchedJsonResponse.php:33-34` — read from `from_channel_account.account`
- Modify: `api/app/Services/Crypto/CryptoMonitorService.php` — read address from `$account->account`

**Step 1: Remove DETAIL_KEY_WALLET_ADDRESS from UserChannelAccount model**

In `api/app/Models/UserChannelAccount.php`, delete line 54:
```php
const DETAIL_KEY_WALLET_ADDRESS = 'wallet_address'; // USDT 收款地址
```

**Step 2: Update CreateTransactionService monitor creation**

In `api/app/Services/Transaction/CreateTransactionService.php`, change line 461 from:
```php
'address' => data_get($account->detail, UserChannelAccount::DETAIL_KEY_WALLET_ADDRESS, ''),
```
to:
```php
'address' => $account->account ?? '',
```

**Step 3: Update MatchedJsonResponse**

In `api/app/Http/Controllers/ThirdParty/MatchedJsonResponse.php`, change lines 33-34 from:
```php
$info['wallet_address'] =
    data_get($transaction->from_channel_account, 'wallet_address', '');
```
to:
```php
$info['wallet_address'] =
    data_get($transaction->from_channel_account, 'account', '');
```

**Step 4: Update CryptoMonitorService**

In `api/app/Services/Crypto/CryptoMonitorService.php`, find any reference to `DETAIL_KEY_WALLET_ADDRESS` and replace with reading from `$monitor->address` (which is already populated from `account` after Step 2).

**Step 5: Verify PHP syntax**

```bash
export PATH="/usr/local/opt/php@8.3/bin:$PATH"
php -l api/app/Models/UserChannelAccount.php
php -l api/app/Services/Transaction/CreateTransactionService.php
php -l api/app/Http/Controllers/ThirdParty/MatchedJsonResponse.php
php -l api/app/Services/Crypto/CryptoMonitorService.php
```

**Step 6: Commit**

```bash
git add api/app/Models/UserChannelAccount.php api/app/Services/Transaction/CreateTransactionService.php api/app/Http/Controllers/ThirdParty/MatchedJsonResponse.php api/app/Services/Crypto/CryptoMonitorService.php
git commit -m "refactor: use account field for USDT address instead of detail.wallet_address"
```

---

### Task 2: Remove mall_sync_at from Transaction model and add chain_network/tx_hash to API resources

**Context:** `mall_sync_at` is dead — only in migration and fillable, never used in business logic. Also, chain_network and tx_hash need to be returned in the Transaction and Withdraw API resources so the frontend can display them.

**Files:**
- Modify: `api/app/Models/Transaction.php:103` — remove `mall_sync_at` from `$dates`
- Modify: `api/app/Models/Transaction.php:63-110` — remove `mall_sync_at` from `$fillable`
- Modify: `api/app/Http/Resources/Admin/Transaction.php` — add `chain_network`, `tx_hash` to output; remove `red_envelope_password`, `re_qq_qrcode_path` (dead channel fields)
- Modify: `api/app/Http/Resources/Admin/Withdraw.php` — add `chain_network`, `tx_hash`

**Step 1: Clean Transaction model**

In `api/app/Models/Transaction.php`:
- Remove `'mall_sync_at'` from the `$fillable` array (line 103)
- Remove `'mall_sync_at'` from the `$dates` array if present

**Step 2: Add chain_network and tx_hash to Admin Transaction resource**

In `api/app/Http/Resources/Admin/Transaction.php`, add after the `'usdt_rate'` line:
```php
'chain_network' => $this->chain_network,
'tx_hash' => $this->tx_hash,
```

Also remove the dead red envelope fields:
```php
'red_envelope_password' => ...
're_qq_qrcode_path' => ...
```

**Step 3: Add chain_network and tx_hash to Admin Withdraw resource**

In `api/app/Http/Resources/Admin/Withdraw.php`, add:
```php
'chain_network' => $this->chain_network,
'tx_hash' => $this->tx_hash,
```

**Step 4: Verify PHP syntax**

```bash
export PATH="/usr/local/opt/php@8.3/bin:$PATH"
php -l api/app/Models/Transaction.php
php -l api/app/Http/Resources/Admin/Transaction.php
php -l api/app/Http/Resources/Admin/Withdraw.php
```

**Step 5: Commit**

```bash
git add api/app/Models/Transaction.php api/app/Http/Resources/Admin/Transaction.php api/app/Http/Resources/Admin/Withdraw.php
git commit -m "cleanup: remove mall_sync_at, add chain_network/tx_hash to API resources"
```

---

## Phase 2: Frontend Cleanup — Remove Old Channel Code References

### Task 3: Clean up shared package channel.ts

**Context:** `packages/shared/src/lib/channel.ts` has a `ChannelCode` object with GCash, Maya, QR_ALIPAY, BANK_CARD — all deleted channels. Also has `SyncStatus` and `AccountStatus` maps that were for GCASH/MAYA device sync. The `Detail` interface in `userChannel.ts` has GCASH-specific fields (mpin, sync_status, etc.).

**Files:**
- Modify: `packages/shared/src/lib/channel.ts:41-46` — replace ChannelCode, remove SyncStatus/AccountStatus
- Modify: `packages/shared/src/interfaces/userChannel.ts:52-67` — update Detail interface for USDT

**Step 1: Update channel.ts**

Replace the entire `ChannelCode` object:
```typescript
export const ChannelCode = {
    USDT: "USDT",
};
```

Remove `SyncStatus` and `AccountStatus` maps entirely (these were for GCASH/MAYA device sync).

**Step 2: Check for SyncStatus/AccountStatus usage**

Search for imports of `SyncStatus` and `AccountStatus` in `apps/admin/src` and `apps/merchant/src`. Remove any imports and usages found.

**Step 3: Update Detail interface in shared userChannel.ts**

In `packages/shared/src/interfaces/userChannel.ts`, update the `Detail` interface:
```typescript
export interface Detail {
    account?: string;
    bank_name?: string;
    bank_card_branch?: string;
    bank_card_number?: string;
    bank_card_holder_name?: string;
    chain_network?: string;       // USDT: trc20, erc20, bep20
    encrypted_private_key?: string; // USDT: write-only, never returned by API
    redirect_url?: string;
    receiver_name?: string;
    processed_qr_code_file_path?: string;
    qr_code_file_path?: string;
}
```

Remove the GCASH-specific fields: `mpin`, `sync_at`, `sync_status`, `account_status`, `otp`, `pwd`, `password_status`, `email_status`, `email`.

**Step 4: Do the same for admin interfaces**

In `apps/admin/src/interfaces/userChannel.ts`, make the same changes to the `Detail` interface.

**Step 5: Verify TypeScript compiles**

```bash
cd /Users/apple/projects/morgan/ustd && pnpm -r typecheck
```

**Step 6: Commit**

```bash
git add packages/shared/ apps/admin/src/interfaces/userChannel.ts
git commit -m "cleanup: remove old channel codes and GCASH-specific fields from shared package"
```

---

### Task 4: Clean up admin UserChannelAccount create form

**Context:** `apps/admin/src/pages/userChannel/create/create.tsx` has conditional rendering for BANK_CARD, ALIPAY_SAC/BAC/GC, ALIPAY_COPY, QR_* channels. All deleted. Replace with USDT-specific fields (chain_network dropdown). The `account` field (bank_card_number) should become a generic "account" field — for USDT it's the wallet address.

**Files:**
- Modify: `apps/admin/src/pages/userChannel/create/create.tsx:95-130` — replace channel-specific conditionals
- Delete: `apps/admin/src/pages/userChannel/create/components/QRCodeFields.tsx` — no longer needed
- Delete: `apps/admin/src/pages/userChannel/create/components/BankFields.tsx` — no longer needed

**Step 1: Update create.tsx**

Remove imports for `QRCodeFields` and `BankFields`:
```typescript
// DELETE these lines:
import { QRCodeFields } from './components/QRCodeFields';
import { BankFields } from './components/BankFields';
```

Replace the channel-specific block (lines 95-130) with:
```tsx
{curChannelCode && (
  <FormColumn>
    <Form.Item
      label={t('fields.account')}
      name="bank_card_number"
      rules={[{ required: true }]}
    >
      <Input placeholder={curChannelCode === 'USDT' ? t('placeholders.walletAddress') : ''} />
    </Form.Item>
  </FormColumn>
)}

{curChannelCode === 'USDT' && (
  <FormColumn>
    <Form.Item
      label={t('fields.chainNetwork')}
      name="chain_network"
      rules={[{ required: true }]}
      initialValue="trc20"
    >
      <Select
        options={[
          { label: 'TRC-20', value: 'trc20' },
          { label: 'ERC-20', value: 'erc20' },
          { label: 'BEP-20', value: 'bep20' },
        ]}
      />
    </Form.Item>
  </FormColumn>
)}

{curChannelCode === 'USDT' && (
  <FormColumn>
    <Form.Item
      label={t('fields.privateKey')}
      name="private_key"
    >
      <Input.Password placeholder={t('placeholders.privateKey')} />
    </Form.Item>
  </FormColumn>
)}
```

**Step 2: Add i18n keys**

Add translation keys to the userChannel translation file (find the location by searching for existing keys like `fields.bankCardNumber`). Add:
- `fields.account`: "帳號"
- `fields.chainNetwork`: "鏈網路"
- `fields.privateKey`: "私鑰（出款用）"
- `placeholders.walletAddress`: "輸入 USDT 錢包地址"
- `placeholders.privateKey`: "僅出款時需要，留空則不設定"

**Step 3: Delete QRCodeFields.tsx and BankFields.tsx**

```bash
rm apps/admin/src/pages/userChannel/create/components/QRCodeFields.tsx
rm apps/admin/src/pages/userChannel/create/components/BankFields.tsx
```

**Step 4: Update useUserChannelForm hook**

Find `apps/admin/src/pages/userChannel/create/hooks/useUserChannelForm.ts` and ensure `chain_network` and `private_key` are included in the form submission. The `chain_network` should be sent as `detail.chain_network` and `private_key` as `detail.encrypted_private_key`.

**Step 5: Verify TypeScript compiles**

```bash
cd /Users/apple/projects/morgan/ustd && pnpm --filter @morgan-ustd/admin typecheck
```

**Step 6: Commit**

```bash
git add apps/admin/src/pages/userChannel/
git commit -m "refactor: replace old channel-specific fields with USDT chain_network and private_key in create form"
```

---

### Task 5: Clean up admin transaction utils and merchant PayForAnother

**Context:**
- `apps/admin/src/pages/transaction/utils.tsx` has `getReceiptUrl()` with MAYA/GCASH references
- `apps/merchant/src/pages/PayForAnother/create.tsx` has BANK_CARD, GCASH, MAYA, QR_ALIPAY channel code references

**Files:**
- Modify: `apps/admin/src/pages/transaction/utils.tsx` — remove MAYA/GCASH receipt URL logic
- Modify: `apps/merchant/src/pages/PayForAnother/create.tsx` — clean up old channel codes

**Step 1: Simplify getReceiptUrl**

In `apps/admin/src/pages/transaction/utils.tsx`, replace the entire function:
```typescript
import { InternalTransfer } from "interfaces/internalTransfer";
import { Withdraw } from "@morgan-ustd/shared";

export const getReceiptUrl = (record: Withdraw | InternalTransfer) => {
    return `${process.env.REACT_APP_HOST}/v1/receipt/${record.system_order_number}`;
};
```

**Step 2: Clean up merchant PayForAnother create form**

In `apps/merchant/src/pages/PayForAnother/create.tsx`:

Update `selectOptions` (line 50-55) to include USDT:
```typescript
const selectOptions: SelectOptions = [
    {
        label: translate("channels.USDT"),
        value: "USDT",
    },
];
```

Update `getCardLabel` (line 56-71):
```typescript
const getCardLabel = (index: number) => {
    const type = lists?.[index]?.type;
    switch (type) {
        case "USDT":
            return translate("withdraw.fields.walletAddress");
    }
    return translate("withdraw.fields.account");
};
```

Update form submission (lines 107-113), remove old channel code handling:
```typescript
for (let item of values.lists) {
    if (item.type === "USDT") {
        item.bank_name = "USDT";
    }
    // ... rest of submission logic
}
```

Update the `isBankCard` variable reference (line 155) — since BANK_CARD is removed, remove all `isBankCard` conditionals. For USDT, we don't need bank name/province/city fields. Simplify the form to show wallet address + holder name only.

Also update the default initialValue (line 147) from `type: "BANK_CARD"` to `type: "USDT"` and the add button default (line 259).

**Step 3: Verify TypeScript compiles**

```bash
cd /Users/apple/projects/morgan/ustd && pnpm -r typecheck
```

**Step 4: Commit**

```bash
git add apps/admin/src/pages/transaction/utils.tsx apps/merchant/src/pages/PayForAnother/create.tsx
git commit -m "cleanup: remove MAYA/GCASH/BANK_CARD references from admin utils and merchant PayForAnother"
```

---

## Phase 3: Frontend USDT Features

### Task 6: Add chain_network and tx_hash to shared Transaction interface

**Context:** Backend now returns `chain_network` and `tx_hash` in Transaction and Withdraw resources. Frontend interfaces need updating.

**Files:**
- Modify: `packages/shared/src/interfaces/transaction.ts:7-68` — add chain_network, tx_hash to Transaction interface
- Modify: `packages/shared/src/interfaces/transaction.ts:245-252` — update FromChannelAccount for USDT

**Step 1: Add fields to Transaction interface**

In `packages/shared/src/interfaces/transaction.ts`, add after `usdt_rate: string;` (line 62):
```typescript
chain_network?: string;
tx_hash?: string;
```

**Step 2: Update FromChannelAccount interface**

In the same file, update `FromChannelAccount` (line 245-252) to include USDT fields:
```typescript
export interface FromChannelAccount {
    bank_city?: string;
    bank_name?: string;
    bank_province?: string;
    bank_card_number?: string;
    extra_withdraw_fee: number;
    bank_card_holder_name?: string;
    account?: string;
    chain_network?: string;
}
```

**Step 3: Verify TypeScript compiles**

```bash
cd /Users/apple/projects/morgan/ustd && pnpm -r typecheck
```

**Step 4: Commit**

```bash
git add packages/shared/src/interfaces/transaction.ts
git commit -m "feat: add chain_network and tx_hash to Transaction interface"
```

---

### Task 7: Add chain_network and tx_hash columns to admin transaction list pages

**Context:** Admin collection list and PayForAnother list need new columns for chain_network and tx_hash. These should show for all transactions but will only have values for USDT.

**Files:**
- Modify: `apps/admin/src/pages/transaction/collection/columns/` — add chain_network column
- Modify: `apps/admin/src/pages/transaction/PayForAnother/components/columns/` — add chain_network, tx_hash columns

**Step 1: Identify the column definition files**

Read the column index files to understand how columns are structured:
- `apps/admin/src/pages/transaction/collection/columns/index.tsx`
- `apps/admin/src/pages/transaction/PayForAnother/components/columns/index.tsx`

**Step 2: Add chain_network column to collection**

Add a new column after channel column that displays `chain_network` if present:
```tsx
{
    title: t('fields.chainNetwork'),
    dataIndex: 'chain_network',
    render: (value: string) => value ? value.toUpperCase() : '-',
    width: 80,
}
```

**Step 3: Add chain_network and tx_hash columns to PayForAnother (withdraw)**

```tsx
{
    title: t('fields.chainNetwork'),
    dataIndex: 'chain_network',
    render: (value: string) => value ? value.toUpperCase() : '-',
    width: 80,
},
{
    title: 'Tx Hash',
    dataIndex: 'tx_hash',
    render: (value: string) => value ? (
        <Typography.Text copyable ellipsis style={{ maxWidth: 120 }}>
            {value}
        </Typography.Text>
    ) : '-',
    width: 140,
}
```

**Step 4: Verify TypeScript compiles**

```bash
cd /Users/apple/projects/morgan/ustd && pnpm --filter @morgan-ustd/admin typecheck
```

**Step 5: Commit**

```bash
git add apps/admin/src/pages/transaction/
git commit -m "feat: add chain_network and tx_hash columns to admin transaction lists"
```

---

## Phase 4: Backend — Self-Operated USDT Withdrawal

### Task 8: Store encrypted private key in UserChannelAccount detail

**Context:** For self-operated USDT withdrawal, the private key is stored encrypted in `detail.encrypted_private_key`. The backend needs to accept it on create/update and encrypt it. It must NEVER be returned in API responses.

**Files:**
- Modify: `api/app/Models/UserChannelAccount.php` — add DETAIL_KEY_ENCRYPTED_PRIVATE_KEY constant
- Modify: `api/app/Services/UserChannelAccount/UserChannelAccountService.php` — encrypt private_key on create
- Modify: `api/app/Http/Resources/Admin/UserChannelAccount.php` (or equivalent) — ensure private key is never returned
- Modify: `api/app/Http/Controllers/Admin/UserChannelAccountController.php` — accept chain_network and private_key in store/update

**Step 1: Add constant to model**

In `api/app/Models/UserChannelAccount.php`, add after DETAIL_KEY_CHAIN_NETWORK:
```php
const DETAIL_KEY_ENCRYPTED_PRIVATE_KEY = 'encrypted_private_key'; // USDT 出款私鑰（加密儲存）
```

**Step 2: Update UserChannelAccountController store method**

In the controller's `store()` method, accept `chain_network` and `private_key` from request. Pass them to the service:
```php
$data['detail'] = array_merge($data['detail'] ?? [], array_filter([
    'chain_network' => $request->input('chain_network'),
]));

if ($request->filled('private_key')) {
    $data['detail']['encrypted_private_key'] = encrypt($request->input('private_key'));
}
```

**Step 3: Ensure private key is scrubbed from API responses**

In any Resource class that returns `detail`, ensure `encrypted_private_key` is removed:
```php
$detail = $this->detail ?? [];
unset($detail['encrypted_private_key']);
```

**Step 4: Verify PHP syntax**

```bash
export PATH="/usr/local/opt/php@8.3/bin:$PATH"
php -l api/app/Models/UserChannelAccount.php
php -l api/app/Services/UserChannelAccount/UserChannelAccountService.php
php -l api/app/Http/Controllers/Admin/UserChannelAccountController.php
```

**Step 5: Commit**

```bash
git add api/app/Models/UserChannelAccount.php api/app/Services/UserChannelAccount/UserChannelAccountService.php api/app/Http/Controllers/Admin/UserChannelAccountController.php
git commit -m "feat: store encrypted private key for USDT withdrawal accounts"
```

---

### Task 9: Install ECDSA signing library and extend ChainAdapterInterface

**Context:** TRC-20 token transfer requires signing transactions with secp256k1 ECDSA. We need a PHP library for this. Also extend ChainAdapterInterface with a `sendTransaction()` method for outgoing transfers.

**Files:**
- Modify: `api/composer.json` — add `simplito/elliptic-php` dependency
- Modify: `api/app/Services/Crypto/Adapters/ChainAdapterInterface.php` — add sendTransaction method
- Modify: `api/app/Services/Crypto/DTO/ChainTransaction.php` — ensure it works for outgoing too

**Step 1: Install elliptic-php**

```bash
cd /Users/apple/projects/morgan/ustd/api && composer require simplito/elliptic-php
```

**Step 2: Extend ChainAdapterInterface**

Add to `api/app/Services/Crypto/Adapters/ChainAdapterInterface.php`:
```php
/**
 * 發送 USDT 到指定地址
 *
 * @return ChainTransaction 已簽署並廣播的交易資訊
 * @throws \App\Services\Crypto\Exceptions\InsufficientBalanceException
 * @throws \App\Services\Crypto\Exceptions\TransactionBroadcastException
 */
public function sendTransaction(
    string $fromAddress,
    string $toAddress,
    string $amount,
    string $privateKey
): ChainTransaction;
```

**Step 3: Create exception classes**

Create `api/app/Services/Crypto/Exceptions/InsufficientBalanceException.php`:
```php
<?php
namespace App\Services\Crypto\Exceptions;

class InsufficientBalanceException extends \RuntimeException {}
```

Create `api/app/Services/Crypto/Exceptions/TransactionBroadcastException.php`:
```php
<?php
namespace App\Services\Crypto\Exceptions;

class TransactionBroadcastException extends \RuntimeException {}
```

**Step 4: Verify PHP syntax**

```bash
export PATH="/usr/local/opt/php@8.3/bin:$PATH"
php -l api/app/Services/Crypto/Adapters/ChainAdapterInterface.php
php -l api/app/Services/Crypto/Exceptions/InsufficientBalanceException.php
php -l api/app/Services/Crypto/Exceptions/TransactionBroadcastException.php
```

**Step 5: Commit**

```bash
git add api/composer.json api/composer.lock api/app/Services/Crypto/
git commit -m "feat: add ECDSA signing library and extend ChainAdapterInterface with sendTransaction"
```

---

### Task 10: Implement Trc20Adapter sendTransaction

**Context:** Implement sending TRC-20 USDT via TronGrid API. Flow: (1) Create unsigned triggerSmartContract transaction via TronGrid, (2) Sign with private key using elliptic-php, (3) Broadcast signed transaction, (4) Return ChainTransaction with tx_hash.

**Files:**
- Modify: `api/app/Services/Crypto/Adapters/Trc20Adapter.php` — implement sendTransaction

**Step 1: Implement sendTransaction in Trc20Adapter**

```php
public function sendTransaction(
    string $fromAddress,
    string $toAddress,
    string $amount,
    string $privateKey
): ChainTransaction {
    // 1. 將金額轉為最小單位 (6 decimals)
    $rawAmount = bcmul($amount, bcpow('10', '6'), 0);

    // 2. 建立 triggerSmartContract 交易
    $unsignedTx = $this->createTriggerSmartContract($fromAddress, $toAddress, $rawAmount);

    // 3. 簽署交易
    $signedTx = $this->signTransaction($unsignedTx, $privateKey);

    // 4. 廣播交易
    $txHash = $this->broadcastTransaction($signedTx);

    return new ChainTransaction(
        txHash: $txHash,
        from: $fromAddress,
        to: $toAddress,
        amount: $amount,
        timestamp: (int) (microtime(true) * 1000),
        confirmations: 0,
    );
}
```

The implementation needs these private helper methods:

`createTriggerSmartContract()` — calls TronGrid API `/wallet/triggersmartcontract` with the USDT contract's `transfer(address,uint256)` function selector.

`signTransaction()` — uses `Elliptic\EC` to sign the transaction's `txID` (raw_data_hex hash) with the private key.

`broadcastTransaction()` — calls TronGrid API `/wallet/broadcasttransaction` with the signed payload.

The TRON address encoding needs a helper to convert base58check TRON addresses to hex format for the smart contract parameter.

**Step 2: Verify PHP syntax**

```bash
export PATH="/usr/local/opt/php@8.3/bin:$PATH"
php -l api/app/Services/Crypto/Adapters/Trc20Adapter.php
```

**Step 3: Commit**

```bash
git add api/app/Services/Crypto/Adapters/Trc20Adapter.php
git commit -m "feat: implement TRC-20 USDT sendTransaction with signing and broadcasting"
```

---

### Task 11: Create UsdtWithdrawHandler and integrate into withdrawal flow

**Context:** When a USDT withdrawal is approved (status changes to PAYING), the system should automatically send the USDT on-chain. This integrates with the existing `TransactionStatusService` or can be a separate handler dispatched as a job.

**Files:**
- Create: `api/app/Services/Crypto/UsdtWithdrawHandler.php` — handles sending USDT for approved withdrawals
- Create: `api/app/Jobs/ProcessUsdtWithdraw.php` — async job for processing USDT withdrawal
- Modify: `api/app/Services/Transaction/TransactionStatusService.php` — dispatch job when USDT withdrawal is approved

**Step 1: Create UsdtWithdrawHandler**

```php
<?php
namespace App\Services\Crypto;

use App\Models\Channel;
use App\Models\Transaction;
use App\Models\UserChannelAccount;
use App\Services\Crypto\Adapters\ChainAdapterInterface;
use App\Services\Crypto\Adapters\Trc20Adapter;
use App\Services\Crypto\Exceptions\InsufficientBalanceException;
use App\Services\Crypto\Exceptions\TransactionBroadcastException;
use App\Services\Transaction\TransactionStatusService;
use Illuminate\Support\Facades\Log;

class UsdtWithdrawHandler
{
    public function handle(Transaction $transaction): void
    {
        // 取得出款帳號（from_channel_account_id = 出款方帳號）
        $account = UserChannelAccount::find($transaction->from_channel_account_id);
        if (!$account) {
            Log::error('UsdtWithdrawHandler: 找不到出款帳號', ['transaction_id' => $transaction->id]);
            return;
        }

        $encryptedKey = data_get($account->detail, UserChannelAccount::DETAIL_KEY_ENCRYPTED_PRIVATE_KEY);
        if (!$encryptedKey) {
            Log::warning('UsdtWithdrawHandler: 帳號未設定私鑰，跳過自動出款', ['account_id' => $account->id]);
            return;
        }

        $chainNetwork = data_get($account->detail, UserChannelAccount::DETAIL_KEY_CHAIN_NETWORK, 'trc20');
        $adapter = $this->resolveAdapter($chainNetwork);

        $privateKey = decrypt($encryptedKey);
        $fromAddress = $account->account;
        $toAddress = data_get($transaction->to_channel_account, 'bank_card_number', '');
        $amount = $transaction->floating_amount ?? $transaction->amount;

        try {
            $chainTx = $adapter->sendTransaction($fromAddress, $toAddress, $amount, $privateKey);

            $transaction->update([
                'tx_hash' => $chainTx->txHash,
                'chain_network' => $chainNetwork,
            ]);

            Log::info('UsdtWithdrawHandler: 出款成功', [
                'transaction_id' => $transaction->id,
                'tx_hash' => $chainTx->txHash,
            ]);
        } catch (InsufficientBalanceException $e) {
            Log::error('UsdtWithdrawHandler: 餘額不足', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        } catch (TransactionBroadcastException $e) {
            Log::error('UsdtWithdrawHandler: 廣播失敗', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveAdapter(string $chainNetwork): ChainAdapterInterface
    {
        return match ($chainNetwork) {
            'trc20' => app(Trc20Adapter::class),
            default => throw new \InvalidArgumentException("不支援的鏈網路: {$chainNetwork}"),
        };
    }
}
```

**Step 2: Create ProcessUsdtWithdraw job**

```php
<?php
namespace App\Jobs;

use App\Models\Transaction;
use App\Services\Crypto\UsdtWithdrawHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessUsdtWithdraw implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly int $transactionId
    ) {}

    public function handle(UsdtWithdrawHandler $handler): void
    {
        $transaction = Transaction::find($this->transactionId);
        if (!$transaction) return;

        $handler->handle($transaction);
    }
}
```

**Step 3: Dispatch job when USDT withdrawal is approved**

In `api/app/Services/Transaction/TransactionStatusService.php`, find where withdrawal status changes to `STATUS_PAYING` (when approved). After the status update, add:
```php
// 自動處理 USDT 出款
if ($transaction->channel_code === Channel::CODE_USDT && !$transaction->thirdchannel_id) {
    ProcessUsdtWithdraw::dispatch($transaction->id);
}
```

**Step 4: Verify PHP syntax**

```bash
export PATH="/usr/local/opt/php@8.3/bin:$PATH"
php -l api/app/Services/Crypto/UsdtWithdrawHandler.php
php -l api/app/Jobs/ProcessUsdtWithdraw.php
php -l api/app/Services/Transaction/TransactionStatusService.php
```

**Step 5: Commit**

```bash
git add api/app/Services/Crypto/UsdtWithdrawHandler.php api/app/Jobs/ProcessUsdtWithdraw.php api/app/Services/Transaction/TransactionStatusService.php
git commit -m "feat: auto-process USDT withdrawal with on-chain signing when approved"
```

---

### Task 12: Add TronGrid API key configuration

**Context:** TronGrid free tier has rate limits. An API key increases the limit significantly. Add configuration support.

**Files:**
- Modify: `api/config/services.php` — add trongrid configuration
- Modify: `api/app/Services/Crypto/Adapters/Trc20Adapter.php` — use API key in requests

**Step 1: Add config**

In `api/config/services.php`, add:
```php
'trongrid' => [
    'api_key' => env('TRONGRID_API_KEY'),
    'base_url' => env('TRONGRID_BASE_URL', 'https://api.trongrid.io'),
],
```

**Step 2: Update Trc20Adapter to use config**

Replace the hardcoded `TRONGRID_BASE_URL` constant. In the HTTP calls, add the API key header:
```php
$headers = [];
$apiKey = config('services.trongrid.api_key');
if ($apiKey) {
    $headers['TRON-PRO-API-KEY'] = $apiKey;
}

Http::timeout(10)
    ->withHeaders($headers)
    ->get(...)
```

**Step 3: Commit**

```bash
git add api/config/services.php api/app/Services/Crypto/Adapters/Trc20Adapter.php
git commit -m "feat: add TronGrid API key configuration for rate limit increase"
```

---

### Task 13: Final verification

**Step 1: Run PHP syntax check on all modified files**

```bash
export PATH="/usr/local/opt/php@8.3/bin:$PATH"
find api/app -name "*.php" -newer api/composer.json -exec php -l {} \;
```

**Step 2: Run frontend typecheck**

```bash
cd /Users/apple/projects/morgan/ustd && pnpm -r typecheck
```

**Step 3: Review all changes**

```bash
git diff HEAD~12..HEAD --stat
git log --oneline HEAD~12..HEAD
```
