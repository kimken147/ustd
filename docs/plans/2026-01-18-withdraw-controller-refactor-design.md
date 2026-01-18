# Withdraw Controller 重構設計

## 概述

重構 ThirdParty 和 Merchant 中的 `AgencyWithdrawController` 和 `WithdrawController`，抽取共用邏輯到 Service 層，採用 Template Method Pattern。

## 問題分析

### 現有重複程式碼

| 檔案 | 行數 | 用途 |
|------|------|------|
| ThirdParty/AgencyWithdrawController | ~500 | 代付 API（第三方呼叫） |
| ThirdParty/WithdrawController | ~370 | 提現 API（第三方呼叫） |
| Merchant/AgencyWithdrawController | ~370 | 代付後台（商戶操作） |
| Merchant/WithdrawController | ~615 | 提現後台（商戶操作） |

### 重複邏輯區塊

1. **驗證邏輯** (~50-80 行)
   - X-Token header 驗證
   - 金額最小值/最大值驗證
   - 小數點金額禁止驗證 (`NO_FLOAT_IN_WITHDRAWS`)
   - 黑名單持卡人驗證 (`BannedRealname`)
   - 銀行支援驗證 (`daifuBanks`)
   - 餘額不足驗證

2. **三方代付通道選擇邏輯** (~150-200 行)
   - 查詢 `MerchantThirdChannel` 列表
   - 過濾金額門檻內的通道
   - 隨機排序後嘗試每個通道
   - 查詢三方餘額、發送代付、處理結果
   - 建立 `TransactionNote`
   - 失敗時的 fallback 處理

3. **交易建立流程** (~30-50 行)
   - `TransactionFactory` 設定
   - USDT 匯率處理
   - 選擇提現方法

---

## 設計方案

採用 **Template Method Pattern**，與現有 `BaseAuthService` 架構風格一致。

### 檔案結構

```
api/app/Services/Withdraw/
├── BaseWithdrawService.php           # 抽象基類 (~300 行)
├── AgencyWithdrawService.php         # 代付服務 (~50 行)
├── WithdrawService.php               # 提現服務 (~60 行)
├── ThirdChannelDispatcher.php        # 三方通道調度 (~150 行)
├── DTO/
│   ├── WithdrawContext.php           # 請求上下文 (~50 行)
│   ├── WithdrawResult.php            # 結果封裝 (~30 行)
│   └── ThirdPartyErrorResponse.php   # ThirdParty 錯誤回應 (~60 行)
└── Exceptions/
    └── WithdrawValidationException.php  # 驗證例外 (~40 行)
```

---

## 核心類別設計

### BaseWithdrawService

主要流程方法：

```php
abstract class BaseWithdrawService
{
    public function execute(WithdrawContext $context): WithdrawResult
    {
        // 1. 驗證階段
        $this->validateRequest($context);
        $this->validateMerchantPermission($context);
        $this->validateAmount($context);
        $this->validateBankCard($context);
        $this->validateBalance($context);

        // 2. 計算費用
        $totalCost = $this->calculateTotalCost($context);

        // 3. 建立交易（含三方通道處理）
        $transaction = DB::transaction(function () use ($context, $totalCost) {
            $transaction = $this->createTransaction($context);
            $this->processThirdChannel($context, $transaction);
            $this->deductWallet($context, $totalCost, $transaction);
            return $transaction;
        });

        // 4. 後置處理
        $this->afterTransactionCreated($transaction);

        return new WithdrawResult($transaction);
    }
}
```

### Hook 方法

| 方法 | 用途 | AgencyWithdraw | Withdraw |
|------|------|----------------|----------|
| `getSubType()` | 交易子類型 | `SUB_TYPE_AGENCY_WITHDRAW` | `SUB_TYPE_WITHDRAW` |
| `getMinAmountField()` | 最小金額欄位 | `agency_withdraw_min_amount` | `withdraw_min_amount` |
| `getMaxAmountField()` | 最大金額欄位 | `agency_withdraw_max_amount` | `withdraw_max_amount` |
| `getFeatureToggle()` | 功能開關 | `ENABLE_AGENCY_WITHDRAW` | （檢查 `withdraw_enable`） |
| `getPaufenToggleField()` | 跑分開關欄位 | `paufen_agency_withdraw_enable` | `paufen_withdraw_enable` |
| `calculateTotalCost()` | 費用計算 | `calculateTotalAgencyWithdrawAmount()` | `calculateTotalWithdrawAmount()` |

---

## ThirdParty vs Merchant 來源處理

### 差異點

| 差異點 | ThirdParty (API) | Merchant (後台) |
|--------|------------------|-----------------|
| 身份驗證 | 簽名驗證 (`sign`) | JWT + 2FA |
| 商戶取得 | 從 `username` 參數查詢 | `auth()->user()` |
| 銀行卡來源 | 請求參數直接傳入 | 從 `BankCard` model 查詢 |
| IP 白名單 | 需要驗證 | 不需要 |
| 回應格式 | 特定 JSON 結構 + error_code | 標準 Laravel Response |
| 訂單號 | 外部傳入 `order_number` | 系統自動產生 |

### Context Builder 模式

```php
abstract class BaseWithdrawService
{
    /**
     * 從 ThirdParty API 請求建立 Context
     */
    public function buildContextFromThirdParty(Request $request): WithdrawContext
    {
        $merchant = $this->validateSignatureAndGetMerchant($request);
        $this->validateWhitelistedIp($merchant, $request);

        return new WithdrawContext(
            merchant: $merchant,
            wallet: $merchant->wallet,
            amount: $request->input('amount'),
            bankCard: $this->bankCardFromRequest($request),
            orderNumber: $request->input('order_number'),
            notifyUrl: $request->input('notify_url'),
            source: WithdrawContext::SOURCE_THIRD_PARTY,
        );
    }

    /**
     * 從 Merchant 後台請求建立 Context
     */
    public function buildContextFromMerchant(Request $request, User $user): WithdrawContext
    {
        $this->validate2FAIfEnabled($request, $user);

        return new WithdrawContext(
            merchant: $user->realUser(),
            wallet: $user->wallet,
            amount: $request->input('amount'),
            bankCard: $this->resolveBankCard($request, $user),
            orderNumber: $this->generateOrderNumber(),
            notifyUrl: null,
            source: WithdrawContext::SOURCE_MERCHANT,
        );
    }
}
```

---

## 三方通道調度邏輯

### ThirdChannelDispatcher

```php
class ThirdChannelDispatcher
{
    public function dispatch(
        User $merchant,
        string $amount,
        TransactionFactory $factory,
        callable $onSuccess,
        callable $onFallback
    ): Transaction {
        if (!$merchant->third_channel_enable) {
            return $onFallback();
        }

        $channels = $this->getAvailableChannels($merchant, $amount);

        if ($channels->isEmpty()) {
            $transaction = $onFallback();
            $this->addNote($transaction, '无符合当前代付金额的三方可用，请调整限额设定');
            $this->handleFailIfEnabled($transaction);
            return $transaction;
        }

        $channels = $this->filterByThreshold($channels, $amount)->shuffle();

        if ($channels->isEmpty()) {
            $transaction = $onFallback();
            $this->addNote($transaction, '无自动推送门槛内的三方可用，请手动推送');
            $this->handleFailIfEnabled($transaction);
            return $transaction;
        }

        return $this->tryChannels($channels, $amount, $factory, $onSuccess, $onFallback);
    }
}
```

---

## DTO 類別

### WithdrawContext

```php
class WithdrawContext
{
    public const SOURCE_THIRD_PARTY = 'third_party';
    public const SOURCE_MERCHANT = 'merchant';

    public function __construct(
        public readonly User $merchant,
        public readonly Wallet $wallet,
        public readonly string $amount,
        public readonly BankCardTransferObject $bankCard,
        public readonly string $orderNumber,
        public readonly ?string $notifyUrl,
        public readonly string $source,
        public readonly ?string $usdtRate = null,
    ) {}

    public function isFromThirdParty(): bool;
    public function isFromMerchant(): bool;
    public function isUsdt(): bool;
    public function getBank(): ?Bank;
}
```

### WithdrawResult

```php
class WithdrawResult
{
    public function __construct(
        public readonly Transaction $transaction,
        public readonly bool $success = true,
        public readonly ?string $message = null,
    ) {}

    public function getTransaction(): Transaction;
}
```

### ThirdPartyErrorResponse

```php
class ThirdPartyErrorResponse
{
    public function __construct(
        public readonly int $httpStatusCode,
        public readonly int $errorCode,
        public readonly string $message,
    ) {}

    public function toResponse(): JsonResponse;

    // 工廠方法
    public static function badRequest(int $errorCode, string $message): self;
    public static function userNotFound(): self;
    public static function insufficientBalance(): self;
}
```

---

## 重構後的 Controller 範例

### ThirdParty/AgencyWithdrawController

```php
class AgencyWithdrawController extends Controller
{
    public function store(Request $request, AgencyWithdrawService $service)
    {
        try {
            $context = $service->buildContextFromThirdParty($request);
            $result = $service->execute($context);

            return Withdraw::make($result->getTransaction())
                ->additional([
                    'http_status_code' => 201,
                    'message' => __('common.Submit successful'),
                ])
                ->response()
                ->setStatusCode(Response::HTTP_OK);

        } catch (WithdrawValidationException $e) {
            return $e->toThirdPartyResponse();
        }
    }
}
```

### Merchant/AgencyWithdrawController

```php
class AgencyWithdrawController extends Controller
{
    public function store(Request $request, AgencyWithdrawService $service)
    {
        $this->validate($request, [
            'bank_card_number' => 'required|max:50',
            'bank_card_holder_name' => 'max:50',
            'bank_name' => 'required|max:50',
            'amount' => 'required|numeric|min:1',
        ]);

        $context = $service->buildContextFromMerchant($request, auth()->user());
        $service->execute($context);

        return response()->noContent(Response::HTTP_CREATED);
    }
}
```

---

## 程式碼行數對比

| 檔案 | 重構前 | 重構後 |
|------|--------|--------|
| ThirdParty/AgencyWithdrawController | ~500 行 | ~30 行 |
| ThirdParty/WithdrawController | ~370 行 | ~30 行 |
| Merchant/AgencyWithdrawController | ~370 行 | ~25 行 |
| Merchant/WithdrawController (store) | ~280 行 | ~35 行 |
| **新增 Services** | - | ~690 行 |
| **總計** | ~1520 行 | ~810 行 |

**淨減少約 710 行重複程式碼（47%）**

---

## 實作步驟

1. 建立 DTO 類別 (`WithdrawContext`, `WithdrawResult`, `ThirdPartyErrorResponse`)
2. 建立 `WithdrawValidationException`
3. 建立 `ThirdChannelDispatcher`
4. 建立 `BaseWithdrawService`
5. 建立 `AgencyWithdrawService` 和 `WithdrawService`
6. 重構 ThirdParty Controllers
7. 重構 Merchant Controllers
8. 撰寫測試
9. 移除舊的重複程式碼
