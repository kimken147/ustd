# CreateTransactionService 重構設計

## 背景

目前有兩個 `CreateTransactionController`，邏輯高度重複（約 80%）：
- `app/Http/Controllers/CreateTransactionController.php` - 返回 View（~1120 行）
- `app/Http/Controllers/ThirdParty/CreateTransactionController.php` - 返回 JSON（~1019 行）

另外 `Admin/TransactionController::demo()` 也有部分重複邏輯。

## 設計目標

1. 提取共用邏輯到 `CreateTransactionService`
2. Controller 只負責 I/O（接收請求、轉換回應格式）
3. 統一錯誤處理機制
4. 提高可測試性

## 架構設計

```
┌─────────────────────────────────────────────────────────────┐
│                        Controllers                          │
├─────────────────────┬─────────────────────┬────────────────┤
│ CreateTransaction   │ ThirdParty/         │ Admin/         │
│ Controller          │ CreateTransaction   │ Transaction    │
│ (返回 View)         │ Controller (JSON)   │ Controller     │
└─────────┬───────────┴──────────┬──────────┴───────┬────────┘
          │                      │                  │
          ▼                      ▼                  ▼
┌─────────────────────────────────────────────────────────────┐
│                  CreateTransactionService                   │
├─────────────────────────────────────────────────────────────┤
│ + create(): CreateTransactionResult                         │
│ + handleCallback(): CallbackResult                          │
│ + validateAndGenerateUrl(): DemoResult                      │
├─────────────────────────────────────────────────────────────┤
│ - validateRequest()                                         │
│ - findSuitableUserChannel()                                 │
│ - findSuitableUserChannelAccounts()                         │
│ - createTransaction()                                       │
│ - matchLocalProvider()                                      │
│ - matchThirdChannel()                                       │
│ - floatingAmount()                                          │
└─────────────────────────────────────────────────────────────┘
```

## DTO 設計

### Context（請求封裝）

```php
namespace App\Services\Transaction\DTO;

class CreateTransactionContext
{
    public function __construct(
        public readonly string $channelCode,
        public readonly string $username,
        public readonly string $amount,
        public readonly string $orderNumber,
        public readonly string $notifyUrl,
        public readonly string $sign,
        public readonly ?string $clientIp = null,
        public readonly ?string $realName = null,
        public readonly ?string $returnUrl = null,
        public readonly ?string $bankName = null,
        public readonly ?string $usdtRate = null,
        public readonly ?bool $matchLastAccount = null,
        public readonly bool $isThirdParty = false,
    ) {}

    public static function fromViewRequest(Request $request): self { ... }
    public static function fromThirdPartyRequest(Request $request): self { ... }
}

class DemoContext
{
    public function __construct(
        public readonly string $channelCode,
        public readonly string $username,
        public readonly string $secretKey,
        public readonly string $amount,
        public readonly string $orderNumber,
        public readonly string $notifyUrl,
        // ... 其他可選參數
    ) {}

    public static function fromRequest(Request $request): self { ... }
}
```

### Result（結果封裝）

```php
class CreateTransactionResult
{
    public function __construct(
        public readonly Transaction $transaction,
        public readonly string $status,              // matching, matched, third_paying, success, etc.
        public readonly ?string $cashierUrl = null,
        public readonly ?string $qrCodePath = null,
        public readonly ?MatchedInfo $matchedInfo = null,
    ) {}

    public static function matching(Transaction $transaction): self { ... }
    public static function matched(Transaction $transaction, string $qrCodePath, MatchedInfo $matchedInfo): self { ... }
    public static function thirdPaying(Transaction $transaction, string $cashierUrl, ?MatchedInfo $matchedInfo = null): self { ... }
    public static function matchingTimedOut(Transaction $transaction): self { ... }
    public static function payingTimedOut(Transaction $transaction): self { ... }
    public static function success(Transaction $transaction): self { ... }
}

class MatchedInfo
{
    public function __construct(
        public readonly ?string $receiverAccount = null,
        public readonly ?string $receiverName = null,
        public readonly ?string $receiverBankName = null,
        public readonly ?string $receiverBankBranch = null,
        public readonly ?string $bankCardNumber = null,
        public readonly ?string $bankCardHolderName = null,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $note = null,
    ) {}

    public static function fromUserChannelAccount(array $fromChannelAccount): self { ... }
    public static function fromThirdChannelResponse(array $data): self { ... }
}

class CallbackResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $responseBody = null,
        public readonly ?string $error = null,
        public readonly int $statusCode = 200,
    ) {}

    public static function success(?string $responseBody = 'SUCCESS'): self { ... }
    public static function fail(string $error, int $statusCode = 400): self { ... }
}

class DemoResult
{
    public function __construct(
        public readonly string $url,
    ) {}
}
```

## Exception 設計

複用現有的 `ThirdPartyErrorResponse`，新增 Transaction 專用的靜態方法：

```php
// 擴充 ThirdPartyErrorResponse
class ThirdPartyErrorResponse
{
    // ... 現有方法 ...

    // 新增 Transaction 專用
    public static function channelNotFound(): self { ... }
    public static function channelMaintenance(): self { ... }
    public static function transactionDisabled(): self { ... }
    public static function balanceLimitExceeded(): self { ... }
    public static function ipBanned(): self { ... }
    public static function realnameBanned(): self { ... }
    public static function rateLimitExceeded(): self { ... }
    public static function matchingTimeout(): self { ... }
    public static function noAvailableProvider(): self { ... }
}

// 新建 Transaction 專用 Exception
namespace App\Services\Transaction\Exceptions;

class TransactionValidationException extends Exception
{
    private ThirdPartyErrorResponse $errorResponse;

    public function __construct(ThirdPartyErrorResponse $errorResponse) { ... }
    public function toThirdPartyResponse(): JsonResponse { ... }
    public function getErrorResponse(): ThirdPartyErrorResponse { ... }
}
```

## Service 設計

```php
namespace App\Services\Transaction;

class CreateTransactionService
{
    public function __construct(
        private BCMathUtil $bcMath,
        private FeatureToggleRepository $featureToggleRepository,
        private NotificationUtil $notificationUtil,
        private TransactionNoteUtil $transactionNoteUtil,
        private TransactionFactory $transactionFactory,
        private WalletUtil $walletUtil,
    ) {}

    /**
     * 建立交易（主入口）
     * @throws TransactionValidationException
     */
    public function create(CreateTransactionContext $context): CreateTransactionResult { ... }

    /**
     * 處理四方回調
     */
    public function handleCallback(string $orderNumber, Request $request): CallbackResult { ... }

    /**
     * 驗證並生成提單 URL（供 demo 使用）
     * @throws TransactionValidationException
     */
    public function validateAndGenerateUrl(DemoContext $context): DemoResult { ... }

    // 私有方法（從現有 Controller 和 Trait 遷移）
    private function validateRequest(CreateTransactionContext $context): void { ... }
    private function findOrCreateTransaction(CreateTransactionContext $context): Transaction { ... }
    private function attemptMatching(CreateTransactionContext $context, Transaction $transaction): CreateTransactionResult { ... }
    private function matchLocalProvider(Transaction $transaction, ...): ?UserChannelAccount { ... }
    private function matchThirdChannel(Transaction $transaction, ...): ?CreateTransactionResult { ... }
    private function buildResult(Transaction $transaction): CreateTransactionResult { ... }
    private function floatingAmount(string $amount, $maxFloating): string { ... }
    private function findSuitableUserChannel(...): array { ... }
    private function findSuitableUserChannelAccounts(...): Collection { ... }
}
```

## 重構後的 Controller

### View Controller

```php
namespace App\Http\Controllers;

class CreateTransactionController extends Controller
{
    public function __construct(private CreateTransactionService $service) {}

    public function __invoke(Request $request)
    {
        try {
            $context = CreateTransactionContext::fromViewRequest($request);
            $result = $this->service->create($context);
            return $this->renderView($result);
        } catch (TransactionValidationException $e) {
            return $this->errorView($e->getErrorResponse()->message);
        }
    }

    private function renderView(CreateTransactionResult $result): View { ... }
    private function errorView(string $message): View { ... }
}
```

### ThirdParty Controller

```php
namespace App\Http\Controllers\ThirdParty;

class CreateTransactionController extends Controller
{
    public function __construct(private CreateTransactionService $service) {}

    public function __invoke(Request $request)
    {
        try {
            $context = CreateTransactionContext::fromThirdPartyRequest($request);
            $result = $this->service->create($context);
            return $this->jsonResponse($result);
        } catch (TransactionValidationException $e) {
            return $e->toThirdPartyResponse();
        }
    }

    private function jsonResponse(CreateTransactionResult $result): JsonResponse { ... }

    public function callback(string $orderNumber, Request $request) { ... }
}
```

### Admin Controller (demo)

```php
public function demo(Request $request, CreateTransactionService $service)
{
    $this->validate($request, [...]);

    try {
        $context = DemoContext::fromRequest($request);
        $result = $service->validateAndGenerateUrl($context);
        return response()->json(['url' => $result->url]);
    } catch (TransactionValidationException $e) {
        abort(Response::HTTP_BAD_REQUEST, $e->getMessage());
    }
}
```

## 檔案結構

```
api/app/
├── Services/
│   ├── Transaction/
│   │   ├── CreateTransactionService.php
│   │   ├── DTO/
│   │   │   ├── CreateTransactionContext.php
│   │   │   ├── DemoContext.php
│   │   │   ├── CreateTransactionResult.php
│   │   │   ├── MatchedInfo.php
│   │   │   ├── CallbackResult.php
│   │   │   └── DemoResult.php
│   │   └── Exceptions/
│   │       └── TransactionValidationException.php
│   └── Withdraw/
│       └── DTO/
│           └── ThirdPartyErrorResponse.php   # 擴充新方法
│
├── Http/Controllers/
│   ├── CreateTransactionController.php       # 重構
│   ├── ThirdParty/
│   │   └── CreateTransactionController.php   # 重構
│   └── Admin/
│       └── TransactionController.php         # demo() 重構
```

## 預期效益

| 項目 | 重構前 | 重構後 |
|------|--------|--------|
| Controller 總行數 | ~2100 行 | ~200 行 |
| 重複邏輯 | 80% 重複 | 0% |
| 單元測試 | 難以測試 | Service 可獨立測試 |
| 新增輸出格式 | 複製整個 Controller | 新增 ~50 行 Controller |

## 實作順序

1. 建立 DTO 類別（Context、Result、MatchedInfo）
2. 擴充 ThirdPartyErrorResponse
3. 建立 TransactionValidationException
4. 建立 CreateTransactionService（遷移邏輯）
5. 重構 ThirdParty/CreateTransactionController
6. 重構 CreateTransactionController
7. 重構 Admin/TransactionController::demo()
8. 移除不再使用的 Trait（UserChannelMatching、UserChannelAccountMatching）
9. 測試驗證
