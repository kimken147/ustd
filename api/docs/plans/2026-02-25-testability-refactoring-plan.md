# Testability Refactoring Plan

**Goal:** Refactor tightly-coupled services to enable unit testing, reduce god-class complexity, and fix known bugs.

**Current test coverage:** 272 tests / 464 assertions (all passing). The code below cannot be meaningfully unit tested without refactoring.

---

## Overview

| Target | Lines | Severity | Core Problem |
|--------|-------|----------|--------------|
| TransactionStatusService | 1,325 | CRITICAL | God class: 4 responsibilities, 7 deps, 193-line method |
| WalletUtil | 480+ | CRITICAL | DB mixed with logic, undefined variable bug, race conditions |
| TransactionFactory | 700+ | HIGH | Every method embeds DB::transaction(), returns null on failure |
| CreateTransactionService | 699 | HIGH | goto statement, 10 deps, 126-line method |
| Fat Controllers (5+) | 2,800+ | MEDIUM | Business logic in controllers, duplicated CSV/creation logic |

**Recommended order:** Phase 1 → 2 → 3 → 4 → 5 (each phase independently deployable)

---

## Phase 1: WalletUtil (Fix Bugs + Extract Pure Logic)

**Why first:** Has a critical undefined variable bug; every other service depends on it.

**Current problems:**
- `depositRollback()` references undefined `$delta` variable (line ~145) — **production bug**
- `conflictAwaredBalanceUpdate()` / `updateWallet()` / `withdrawNotLock()` lack DB transaction wrappers
- Balance calculation logic mixed with DB writes and WalletHistory creation
- `withdraw()` vs `withdrawNotLock()` — duplicated code, only difference is locking
- Race condition in WalletHistory ID generation (line 62-63)
- No idempotency safeguards

### Task 1-1: Fix `depositRollback()` undefined variable bug

**Files:**
- Modify: `app/Utils/WalletUtil.php` — `depositRollback()` method

**What:** Fix the `$delta` reference before it's defined. The `throw_if` validation at lines 143-152 checks `$delta['balance']` but `$delta` is only constructed at line 155+. Move the validation after `$delta` construction, or inline the check.

### Task 1-2: Extract `WalletBalanceCalculator` (pure logic)

**Files:**
- Create: `app/Services/Wallet/WalletBalanceCalculator.php`
- Modify: `app/Utils/WalletUtil.php`

**What:** Extract all pure calculation logic into a testable class:

```php
class WalletBalanceCalculator
{
    public function __construct(private BCMathUtil $bcMath) {}

    // From deposit(): compute balance/profit delta
    public function computeDepositDelta(string $amount, string $withdrawType, bool $deductFrozen, string $frozenBalance): array

    // From withdraw(): compute balance/profit delta
    public function computeWithdrawDelta(string $amount, string $withdrawType): array

    // From transactionReward(): compute reward amount
    public function computeRewardAmount(string $amount, string $rewardRate, string $rewardUnit): string

    // From conflictAwaredBalanceUpdate(): compute new wallet values
    public function computeUpdatedBalance(array $wallet, array $delta): array

    // Validation: check if wallet has sufficient balance
    public function hasSufficientBalance(string $available, string $required): bool
}
```

WalletUtil keeps the DB operations but delegates calculations to WalletBalanceCalculator.

**Tests:** 20+ pure unit tests for computation edge cases (negative, zero, overflow, precision).

### Task 1-3: Unify `withdraw()` and `withdrawNotLock()`

**Files:**
- Modify: `app/Utils/WalletUtil.php`

**What:** Merge into single method with `bool $useLock = true` parameter, or better: always lock (the "not lock" variant is a race condition risk). Audit all callers of `withdrawNotLock()` — if they're already inside a DB transaction, switching to `withdraw()` is safe.

### Task 1-4: Extract `WalletHistoryRecorder`

**Files:**
- Create: `app/Services/Wallet/WalletHistoryRecorder.php`
- Modify: `app/Utils/WalletUtil.php`

**What:** All 11 `WalletHistory::create()` calls share a pattern (type, wallet_id, delta, note, adjustment_number). Extract to:

```php
class WalletHistoryRecorder
{
    public function record(Wallet $wallet, int $type, array $delta, string $note): WalletHistory
}
```

This separates the audit trail concern from balance manipulation.

---

## Phase 2: TransactionStatusService (Split God Class)

**Why second:** 1,325 lines, 4 distinct responsibilities. Largest single-file risk.

**Current problems:**
- 10 public methods spanning status transitions, wallet settlement, profit distribution, and transaction separation
- `markAsFailed()` is 193 lines with nested switch statements
- `settleToWallet()` is 118 lines with 4-way switch
- 7 constructor dependencies
- 12+ direct model usages
- Undefined `$provider` variable bug in `markAsThirdChannelWithdraw()` (line 225)
- Feature flags scattered across methods
- Repeated fee filtering logic (DRY violation)

### Task 2-1: Extract `TransactionSettlementService`

**Files:**
- Create: `app/Services/Transaction/TransactionSettlementService.php`
- Modify: `app/Services/Transaction/TransactionStatusService.php`

**What:** Move wallet + profit settlement logic:

```php
class TransactionSettlementService
{
    public function __construct(
        private WalletUtil $wallet,
        private BCMathUtil $bcMath,
        private FeatureToggleRepository $featureToggleRepository,
        private UserChannelAccountUtil $userChannelAccountUtil,
    ) {}

    // Extracted from settleToWallet() — 118 lines
    public function settleToWallet(Transaction $transaction): void

    // Extracted from settleTransactionProfit() — ~50 lines
    public function settleProfit(Transaction $transaction): void

    // Extracted from markAsFailed() rollback sections
    public function rollbackSettlement(Transaction $transaction): void

    // DRY: extracted repeated fee filtering
    private function getNonMerchantProviderFees(Transaction $transaction): Collection
}
```

**Impact:** Removes ~280 lines from TransactionStatusService; eliminates `$wallet`, `$bcMath`, `$userChannelAccountUtil` dependencies.

### Task 2-2: Extract `TransactionSeparationService`

**Files:**
- Create: `app/Services/Transaction/TransactionSeparationService.php`
- Modify: `app/Services/Transaction/TransactionStatusService.php`

**What:** Move `separateWithdraw()` (140 lines) and `createChildPaufenTransaction()`:

```php
class TransactionSeparationService
{
    // Extracted from separateWithdraw() — 140 lines
    public function separate(Transaction $parent, array $childAmounts, string $childType): Collection

    // Extracted from createChildPaufenTransaction()
    private function createChild(Transaction $parent, string $amount, string $type): Transaction
}
```

**Impact:** Removes ~180 lines; eliminates `$transactionFactory`, `$bankCardTransferObject` dependencies.

### Task 2-3: Extract `TransactionStateValidator`

**Files:**
- Create: `app/Services/Transaction/TransactionStateValidator.php`
- Modify: `app/Services/Transaction/TransactionStatusService.php`

**What:** Consolidate all lock validation and state checks:

```php
class TransactionStateValidator
{
    // From shouldLockBeforeUpdate() — switch on type
    public function validateLock(Transaction $transaction): void

    // From paufenTransactionLocked()
    public function validatePaufenLock(Transaction $transaction): void

    // From separatedWithdrawCannotBeUpdateDirectly()
    public function validateNotSeparatedParent(Transaction $transaction): void

    // From childWithdrawCanBeUpdatedToSuccess/Fail()
    public function validateSiblingStatus(Transaction $transaction, string $targetStatus): void
}
```

**Impact:** Pure validation logic, easily testable without DB.

### Task 2-4: Fix `markAsThirdChannelWithdraw()` undefined `$provider` bug

**Files:**
- Modify: `app/Services/Transaction/TransactionStatusService.php` — line 225

**What:** Reference to undefined `$provider` variable. Investigate callers to determine correct fix.

### Task 2-5: Simplify remaining `TransactionStatusService`

After Tasks 2-1 through 2-4, the service should be ~600 lines with only 3 dependencies:
- `TransactionSettlementService`
- `TransactionStateValidator`
- `TransactionFeeService`

Methods become orchestrators that delegate to extracted services. Each public method should be under 50 lines.

---

## Phase 3: TransactionFactory (Separate Creation from Persistence)

**Current problems:**
- 15 public methods, each embedding its own `DB::transaction()`
- 3 methods lack transaction wrappers (inconsistent)
- Returns `null` on failure (silent failures)
- Mixes factory logic with state mutations (`changeToPaufenWithdraw`, `assignThirdChannel`)
- Data fetching inside transactions (UserChannelAccount queries)
- Race condition handling scattered with 4 different approaches

### Task 3-1: Extract state mutation methods to `TransactionMutator`

**Files:**
- Create: `app/Services/Transaction/TransactionMutator.php`
- Modify: `app/Utils/TransactionFactory.php`

**What:** Move non-creation methods that mutate existing transactions:

```php
class TransactionMutator
{
    public function changeToPaufenWithdraw(Transaction $tx): void
    public function changeToNormalWithdraw(Transaction $tx): void
    public function changeToThirdChannelPending(Transaction $tx): void
    public function assignThirdChannel(Transaction $tx, ThirdChannel $channel): void
}
```

These are simple state transitions, not "factory" operations. They should throw exceptions, not return null.

### Task 3-2: Extract transaction data builders

**Files:**
- Create: `app/Services/Transaction/TransactionDataBuilder.php`
- Modify: `app/Utils/TransactionFactory.php`

**What:** Separate "compute transaction attributes" (pure logic) from "insert into DB":

```php
class TransactionDataBuilder
{
    // Pure: computes the array of attributes for a new transaction
    public function buildNormalDepositData(User $merchant, Channel $channel, ...): array
    public function buildNormalWithdrawData(User $merchant, Wallet $wallet, ...): array
    public function buildPaufenWithdrawData(...): array
    public function buildInternalTransferData(...): array
}
```

TransactionFactory then becomes thin: `DB::transaction(fn() => Transaction::create($builder->buildXxx(...)))`.

**Tests:** Pure unit tests for data building logic (status determination, field computation).

### Task 3-3: Standardize error handling

**Files:**
- Modify: `app/Utils/TransactionFactory.php`

**What:** Replace all `return null` patterns with proper exceptions. Create `TransactionCreationException` if needed. Callers must be updated to catch exceptions instead of checking for null.

---

## Phase 4: CreateTransactionService (Decompose Matching Logic)

**Current problems:**
- goto statement for fallback matching
- `matchWithThirdChannel()` is 126 lines
- `attemptMatching()` has 3 responsibilities (local match, third-party match, fallback)
- Dynamic class instantiation without type safety
- Silent exception swallowing in matching loop
- 10 constructor dependencies

### Task 4-1: Extract `LocalProviderMatchingService`

**Files:**
- Create: `app/Services/Transaction/Matching/LocalProviderMatchingService.php`
- Modify: `app/Services/Transaction/CreateTransactionService.php`

**What:** Extract `matchWithLocalProvider()` + local account query logic:

```php
class LocalProviderMatchingService
{
    public function __construct(
        private AccountMatchingQueryBuilder $queryBuilder,
        private WalletUtil $walletUtil,
        private TransactionFactory $transactionFactory,
        private FeatureToggleRepository $featureToggleRepository,
    ) {}

    // Returns matched account or null
    public function attemptMatch(Transaction $transaction, Channel $channel, User $merchant): ?MatchResult
}
```

### Task 4-2: Extract `ThirdPartyChannelMatchingService`

**Files:**
- Create: `app/Services/Transaction/Matching/ThirdPartyChannelMatchingService.php`
- Modify: `app/Services/Transaction/CreateTransactionService.php`

**What:** Extract `matchWithThirdChannel()` (126 lines):

```php
class ThirdPartyChannelMatchingService
{
    // Iterates merchant's enabled third-party channels, calls API, returns result
    public function attemptMatch(Transaction $transaction, User $merchant): ?ThirdPartyMatchResult
}
```

### Task 4-3: Refactor `attemptMatching()` to eliminate goto

**Files:**
- Modify: `app/Services/Transaction/CreateTransactionService.php`

**What:** Replace goto-based fallback with explicit strategy pattern:

```php
public function attemptMatching(Transaction $transaction, ...): CreateTransactionResult
{
    // Strategy 1: Try local providers (if enabled or random)
    if ($shouldTryLocal) {
        $result = $this->localMatcher->attemptMatch($transaction, ...);
        if ($result) return $this->buildResult($result);
    }

    // Strategy 2: Try third-party channels
    $result = $this->thirdPartyMatcher->attemptMatch($transaction, ...);
    if ($result) return $result;

    // Strategy 3: Fallback to local (if third-party failed and merchant allows)
    if (!$triedLocal && $merchant->include_self_providers) {
        $result = $this->localMatcher->attemptMatch($transaction, ...);
        if ($result) return $this->buildResult($result);
    }

    return $this->handleMatchingTimeout($transaction);
}
```

**Impact:** Eliminates goto; reduces `attemptMatching()` to ~30 lines; reduces constructor deps from 10 to ~5.

### Task 4-4: Extract `ThirdPartyCallbackHandler`

**Files:**
- Create: `app/Services/Transaction/ThirdPartyCallbackHandler.php`
- Modify: `app/Services/Transaction/CreateTransactionService.php`

**What:** Move `handleCallback()` to its own class. It's a completely separate concern from transaction creation.

---

## Phase 5: Fat Controllers (Extract to Services)

**Priority order based on impact:**

### Task 5-1: `ProviderCreationService` + `MerchantCreationService`

**Files:**
- Create: `app/Services/User/ProviderCreationService.php`
- Create: `app/Services/User/MerchantCreationService.php`
- Modify: `app/Http/Controllers/Admin/ProviderController.php` (~120 lines extracted)
- Modify: `app/Http/Controllers/Admin/MerchantController.php` (~76 lines extracted)

**What:** Extract `store()` logic: password/secret generation, wallet creation, channel group setup, device creation. Both share patterns — consider a `BaseUserCreationService`.

### Task 5-2: `TransactionCsvExportService`

**Files:**
- Create: `app/Services/Export/TransactionCsvExportService.php`
- Modify: `app/Http/Controllers/Admin/WithdrawController.php` (~126 lines extracted)
- Modify: `app/Http/Controllers/Merchant/WithdrawController.php` (~121 lines extracted)

**What:** Consolidate duplicated CSV export logic with configurable field mappings and status translations.

### Task 5-3: `ThirdPartyChannelCheckoutService`

**Files:**
- Create: `app/Services/Transaction/ThirdPartyChannelCheckoutService.php`
- Modify: `app/Http/Controllers/Admin/WithdrawController.php` (~88 lines extracted)

**What:** Extract `markAsThirdChannelWithdraw()` API orchestration: URL building, balance checking, API calling, error handling.

### Task 5-4: `ProviderAccountModeTransitionService`

**Files:**
- Create: `app/Services/User/ProviderAccountModeTransitionService.php`
- Modify: `app/Http/Controllers/Admin/ProviderController.php` (~195 lines extracted)

**What:** Extract account mode transition logic from `update()`: credit/deposit/general mode switching with descendant user synchronization and wallet recalculation.

### Task 5-5: `UserChannelAccountBatchService`

**Files:**
- Create: `app/Services/UserChannelAccount/UserChannelAccountBatchService.php`
- Modify: `app/Http/Controllers/Admin/UserChannelAccountController.php` (~91 lines extracted)

**What:** Extract `massiveStore()` batch processing + USDT-specific validation/sync logic.

---

## Expected Outcome

After all phases:

| Metric | Before | After |
|--------|--------|-------|
| TransactionStatusService | 1,325 lines | ~500 lines |
| WalletUtil | 480 lines | ~250 lines (+ 2 new services ~230 lines) |
| TransactionFactory | 700 lines | ~350 lines (+ Mutator ~100, DataBuilder ~200) |
| CreateTransactionService | 699 lines | ~300 lines (+ 3 new services ~400 lines) |
| Testable service methods | ~40% | ~85% |
| Known bugs fixed | 0 | 3 (WalletUtil $delta, TSS $provider, withdrawNotLock race) |

**New test coverage potential:** ~150-200 additional unit tests for extracted pure logic.

---

## Principles

1. **Extract, don't rewrite** — Move existing logic to new classes, verify behavior is identical
2. **One phase at a time** — Each phase is independently deployable and testable
3. **Write tests for extracted code** — Each extracted class gets unit tests before merging
4. **Keep backward compatibility** — Original classes delegate to new ones; callers unchanged
5. **Fix bugs as encountered** — Don't let known bugs survive the refactoring
