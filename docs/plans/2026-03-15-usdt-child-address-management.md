# USDT 子地址管理 Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** 讓 USDT 收付款帳號支援主地址/子地址區分，支援 HD 衍生自動產生、手動輸入、批量建立、歸集功能。

**Architecture:** 在 `user_channel_accounts` 表新增 `address_type`、`parent_account_id`、`derivation_index` 欄位。新增 `TronAddressService` 負責從 master private key 衍生子地址（secp256k1 + keccak256）。歸集功能透過 `ConsolidationService` 將子地址 USDT 轉回主地址，需先從主地址送 TRX 給子地址作為 gas。前端在建立帳號時可選地址類型，批量建立子地址使用獨立 UI。

**Tech Stack:** PHP 8.2+ / Laravel 11 / `simplito/elliptic-php`（已安裝）/ `kornrunner/keccak`（需安裝）/ React 18 / Refine v5 / Ant Design v5

---

## Task 1: 安裝 keccak 套件

TRON 地址需要 keccak-256 雜湊，目前專案沒有此套件。

**Files:**
- Modify: `api/composer.json`

**Step 1: 安裝 kornrunner/keccak**

```bash
cd api && composer require kornrunner/keccak
```

**Step 2: 驗證安裝**

```bash
cd api && php -r "echo (new \kornrunner\Keccak())->hash('test', 256);"
```

Expected: 輸出 64 字元的 hex hash

**Step 3: Commit**

```bash
git add api/composer.json api/composer.lock
git commit -m "chore: add kornrunner/keccak for TRON address derivation"
```

---

## Task 2: TronAddressService — 地址衍生核心

從 master private key + index 衍生子地址。純數學運算，不呼叫外部 API。

**Files:**
- Create: `api/app/Services/Crypto/TronAddressService.php`
- Create: `api/tests/Unit/Services/Crypto/TronAddressServiceTest.php`

**Step 1: 寫測試**

```php
<?php

namespace Tests\Unit\Services\Crypto;

use App\Services\Crypto\TronAddressService;
use Tests\TestCase;

class TronAddressServiceTest extends TestCase
{
    private TronAddressService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TronAddressService();
    }

    public function test_private_key_to_address_returns_valid_tron_address(): void
    {
        // Known test vector: private key → TRON address
        // Using a well-known test private key (DO NOT use in production)
        $privateKey = 'a0b1c2d3e4f5a6b7c8d9e0f1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1';
        $address = $this->service->privateKeyToAddress($privateKey);

        $this->assertMatchesRegularExpression('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $address);
    }

    public function test_same_private_key_always_produces_same_address(): void
    {
        $privateKey = 'a0b1c2d3e4f5a6b7c8d9e0f1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1';
        $address1 = $this->service->privateKeyToAddress($privateKey);
        $address2 = $this->service->privateKeyToAddress($privateKey);

        $this->assertSame($address1, $address2);
    }

    public function test_derive_child_key_returns_valid_hex(): void
    {
        $masterKey = 'a0b1c2d3e4f5a6b7c8d9e0f1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1';
        $childKey = $this->service->deriveChildKey($masterKey, 0);

        $this->assertSame(64, strlen($childKey));
        $this->assertTrue(ctype_xdigit($childKey));
    }

    public function test_different_indexes_produce_different_keys(): void
    {
        $masterKey = 'a0b1c2d3e4f5a6b7c8d9e0f1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1';
        $child0 = $this->service->deriveChildKey($masterKey, 0);
        $child1 = $this->service->deriveChildKey($masterKey, 1);

        $this->assertNotSame($child0, $child1);
    }

    public function test_same_index_always_produces_same_key(): void
    {
        $masterKey = 'a0b1c2d3e4f5a6b7c8d9e0f1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1';
        $child1 = $this->service->deriveChildKey($masterKey, 5);
        $child2 = $this->service->deriveChildKey($masterKey, 5);

        $this->assertSame($child1, $child2);
    }

    public function test_derive_child_address_returns_valid_tron_address(): void
    {
        $masterKey = 'a0b1c2d3e4f5a6b7c8d9e0f1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1';
        $result = $this->service->deriveChildAccount($masterKey, 0);

        $this->assertArrayHasKey('address', $result);
        $this->assertArrayHasKey('private_key', $result);
        $this->assertMatchesRegularExpression('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $result['address']);
        $this->assertSame(64, strlen($result['private_key']));
    }

    public function test_derived_address_matches_derived_key(): void
    {
        $masterKey = 'a0b1c2d3e4f5a6b7c8d9e0f1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1';
        $result = $this->service->deriveChildAccount($masterKey, 0);

        $expectedAddress = $this->service->privateKeyToAddress($result['private_key']);
        $this->assertSame($expectedAddress, $result['address']);
    }

    public function test_generate_key_pair_returns_valid_pair(): void
    {
        $pair = $this->service->generateKeyPair();

        $this->assertArrayHasKey('address', $pair);
        $this->assertArrayHasKey('private_key', $pair);
        $this->assertMatchesRegularExpression('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $pair['address']);
        $this->assertSame(64, strlen($pair['private_key']));

        // Verify address matches the private key
        $expectedAddress = $this->service->privateKeyToAddress($pair['private_key']);
        $this->assertSame($expectedAddress, $pair['address']);
    }
}
```

**Step 2: 跑測試確認失敗**

```bash
cd api && php artisan test tests/Unit/Services/Crypto/TronAddressServiceTest.php
```

Expected: FAIL — class not found

**Step 3: 實作 TronAddressService**

```php
<?php

namespace App\Services\Crypto;

use Elliptic\EC;
use kornrunner\Keccak;

class TronAddressService
{
    private const SECP256K1_ORDER = 'FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141';

    /**
     * 從 private key (hex) 產生 TRON base58check 地址
     */
    public function privateKeyToAddress(string $privateKeyHex): string
    {
        $ec = new EC('secp256k1');
        $key = $ec->keyFromPrivate($privateKeyHex, 'hex');

        // 取未壓縮公鑰 (65 bytes: 04 + x + y)，移除 04 前綴
        $publicKeyHex = $key->getPublic(false, 'hex');
        $publicKeyNoPrefix = substr($publicKeyHex, 2);

        // Keccak-256 hash
        $hash = Keccak::hash(hex2bin($publicKeyNoPrefix), 256);

        // 取最後 20 bytes，加上 TRON 前綴 0x41
        $addressHex = '41' . substr($hash, -40);

        return $this->hexToBase58Check($addressHex);
    }

    /**
     * 從 master private key 衍生子 private key
     * 使用 HMAC-SHA512 確保確定性和安全性
     */
    public function deriveChildKey(string $masterPrivateKeyHex, int $index): string
    {
        $data = hex2bin($masterPrivateKeyHex) . pack('N', $index);
        $hmac = hash_hmac('sha512', $data, 'tron-hd-child', true);

        // 取前 32 bytes 作為 child key modifier
        $childModifier = substr($hmac, 0, 32);

        // (master_key + modifier) mod n
        $masterBN = gmp_init($masterPrivateKeyHex, 16);
        $modifierBN = gmp_init(bin2hex($childModifier), 16);
        $n = gmp_init(self::SECP256K1_ORDER, 16);

        $childKey = gmp_mod(gmp_add($masterBN, $modifierBN), $n);

        return str_pad(gmp_strval($childKey, 16), 64, '0', STR_PAD_LEFT);
    }

    /**
     * 從 master private key 衍生完整的子帳號（地址 + 私鑰）
     *
     * @return array{address: string, private_key: string}
     */
    public function deriveChildAccount(string $masterPrivateKeyHex, int $index): array
    {
        $childKey = $this->deriveChildKey($masterPrivateKeyHex, $index);

        return [
            'address' => $this->privateKeyToAddress($childKey),
            'private_key' => $childKey,
        ];
    }

    /**
     * 產生全新的 key pair（用於手動建立不需要母地址的帳號）
     *
     * @return array{address: string, private_key: string}
     */
    public function generateKeyPair(): array
    {
        $ec = new EC('secp256k1');
        $keyPair = $ec->genKeyPair();
        $privateKeyHex = str_pad($keyPair->getPrivate('hex'), 64, '0', STR_PAD_LEFT);

        return [
            'address' => $this->privateKeyToAddress($privateKeyHex),
            'private_key' => $privateKeyHex,
        ];
    }

    /**
     * 將 hex 地址轉為 TRON base58check 格式
     */
    private function hexToBase58Check(string $hex): string
    {
        $payload = hex2bin($hex);
        $checksum = substr(hash('sha256', hash('sha256', $payload, true)), 0, 8);
        $fullHex = $hex . $checksum;

        return $this->hexToBase58($fullHex);
    }

    private function hexToBase58(string $hex): string
    {
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $num = gmp_init($hex, 16);
        $base = gmp_init(58);

        $result = '';
        while (gmp_cmp($num, 0) > 0) {
            [$num, $rem] = gmp_div_qr($num, $base);
            $result = $alphabet[gmp_intval($rem)] . $result;
        }

        // Leading zero bytes → leading '1' in base58
        $leadingZeros = 0;
        for ($i = 0; $i < strlen($hex); $i += 2) {
            if (substr($hex, $i, 2) === '00') {
                $leadingZeros++;
            } else {
                break;
            }
        }

        return str_repeat('1', $leadingZeros) . $result;
    }
}
```

**Step 4: 跑測試確認通過**

```bash
cd api && php artisan test tests/Unit/Services/Crypto/TronAddressServiceTest.php
```

Expected: 全部 PASS

**Step 5: Commit**

```bash
git add api/app/Services/Crypto/TronAddressService.php api/tests/Unit/Services/Crypto/TronAddressServiceTest.php
git commit -m "feat: add TronAddressService for HD child key derivation and address generation"
```

---

## Task 3: Migration — user_channel_accounts 加欄位

**Files:**
- Create: `api/database/migrations/2026_03_15_000001_add_address_type_to_user_channel_accounts.php`

**Step 1: 建立 migration**

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
            $table->string('address_type', 10)->default('master')->after('account')
                ->comment('master=主地址, child=子地址');
            $table->unsignedInteger('parent_account_id')->nullable()->after('address_type')
                ->comment('子地址的母地址帳號 ID');
            $table->unsignedInteger('derivation_index')->nullable()->after('parent_account_id')
                ->comment('HD 衍生路徑 index');

            $table->index('address_type');
            $table->index('parent_account_id');
            $table->unique(['parent_account_id', 'derivation_index'], 'uca_parent_derivation_unique');
        });
    }

    public function down(): void
    {
        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->dropUnique('uca_parent_derivation_unique');
            $table->dropIndex(['parent_account_id']);
            $table->dropIndex(['address_type']);
            $table->dropColumn(['address_type', 'parent_account_id', 'derivation_index']);
        });
    }
};
```

**Step 2: Commit**

```bash
git add api/database/migrations/2026_03_15_000001_add_address_type_to_user_channel_accounts.php
git commit -m "feat: add address_type, parent_account_id, derivation_index to user_channel_accounts"
```

---

## Task 4: Model — UserChannelAccount 更新

**Files:**
- Modify: `api/app/Models/UserChannelAccount.php`

**Step 1: 加常數、fillable、關聯**

在 UserChannelAccount model 中新增：

```php
// 常數（加在現有常數之後）
const ADDRESS_TYPE_MASTER = 'master';
const ADDRESS_TYPE_CHILD = 'child';
```

在 `$fillable` array 中加入：
```php
'address_type',
'parent_account_id',
'derivation_index',
```

加關聯方法：
```php
public function parentAccount()
{
    return $this->belongsTo(self::class, 'parent_account_id');
}

public function childAccounts()
{
    return $this->hasMany(self::class, 'parent_account_id');
}
```

**Step 2: Commit**

```bash
git add api/app/Models/UserChannelAccount.php
git commit -m "feat: add address type constants and parent/child relationships to UserChannelAccount"
```

---

## Task 5: API Resource — 回傳新欄位

**Files:**
- Modify: `api/app/Http/Resources/UserChannelAccount.php`

**Step 1: 在 toArray 中加入新欄位**

在 API Resource 的 `toArray()` 方法中加入：

```php
'address_type' => $this->address_type,
'parent_account_id' => $this->parent_account_id,
'parent_account' => $this->when($this->parent_account_id, function () {
    return [
        'id' => $this->parentAccount?->id,
        'account' => $this->parentAccount?->account,
        'name' => $this->parentAccount?->name,
    ];
}),
'derivation_index' => $this->derivation_index,
'child_count' => $this->when($this->address_type === 'master', fn () => $this->childAccounts()->count()),
```

**Step 2: 在 controller 的 show/index 加載關聯**

在 `UserChannelAccountController::show()` 中 load `parentAccount`：

修改 `load("user.parent", "channelAmount.channel")` 為 `load("user.parent", "channelAmount.channel", "parentAccount")`

**Step 3: Commit**

```bash
git add api/app/Http/Resources/UserChannelAccount.php api/app/Http/Controllers/Admin/UserChannelAccountController.php
git commit -m "feat: return address_type, parent_account, child_count in API resource"
```

---

## Task 6: API — 子地址建立端點 (單筆 + 批量)

**Files:**
- Modify: `api/app/Http/Controllers/Admin/UserChannelAccountController.php`
- Modify: `api/app/Services/UserChannelAccount/UserChannelAccountService.php`
- Modify: `api/routes/api-v1.php`

**Step 1: UserChannelAccountService 加入子地址建立方法**

```php
use App\Services\Crypto\TronAddressService;

// 注入到 constructor
public function __construct(
    private readonly QrCodeService $qrCodeService,
    private readonly TronAddressService $tronAddressService,
) {}

/**
 * 從母地址自動衍生子地址
 */
public function createChildAccount(UserChannelAccount $parentAccount, ?int $derivationIndex = null): UserChannelAccount
{
    $encryptedKey = data_get($parentAccount->detail, UserChannelAccount::DETAIL_KEY_ENCRYPTED_PRIVATE_KEY);
    abort_if(!$encryptedKey, Response::HTTP_BAD_REQUEST, '母地址未設定私鑰，無法自動衍生');

    if ($derivationIndex === null) {
        $derivationIndex = ($parentAccount->childAccounts()->max('derivation_index') ?? -1) + 1;
    }

    // 檢查 derivation_index 唯一性
    $exists = UserChannelAccount::where('parent_account_id', $parentAccount->id)
        ->where('derivation_index', $derivationIndex)
        ->exists();
    abort_if($exists, Response::HTTP_BAD_REQUEST, "derivation_index {$derivationIndex} 已存在");

    $masterKey = decrypt($encryptedKey);
    $child = $this->tronAddressService->deriveChildAccount($masterKey, $derivationIndex);
    $masterKey = null; // 清除記憶體

    $this->validateAccountUniqueness(Channel::CODE_USDT, $child['address']);

    $detail = [
        UserChannelAccount::DETAIL_KEY_CHAIN_NETWORK => data_get(
            $parentAccount->detail,
            UserChannelAccount::DETAIL_KEY_CHAIN_NETWORK,
            'trc20'
        ),
        UserChannelAccount::DETAIL_KEY_ENCRYPTED_PRIVATE_KEY => encrypt($child['private_key']),
    ];
    $child['private_key'] = null; // 清除記憶體

    $provider = User::findOrFail($parentAccount->user_id);

    $channelAmount = $parentAccount->channelAmount;

    $userChannel = $this->validateUserChannel($provider, $channelAmount);
    $device = $this->resolveDevice($provider, $provider->name);
    $wallet = $this->resolveWallet($provider);

    $childAccount = $channelAmount->userChannelAccounts()->create([
        'user_id' => $provider->getKey(),
        'device_id' => $device->getKey(),
        'wallet_id' => $wallet->getKey(),
        'bank_id' => 0,
        'channel_code' => Channel::CODE_USDT,
        'status' => UserChannelAccount::STATUS_DISABLE,
        'type' => $parentAccount->type,
        'fee_percent' => $userChannel->fee_percent,
        'min_amount' => $userChannel->min_amount,
        'max_amount' => $userChannel->max_amount,
        'account' => $child['address'],
        'detail' => $detail,
        'address_type' => UserChannelAccount::ADDRESS_TYPE_CHILD,
        'parent_account_id' => $parentAccount->id,
        'derivation_index' => $derivationIndex,
        'is_auto' => true,
    ]);

    $childAccount->name = \Illuminate\Support\Str::padLeft($childAccount->id, 5, '0');
    $childAccount->save();

    $this->syncTransactionGroups($childAccount, $provider);

    BackfillChainTransactions::dispatch($childAccount->id);

    return $childAccount;
}

/**
 * 批量衍生子地址
 */
public function batchCreateChildAccounts(UserChannelAccount $parentAccount, int $count): array
{
    $created = [];
    $startIndex = ($parentAccount->childAccounts()->max('derivation_index') ?? -1) + 1;

    for ($i = 0; $i < $count; $i++) {
        $created[] = $this->createChildAccount($parentAccount, $startIndex + $i);
    }

    return $created;
}
```

**Step 2: Controller 加入端點**

在 `UserChannelAccountController` 中加入：

```php
public function createChild(Request $request)
{
    $this->validate($request, [
        'parent_account_id' => 'required|integer',
        'count' => 'nullable|integer|min:1|max:50',
    ]);

    $parentAccount = UserChannelAccount::findOrFail($request->input('parent_account_id'));

    abort_if(
        $parentAccount->channel_code !== Channel::CODE_USDT,
        Response::HTTP_BAD_REQUEST,
        '僅支援 USDT 帳號'
    );

    $count = $request->input('count', 1);

    if ($count === 1) {
        $child = $this->userChannelAccountService->createChildAccount($parentAccount);
        return \App\Http\Resources\UserChannelAccount::make($child);
    }

    $children = DB::transaction(function () use ($parentAccount, $count) {
        return $this->userChannelAccountService->batchCreateChildAccounts($parentAccount, $count);
    });

    return \App\Http\Resources\UserChannelAccount::collection(collect($children));
}
```

**Step 3: 更新 store() 支援 address_type**

在 `UserChannelAccountController::store()` 的 `$data` 中，加入 `address_type` 和 `parent_account_id`：

```php
// 在 $data 合併後加入
if ($request->input('address_type') === UserChannelAccount::ADDRESS_TYPE_CHILD) {
    $data['address_type'] = UserChannelAccount::ADDRESS_TYPE_CHILD;
    $data['parent_account_id'] = $request->input('parent_account_id');
}
```

同時在 `UserChannelAccountService::createAccount()` 中，將 `address_type` 和 `parent_account_id` 加入 create 陣列。

**Step 4: 註冊路由**

在 `routes/api-v1.php` 的 admin 路由中加入：

```php
Route::post('user-channel-accounts/create-child', [UserChannelAccountController::class, 'createChild']);
```

放在 `user-channel-accounts/massive-create` 附近。

**Step 5: Commit**

```bash
git add api/app/Http/Controllers/Admin/UserChannelAccountController.php \
    api/app/Services/UserChannelAccount/UserChannelAccountService.php \
    api/routes/api-v1.php
git commit -m "feat: add child address creation endpoint with HD derivation and batch support"
```

---

## Task 7: API — 歸集端點 (Consolidation)

子地址的 USDT 轉回主地址。流程：主地址先送 TRX 給子地址付 gas → 子地址送 USDT 回主地址。

**Files:**
- Create: `api/app/Services/Crypto/ConsolidationService.php`
- Create: `api/app/Jobs/ConsolidateChildAccount.php`
- Modify: `api/app/Http/Controllers/Admin/UserChannelAccountController.php`
- Modify: `api/app/Services/Crypto/Adapters/Trc20Adapter.php`
- Modify: `api/app/Services/Crypto/Adapters/ChainAdapterInterface.php`
- Modify: `api/routes/api-v1.php`

**Step 1: ChainAdapterInterface 加入 sendNativeToken 方法**

```php
/**
 * 發送原生代幣 (TRX) 到指定地址
 */
public function sendNativeToken(
    string $fromAddress,
    string $toAddress,
    string $amount,
    string $privateKey
): string; // returns tx_hash
```

**Step 2: Trc20Adapter 實作 sendNativeToken**

```php
public function sendNativeToken(
    string $fromAddress,
    string $toAddress,
    string $amount,
    string $privateKey
): string {
    $rawAmount = (int) bcmul($amount, '1000000', 0);

    $fromHex = $this->base58ToHex($fromAddress);
    $toHex = $this->base58ToHex($toAddress);

    // Create unsigned TRX transfer
    $response = $this->buildHttpClient()->post($this->getBaseUrl() . '/wallet/createtransaction', [
        'owner_address' => $fromHex,
        'to_address' => $toHex,
        'amount' => $rawAmount,
        'visible' => false,
    ]);

    $data = $response->json();
    if (!$response->successful() || !isset($data['txID'])) {
        throw new TransactionBroadcastException('Failed to create TRX transfer: ' . json_encode($data));
    }

    $signedTx = $this->signTransaction($data, $privateKey);
    return $this->broadcastTransaction($signedTx);
}
```

**Step 3: ConsolidationService**

```php
<?php

namespace App\Services\Crypto;

use App\Models\Channel;
use App\Models\UserChannelAccount;
use App\Services\Crypto\Adapters\ChainAdapterInterface;
use App\Services\Crypto\Adapters\Trc20Adapter;
use Illuminate\Support\Facades\Log;

class ConsolidationService
{
    /**
     * 歸集單一子地址的 USDT 到主地址
     *
     * @return array{status: string, tx_hash?: string, gas_tx_hash?: string, error?: string}
     */
    public function consolidate(UserChannelAccount $childAccount): array
    {
        $parentAccount = $childAccount->parentAccount;
        if (!$parentAccount) {
            return ['status' => 'error', 'error' => '無母地址'];
        }

        $adapter = $this->resolveAdapter(
            data_get($childAccount->detail, UserChannelAccount::DETAIL_KEY_CHAIN_NETWORK, 'trc20')
        );

        // 1. 查詢子地址 USDT 餘額
        $usdtBalance = $adapter->getTokenBalance($childAccount->account);
        if (bccomp($usdtBalance, '0.000001', 6) <= 0) {
            return ['status' => 'skip', 'error' => 'USDT 餘額不足'];
        }

        // 2. 查詢子地址 TRX 餘額，不夠則從主地址送
        $trxBalance = $adapter->getNativeBalance($childAccount->account);
        $minTrx = config('services.trongrid.min_trx_balance', '30');
        $gasTxHash = null;

        if (bccomp($trxBalance, $minTrx, 6) < 0) {
            $parentKey = $this->getPrivateKey($parentAccount);
            if (!$parentKey) {
                return ['status' => 'error', 'error' => '母地址無私鑰'];
            }

            try {
                $sendTrx = bcadd($minTrx, '5', 6); // 多送一點以確保夠用
                $gasTxHash = $adapter->sendNativeToken(
                    $parentAccount->account,
                    $childAccount->account,
                    $sendTrx,
                    $parentKey
                );
                $parentKey = null;
            } catch (\Throwable $e) {
                $parentKey = null;
                return ['status' => 'error', 'error' => '送 TRX 失敗: ' . $e->getMessage()];
            }
        }

        // 3. 從子地址送 USDT 回主地址
        $childKey = $this->getPrivateKey($childAccount);
        if (!$childKey) {
            return ['status' => 'error', 'error' => '子地址無私鑰'];
        }

        try {
            $chainTx = $adapter->sendTransaction(
                $childAccount->account,
                $parentAccount->account,
                $usdtBalance,
                $childKey
            );
            $childKey = null;

            return [
                'status' => 'success',
                'tx_hash' => $chainTx->txHash,
                'gas_tx_hash' => $gasTxHash,
                'amount' => $usdtBalance,
            ];
        } catch (\Throwable $e) {
            $childKey = null;
            return ['status' => 'error', 'error' => '送 USDT 失敗: ' . $e->getMessage()];
        }
    }

    /**
     * 歸集主地址下所有子地址
     */
    public function consolidateAll(UserChannelAccount $masterAccount): array
    {
        $children = $masterAccount->childAccounts()
            ->whereNull('deleted_at')
            ->get();

        $results = [];
        foreach ($children as $child) {
            $results[$child->id] = $this->consolidate($child);
        }

        return $results;
    }

    private function getPrivateKey(UserChannelAccount $account): ?string
    {
        $encrypted = data_get($account->detail, UserChannelAccount::DETAIL_KEY_ENCRYPTED_PRIVATE_KEY);
        if (!$encrypted) {
            return null;
        }

        return decrypt($encrypted);
    }

    private function resolveAdapter(string $network): ChainAdapterInterface
    {
        return match ($network) {
            'trc20' => app(Trc20Adapter::class),
            default => throw new \InvalidArgumentException("不支援: {$network}"),
        };
    }
}
```

**Step 4: ConsolidateChildAccount Job**

```php
<?php

namespace App\Jobs;

use App\Models\UserChannelAccount;
use App\Services\Crypto\ConsolidationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ConsolidateChildAccount implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 60;

    public function __construct(
        private readonly int $childAccountId,
    ) {}

    public function handle(ConsolidationService $service): void
    {
        $child = UserChannelAccount::find($this->childAccountId);
        if (!$child || !$child->parent_account_id) {
            return;
        }

        $result = $service->consolidate($child);

        Log::info('ConsolidateChildAccount: 歸集結果', [
            'child_account_id' => $this->childAccountId,
            'result' => $result,
        ]);
    }
}
```

**Step 5: Controller 端點**

在 `UserChannelAccountController` 加入：

```php
public function consolidate(Request $request)
{
    $this->validate($request, [
        'id' => 'required|integer',
    ]);

    $account = UserChannelAccount::findOrFail($request->input('id'));

    abort_if(
        $account->channel_code !== Channel::CODE_USDT,
        Response::HTTP_BAD_REQUEST,
        '僅支援 USDT 帳號'
    );

    $service = app(ConsolidationService::class);

    if ($account->address_type === UserChannelAccount::ADDRESS_TYPE_MASTER) {
        // 歸集所有子地址
        $results = $service->consolidateAll($account);
        return response()->json(['results' => $results]);
    }

    if ($account->parent_account_id) {
        // 歸集單一子地址
        $result = $service->consolidate($account);
        return response()->json($result);
    }

    return response()->json(['error' => '此帳號無母地址，無法歸集'], Response::HTTP_BAD_REQUEST);
}
```

**Step 6: 註冊路由**

```php
Route::post('user-channel-accounts/consolidate', [UserChannelAccountController::class, 'consolidate']);
```

**Step 7: Commit**

```bash
git add api/app/Services/Crypto/ConsolidationService.php \
    api/app/Jobs/ConsolidateChildAccount.php \
    api/app/Services/Crypto/Adapters/ChainAdapterInterface.php \
    api/app/Services/Crypto/Adapters/Trc20Adapter.php \
    api/app/Http/Controllers/Admin/UserChannelAccountController.php \
    api/routes/api-v1.php
git commit -m "feat: add consolidation service to sweep child address USDT back to master"
```

---

## Task 8: Frontend — 共用 interface 更新

**Files:**
- Modify: `packages/shared/src/interfaces/userChannel.ts`

**Step 1: 加入新欄位**

```typescript
// 在 UserChannel interface 中加入
address_type?: 'master' | 'child';
parent_account_id?: number | null;
parent_account?: {
  id: number;
  account: string;
  name: string;
} | null;
derivation_index?: number | null;
child_count?: number;
```

**Step 2: Commit**

```bash
git add packages/shared/src/interfaces/userChannel.ts
git commit -m "feat: add address type fields to UserChannel interface"
```

---

## Task 9: Frontend — 建立帳號表單加入地址類型

**Files:**
- Modify: `apps/admin/src/pages/userChannel/create/create.tsx`
- Modify: `apps/admin/src/pages/userChannel/create/hooks/useUserChannelForm.ts`
- Modify: `apps/admin/public/locales/zh-CN/userChannel.json`
- Modify: `apps/admin/public/locales/en/userChannel.json`

**Step 1: 翻譯 key**

zh-CN/userChannel.json 加入：
```json
{
  "fields": {
    "addressType": "地址类型",
    "parentAccount": "母地址",
    "masterAddress": "主地址",
    "childAddress": "子地址"
  },
  "placeholders": {
    "selectParentAccount": "选择母地址（可选）"
  }
}
```

en/userChannel.json 加入：
```json
{
  "fields": {
    "addressType": "Address Type",
    "parentAccount": "Parent Address",
    "masterAddress": "Master",
    "childAddress": "Child"
  },
  "placeholders": {
    "selectParentAccount": "Select parent address (optional)"
  }
}
```

**Step 2: 表單加入地址類型選擇**

在 `create.tsx` 的 USDT 區塊中（`curChannelCode === 'USDT'` 條件下），在 chain_network 之後加入：

```tsx
{curChannelCode === 'USDT' && (
  <FormColumn>
    <Form.Item
      label={t('fields.addressType')}
      name="address_type"
      initialValue="master"
    >
      <Select
        options={[
          { label: t('fields.masterAddress'), value: 'master' },
          { label: t('fields.childAddress'), value: 'child' },
        ]}
      />
    </Form.Item>
  </FormColumn>
)}
```

當選擇 child 時，顯示「母地址」下拉（非必填，因為子地址不一定有母地址）：

```tsx
{curChannelCode === 'USDT' && curAddressType === 'child' && (
  <FormColumn>
    <Form.Item
      label={t('fields.parentAccount')}
      name="parent_account_id"
    >
      <Select
        allowClear
        showSearch
        placeholder={t('placeholders.selectParentAccount')}
        // 需要請求 master 地址列表
        // 使用 useSelect hook 搭配 filters
      />
    </Form.Item>
  </FormColumn>
)}
```

**Note:** 需要加 `const curAddressType = Form.useWatch('address_type', form);`

**Step 3: useUserChannelForm hook 加入新欄位**

在 `handleSubmit` 中，將 `address_type` 和 `parent_account_id` 加入 FormData。

**Step 4: Commit**

```bash
git add apps/admin/src/pages/userChannel/create/ \
    apps/admin/public/locales/zh-CN/userChannel.json \
    apps/admin/public/locales/en/userChannel.json
git commit -m "feat: add address type selection to USDT account creation form"
```

---

## Task 10: Frontend — 批量建立子地址 UI

**Files:**
- Create: `apps/admin/src/pages/userChannel/components/BatchCreateChildModal.tsx`
- Modify: `apps/admin/src/pages/userChannel/show.tsx`

**Step 1: 建立 Modal 元件**

```tsx
// BatchCreateChildModal.tsx
import { Modal, Form, InputNumber, message } from 'antd';
import { useCustomMutation } from '@refinedev/core';
import { useTranslation } from 'react-i18next';
import { apiUrl } from 'index';

interface Props {
  parentAccountId: number;
  open: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

export const BatchCreateChildModal: React.FC<Props> = ({
  parentAccountId, open, onClose, onSuccess
}) => {
  const { t } = useTranslation('userChannel');
  const [form] = Form.useForm();
  const { mutate, isLoading } = useCustomMutation();

  const handleOk = () => {
    form.validateFields().then(values => {
      mutate({
        url: `${apiUrl}/user-channel-accounts/create-child`,
        method: 'post',
        values: {
          parent_account_id: parentAccountId,
          count: values.count,
        },
      }, {
        onSuccess: () => {
          message.success(t('messages.batchCreateSuccess'));
          onSuccess();
          onClose();
        },
      });
    });
  };

  return (
    <Modal
      title={t('actions.batchCreateChild')}
      open={open}
      onOk={handleOk}
      onCancel={onClose}
      confirmLoading={isLoading}
    >
      <Form form={form} layout="vertical">
        <Form.Item
          label={t('fields.childCount')}
          name="count"
          rules={[{ required: true }]}
          initialValue={5}
        >
          <InputNumber min={1} max={50} />
        </Form.Item>
      </Form>
    </Modal>
  );
};
```

**Step 2: 在 show.tsx 中加入按鈕**

在主地址的詳情頁加入「批量建立子地址」按鈕和「歸集」按鈕：

```tsx
{data.channel_code === 'USDT' && data.address_type === 'master' && (
  <>
    <Button onClick={() => setBatchModalOpen(true)}>
      {t('actions.batchCreateChild')}
    </Button>
    <Button onClick={handleConsolidate}>
      {t('actions.consolidateAll')}
    </Button>
  </>
)}
```

**Step 3: Commit**

```bash
git add apps/admin/src/pages/userChannel/components/BatchCreateChildModal.tsx \
    apps/admin/src/pages/userChannel/show.tsx
git commit -m "feat: add batch child address creation modal and consolidation button"
```

---

## Task 11: Frontend — 列表頁顯示地址類型

**Files:**
- Modify: `apps/admin/src/pages/userChannel/columns/` (relevant column file)
- Modify: `apps/admin/public/locales/zh-CN/userChannel.json`
- Modify: `apps/admin/public/locales/en/userChannel.json`

**Step 1: 加入地址類型 column**

在列表的 columns 中加入：

```tsx
{
  title: t('fields.addressType'),
  dataIndex: 'address_type',
  width: 80,
  render: (value: string) => (
    <Tag color={value === 'master' ? 'blue' : 'green'}>
      {value === 'master' ? t('fields.masterAddress') : t('fields.childAddress')}
    </Tag>
  ),
  filters: [
    { text: t('fields.masterAddress'), value: 'master' },
    { text: t('fields.childAddress'), value: 'child' },
  ],
}
```

**Step 2: show.tsx 顯示地址類型和母地址**

在詳情頁加入：
- 地址類型標籤
- 如果是子地址且有母地址，顯示母地址連結
- 如果是主地址，顯示子地址數量

**Step 3: Commit**

```bash
git add apps/admin/src/pages/userChannel/columns/ \
    apps/admin/src/pages/userChannel/show.tsx \
    apps/admin/public/locales/
git commit -m "feat: display address type tag and parent/child info in account list and detail"
```

---

## Task 12: 出金流程支援子地址

目前 `UsdtWithdrawHandler` 已經支援任何有私鑰的 `UserChannelAccount` 出金，不需要額外修改。

驗證清單：
- [x] `UsdtWithdrawHandler::handle()` 從 `to_channel_account_id` 取帳號 → 不分主/子
- [x] 從 `detail` 取 `encrypted_private_key` → 子地址已有
- [x] `sendTransaction()` 用地址 + 私鑰 → 不分主/子

**唯一需要注意：** 子地址出金前需確保有足夠 TRX 做 gas。可以在 `UsdtWithdrawHandler` 的 TRX 餘額不足檢查失敗時，自動從母地址送 TRX（可選，作為後續優化）。

**No code changes needed for this task.**

---

## 執行順序摘要

```
Task 1: 安裝 keccak 套件
Task 2: TronAddressService (核心衍生邏輯 + 測試)
Task 3: Migration (DB 欄位)
Task 4: Model 更新
Task 5: API Resource
Task 6: API 子地址端點
Task 7: 歸集服務
Task 8: Frontend interface
Task 9: 建立表單
Task 10: 批量建立 UI
Task 11: 列表/詳情顯示
Task 12: 驗證出金 (無需改動)
```

---

## 注意事項

1. **安全性：** 所有私鑰操作後立即設為 null，避免殘留記憶體
2. **歸集 gas 費：** 子地址需要 TRX 才能發起轉帳，歸集服務會先從主地址送 TRX
3. **TronGrid rate limit：** 批量建立不會呼叫 API（純數學），但歸集會。歸集時注意 API 限流
4. **derivation_index 唯一性：** `(parent_account_id, derivation_index)` 有 unique constraint
5. **keccak 套件：** `kornrunner/keccak` 是純 PHP 實作，不需要額外 PHP extension
