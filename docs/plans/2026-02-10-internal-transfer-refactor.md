# InternalTransfer Controller Refactor Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** 移除 MAYA/GCASH 相關程式碼，將 InternalTransferController 重構為 Service 層模式，與 Withdraw/AgencyWithdraw 共用架構，為未來 USDT 付款整合做準備。

**Architecture:** 建立 `InternalTransferService`，遵循與 `WithdrawService` / `AgencyWithdrawService` 相同的 Service 模式。目前不繼承 `BaseWithdrawService`（InternalTransfer 的資料流差異較大：from_id=0、無 wallet 扣款、使用 UserChannelAccountUtil），但結構平行對齊，未來加入 USDT 時可輕鬆合併。`update()` 的共用邏輯（markAsSuccess/markAsFailed/locking）已由 `TransactionUtil` 處理，保持原樣。

**Tech Stack:** Laravel 11, PHP 8.2+

**與 Withdraw/AgencyWithdraw 共用分析：**

| 元件 | Withdraw | AgencyWithdraw | InternalTransfer | 共用方式 |
|------|----------|----------------|------------------|----------|
| `TransactionUtil` (markAsSuccess/Failed/Locking) | ✅ | ✅ | ✅ | 已共用，無需變更 |
| `TransactionFactory` | ✅ | ✅ | ❌→✅ | 新增 `internalTransferFrom()` 方法 |
| `WithdrawContext` DTO | ✅ | ✅ | ❌ | 新增 `SOURCE_ADMIN` 常數供未來使用 |
| `BCMathUtil` | ✅ | ✅ | ✅ | 已共用 |
| `UserChannelAccountUtil` | ❌ | ❌ | ✅ | InternalTransfer 專用 |
| `ThirdChannelDispatcher` | ✅ | ✅ | ❌ | 未來 USDT 可共用 |
| `BankCardTransferObject` | ✅ | ✅ | ❌→✅ | 改用共用的 DTO |

---

## Task 1: 為 WithdrawContext 新增 SOURCE_ADMIN 常數

**Files:**
- Modify: `api/app/Services/Withdraw/DTO/WithdrawContext.php:13-14`

**目的：** 為未來 InternalTransfer 整合進 BaseWithdrawService 預留來源常數。

**Step 1: 新增常數**

在 `WithdrawContext.php` 的 `SOURCE_MERCHANT` 下方新增：

```php
public const SOURCE_THIRD_PARTY = 'third_party';
public const SOURCE_MERCHANT = 'merchant';
public const SOURCE_ADMIN = 'admin';
```

新增對應的 helper method：

```php
public function isFromAdmin(): bool
{
    return $this->source === self::SOURCE_ADMIN;
}
```

**Step 2: Commit**

```bash
git add api/app/Services/Withdraw/DTO/WithdrawContext.php
git commit -m "feat: add SOURCE_ADMIN constant to WithdrawContext for future internal transfer integration"
```

---

## Task 2: 在 TransactionFactory 新增 internalTransferFrom 方法

**Files:**
- Modify: `api/app/Utils/TransactionFactory.php`

**目的：** 讓 InternalTransfer 使用與 Withdraw 一致的 TransactionFactory 模式建立交易，取代手動 `Transaction::create()`。

**Step 1: 新增 internalTransferFrom 方法**

在 `TransactionFactory.php` 中，在 `thirdchannelWithdrawFrom()` 方法之後新增：

```php
/**
 * Create an internal transfer transaction.
 *
 * @param UserChannelAccount|null $account Target channel account
 */
public function internalTransferFrom(?UserChannelAccount $account = null): ?Transaction
{
    $this->throwIfAnyMissing(["amount", "bankCard"]);

    try {
        DB::beginTransaction();

        $data = [
            "from_id" => 0,
            "from_wallet_id" => 0,
            "to_id" => 0,
            "to_channel_account_id" => null,
            "type" => Transaction::TYPE_INTERNAL_TRANSFER,
            "status" => Transaction::STATUS_MATCHING,
            "notify_status" => Transaction::NOTIFY_STATUS_NONE,
            "to_account_mode" => null,
            "from_channel_account" => [
                UserChannelAccount::DETAIL_KEY_BANK_NAME => $this->bankCard->bankName,
                UserChannelAccount::DETAIL_KEY_BANK_CARD_NUMBER => $this->bankCard->bankCardNumber,
                UserChannelAccount::DETAIL_KEY_BANK_CARD_HOLDER_NAME => $this->bankCard->bankCardHolderName,
            ],
            "to_channel_account" => [],
            "amount" => $this->amount,
            "floating_amount" => $this->amount,
            "actual_amount" => 0,
            "usdt_rate" => $this->usdtRate ?? 0,
            "channel_code" => null,
            "order_number" => $this->orderNumber,
            "note" => $this->note,
        ];

        if ($account) {
            $data["to_id"] = $account->user_id;
            $data["to_channel_account_id"] = $account->id;
            $data["to_channel_account"] = array_merge($account->detail, [
                "channel_code" => $account->channel_code,
            ]);
            $data["status"] = Transaction::STATUS_PAYING;
            $data["matched_at"] = now();
        }

        $transaction = Transaction::create($data);

        DB::commit();
    } catch (RuntimeException $e) {
        DB::rollback();
        return null;
    }

    return $transaction;
}
```

需要在檔案頂部確認已 import `UserChannelAccount`（已在現有 code 中使用，確認即可）。

**Step 2: Commit**

```bash
git add api/app/Utils/TransactionFactory.php
git commit -m "feat: add internalTransferFrom method to TransactionFactory"
```

---

## Task 3: 建立 InternalTransferService

**Files:**
- Create: `api/app/Services/InternalTransfer/InternalTransferService.php`

**目的：** 將 InternalTransferController 的業務邏輯抽離到 Service 層，結構平行對齊 WithdrawService。

**Step 1: 建立目錄**

Run: `mkdir -p api/app/Services/InternalTransfer`

**Step 2: 建立 InternalTransferService**

```php
<?php

namespace App\Services\InternalTransfer;

use App\Models\Transaction;
use App\Models\UserChannelAccount;
use App\Utils\BCMathUtil;
use App\Utils\BankCardTransferObject;
use App\Utils\TransactionFactory;
use App\Utils\UserChannelAccountUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InternalTransferService
{
    public function __construct(
        private readonly BCMathUtil $bcMath,
        private readonly TransactionFactory $transactionFactory,
        private readonly UserChannelAccountUtil $userChannelAccountUtil,
    ) {}

    /**
     * Create an internal transfer transaction.
     */
    public function execute(Request $request): Transaction
    {
        $account = UserChannelAccount::findOrFail($request->input('account_id'));

        $this->validateAccountAvailability($account);

        $transaction = $this->createTransaction($request, $account);

        abort_if(!$transaction, Response::HTTP_BAD_REQUEST, __('common.Create transfer failed'));

        $this->updateAccountTotal($account, $transaction);

        return $transaction;
    }

    /**
     * Validate that the account has no pending transfers.
     */
    private function validateAccountAvailability(UserChannelAccount $account): void
    {
        $exists = Transaction::where('to_channel_account_id', $account->id)
            ->where('status', Transaction::STATUS_PAYING)
            ->where('created_at', '>', now()->subDay())
            ->exists();

        abort_if(
            $exists,
            Response::HTTP_FORBIDDEN,
            __('common.Account is processing transfer, please try later', ['account' => $account->account])
        );
    }

    /**
     * Create the transaction using TransactionFactory.
     */
    private function createTransaction(Request $request, UserChannelAccount $account): ?Transaction
    {
        $bankCard = app(BankCardTransferObject::class)->plain(
            $request->input('bank_name'),
            $request->input('bank_card_number', ''),
            $request->input('bank_card_holder_name'),
        );

        $orderNumber = $request->input(
            'order_id',
            chr(mt_rand(65, 90)) . chr(mt_rand(65, 90)) . chr(mt_rand(65, 90)) . date('YmdHis') . rand(100, 999)
        );

        return $this->transactionFactory->fresh()
            ->bankCard($bankCard)
            ->orderNumber($orderNumber)
            ->amount($request->input('amount'))
            ->note($request->input('note'))
            ->internalTransferFrom($account);
    }

    /**
     * Update account totals after transfer creation.
     */
    private function updateAccountTotal(UserChannelAccount $account, Transaction $transaction): void
    {
        $this->userChannelAccountUtil->updateTotal(
            $account->id,
            $transaction->amount,
            true
        );
    }
}
```

**Step 3: Commit**

```bash
git add api/app/Services/InternalTransfer/InternalTransferService.php
git commit -m "feat: create InternalTransferService with extracted business logic"
```

---

## Task 4: 重構 InternalTransferController

**Files:**
- Modify: `api/app/Http/Controllers/Admin/InternalTransferController.php`

**目的：** 移除 MAYA/GCASH 程式碼，將 `store()` 委託給 Service，清理 `update()` 使用 i18n，移除無路由的 `statistics()` 方法。

**Step 1: 重寫整個 InternalTransferController**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\InternalTransferCollection;
use App\Http\Resources\Admin\InternalTransfer;
use App\Models\Permission;
use App\Models\Transaction;
use App\Utils\TransactionUtil;
use App\Builders\Transaction as TransactionBuilder;
use App\Exceptions\TransactionLockerNotYouException;
use App\Services\InternalTransfer\InternalTransferService;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class InternalTransferController extends Controller
{
    public function __construct()
    {
        $this->middleware([
            "permission:" . Permission::ADMIN_UPDATE_TRANSACTION,
        ])->only("update");
    }

    public function index(Request $request)
    {
        $this->validate($request, [
            "started_at" => ["nullable", "date_format:" . DateTimeInterface::ATOM],
            "ended_at" => ["nullable", "date_format:" . DateTimeInterface::ATOM],
            "channel_code" => ["nullable", "array"],
            "status" => ["nullable", "array"],
        ]);

        $startedAt = $request->started_at
            ? optional(Carbon::make($request->started_at))->tz(config("app.timezone"))
            : now()->startOfDay();
        $endedAt = $request->ended_at
            ? Carbon::make($request->ended_at)->tz(config("app.timezone"))
            : now()->endOfDay();

        abort_if(
            now()->diffInMonths($startedAt) > 2,
            Response::HTTP_BAD_REQUEST,
            __('common.No data found')
        );

        abort_if(
            !$startedAt || $startedAt->diffInDays($endedAt) > 31,
            Response::HTTP_BAD_REQUEST,
            __('common.Date range limited to one month')
        );

        $builder = new TransactionBuilder();
        $transactions = $builder->internalTransfer($request);

        $transactions->with("from", "transactionNotes", "toChannelAccount");

        return InternalTransferCollection::make($transactions->paginate(20));
    }

    public function store(Request $request, InternalTransferService $service)
    {
        $this->validate($request, [
            "account_id" => "required|numeric",
            "amount" => "required|numeric",
            "bank_name" => "required|string",
            "bank_card_holder_name" => "required|string",
        ]);

        $transfer = $service->execute($request);

        return InternalTransfer::make($transfer);
    }

    public function update(
        Request $request,
        Transaction $transfer,
        TransactionUtil $transactionUtil
    ) {
        abort_if(
            $transfer->type != Transaction::TYPE_INTERNAL_TRANSFER,
            Response::HTTP_BAD_REQUEST,
            __('common.Invalid order number')
        );

        $this->validate($request, [
            "status" => [
                "int",
                Rule::in([
                    Transaction::STATUS_MANUAL_SUCCESS,
                    Transaction::STATUS_FAILED,
                    Transaction::STATUS_REVIEW_PASSED,
                ]),
            ],
            "note" => ["nullable", "string", "max:50"],
            "locked" => ["boolean"],
            "to_id" => ["nullable", "int"],
        ]);

        $transactionUtil->supportLockingLogics($transfer, $request);

        if ($request->input("status") === Transaction::STATUS_FAILED) {
            $this->validate($request, [
                "note" => ["string", "max:50"],
            ]);

            try {
                $transactionUtil->markAsFailed(
                    $transfer,
                    auth()->user()->realUser(),
                    $request->input("note"),
                    false
                );
            } catch (TransactionLockerNotYouException $e) {
                abort(Response::HTTP_FORBIDDEN, __('common.Operation error, locked by different user'));
            }
        }

        if ($request->input("status") === Transaction::STATUS_MANUAL_SUCCESS) {
            if ($request->has("_search1")) {
                $search1 = $request->input("_search1");
                abort_if(
                    Transaction::where("_search1", $search1)->exists(),
                    Response::HTTP_BAD_REQUEST,
                    __('common.Already duplicated')
                );
                abort_if(
                    $transfer->_search1,
                    Response::HTTP_BAD_REQUEST,
                    __('common.Already manually processed')
                );

                $transfer->update(["_search1" => $request->input("_search1")]);
            }

            try {
                $transactionUtil->markAsSuccess(
                    $transfer,
                    auth()->user()->realUser()
                );
            } catch (TransactionLockerNotYouException $e) {
                abort(Response::HTTP_FORBIDDEN, __('common.Operation error, locked by different user'));
            }
        }

        if ($request->note) {
            $transfer->update(["note" => $request->note]);
        }

        return InternalTransfer::make(
            $transfer
                ->refresh()
                ->load("from", "transactionNotes", "toChannelAccount")
        );
    }
}
```

**與原始碼的差異總結：**

| 變更 | 說明 |
|------|------|
| 移除 `use App\Jobs\GcashDaifu` | GCASH 相關移除 |
| 移除 `use App\Jobs\MayaDaifu` | MAYA 相關移除 |
| 移除 `use App\Models\Bank` | 不再直接查詢 Bank |
| 移除 `use App\Models\User` | 不再直接使用 |
| 移除 `use App\Models\UserChannelAccount` | 移至 Service |
| 移除 `use App\Models\UserChannel` | 未使用 |
| 移除 `use App\Utils\AmountDisplayTransformer` | 隨 statistics 移除 |
| 移除 `use App\Utils\TransactionFactory` | 移至 Service |
| 移除 `use App\Utils\BCMathUtil` | 移至 Service |
| 移除 `use App\Utils\UserChannelAccountUtil` | 移至 Service |
| 移除 `use Illuminate\Support\Facades\DB` | 隨 statistics 移除 |
| 移除 `use App\Jobs\SettleDelayedProviderCancelOrder` | 未使用 |
| 移除 `use App\Jobs\NotifyTransaction` | 未使用 |
| 移除 `use App\Models\Channel` | 未使用 |
| `store()` | 從 ~100 行精簡為 ~10 行，委託 InternalTransferService |
| `update()` | 硬編碼中文改為 `__()` i18n，與 WithdrawController 共用相同翻譯 key |
| `statistics()` | 移除（無路由，是 dead code） |
| `account_id` 驗證 | 從 `nullable` 改為 `required`（移除 GCASH/MAYA 後不再有無帳戶的場景） |

**Step 2: 確認 i18n 翻譯 key 存在**

需要確認以下 key 已存在於 `api/resources/lang/{zh_CN,en}/common.php`：
- `common.No data found` — 若不存在需新增
- `common.Date range limited to one month` — 若不存在需新增
- `common.Invalid order number` — 若不存在需新增
- `common.Create transfer failed` — 若不存在需新增
- `common.Account is processing transfer, please try later` — 若不存在需新增
- `common.Operation error, locked by different user` — 應已存在（WithdrawController 使用中）
- `common.Already duplicated` — 應已存在（WithdrawController 使用中）
- `common.Already manually processed` — 應已存在（WithdrawController 使用中）

檢查現有翻譯檔，將缺少的 key 補上。

**Step 3: Commit**

```bash
git add api/app/Http/Controllers/Admin/InternalTransferController.php
git add api/resources/lang/
git commit -m "refactor: rewrite InternalTransferController - remove GCASH/MAYA, use service layer, add i18n"
```

---

## Task 5: 清理 — 確認 GcashDaifu/MayaDaifu Job 檔案

**Files:**
- Check: `api/app/Jobs/GcashDaifu.php`
- Check: `api/app/Jobs/MayaDaifu.php`

**目的：** InternalTransferController 是這兩個 Job 的唯一使用者（DaiFuService 中已被註解掉）。確認移除 import 後是否可以刪除 Job 檔案本身。

**Step 1: 確認無其他引用**

Run:
```bash
cd api && grep -r "GcashDaifu\|MayaDaifu" --include="*.php" -l
```

預期結果：應該不再有任何檔案引用。如果只剩 DaiFuService 中的註解，則可安全刪除。

**Step 2: 如果確認可刪除**

```bash
rm api/app/Jobs/GcashDaifu.php api/app/Jobs/MayaDaifu.php
git add -A api/app/Jobs/GcashDaifu.php api/app/Jobs/MayaDaifu.php
git commit -m "chore: remove unused GcashDaifu and MayaDaifu job classes"
```

如果這兩個檔案實際上不存在（可能只有 import 但 class 已被刪除），則跳過此步驟。

---

## Task 6: 驗證

**Step 1: 語法檢查**

Run:
```bash
cd api && php artisan route:list --path=internal-transfers
```

預期：應顯示 `index`, `store`, `update` 三個路由，controller 指向 `InternalTransferController`。

**Step 2: TypeCheck（如適用）**

Run:
```bash
cd api && php -l app/Http/Controllers/Admin/InternalTransferController.php
cd api && php -l app/Services/InternalTransfer/InternalTransferService.php
cd api && php -l app/Utils/TransactionFactory.php
```

預期：`No syntax errors detected`

**Step 3: 確認整體測試通過**

Run:
```bash
cd api && php artisan test
```

**Step 4: Commit（如有修正）**

```bash
git add -A
git commit -m "fix: address any issues found during verification"
```

---

## 未來 USDT 整合路線圖（不在本次範圍）

當 USDT 付款功能開發時，建議的整合步驟：

1. **InternalTransferService 改為繼承 BaseWithdrawService**
   - 實作 `getSubType()` → 回傳新的 `SUB_TYPE_INTERNAL_TRANSFER` 常數
   - 實作 `calculateTotalCost()` → USDT 匯率計算
   - 實作 `validateFeatureEnabled()` → 檢查 internal transfer 開關
   - 實作 `getPaufenEnabled()` → 回傳 `false`
   - 覆寫 `execute()` → 處理帳戶扣款而非 wallet 扣款

2. **WithdrawContext 使用 SOURCE_ADMIN**
   - `buildContextFromAdmin()` 方法建構 admin 來源的 context

3. **整合 ThirdChannelDispatcher**
   - USDT 通道走 ThirdChannelDispatcher 統一分派

4. **統一 TransactionFactory**
   - `internalTransferFrom()` 與 `normalWithdrawFrom()` 合併為可設定的 factory 方法
