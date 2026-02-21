# API Refactoring Roadmap

## Completed

| Phase | Description | Commit | Date |
|-------|-------------|--------|------|
| Phase 1 | Dead code cleanup — 39 files removed | `95184cfab` | 2026-02-10 |
| Phase 2 | Extract 4 shared services — QrCodeService, CertificateService, DateRangeValidator, UserChannelAccountService | `12c6a9e4c` | 2026-02-10 |
| Phase 3 | TransactionFactory refactoring — extract TransactionFeeService, add BankCardTransferObject::toFromChannelAccount(), remove dead code (1597 → 919 lines) | `3113c7644` | 2026-02-10 |
| Phase 4 | TransactionUtil refactoring — extract TransactionLockService (133 lines) and TransactionStatusService (1,311 lines), TransactionUtil reduced to ~105-line thin proxy | `d185a7dd1` | 2026-02-10 |
| Phase 5 | Provider/Merchant Controller dedup — extract UserManagementService (5 shared methods), ProviderController 621→526, MerchantController 549→453 | `731a41c0a` | 2026-02-12 |
| Phase 6 | CreateTransactionService extraction — extract AccountMatchingQueryBuilder and TransactionValidationService | `dde4c4113` | 2026-02-21 |
| Phase 7 | Eliminate Admin controller TransactionUtil dependency — 6 Admin controllers + CreateTransactionService::handleCallback() now use TransactionStatusService/TransactionLockService directly | — | 2026-02-21 |

## Remaining — P1: Service Layer Unification

### TransactionUtil thin proxy (~10 remaining callers)
- Provider/Merchant controllers, Jobs, Commands still use TransactionUtil
- Status: Pending

## Remaining — P2: Medium Impact

| Item | Description |
|------|-------------|
| Country Notification Job consolidation | CN/VN/PH ProcessTransactionNotification Jobs share similar structure — use Template Method |
| TransactionFactory mutable state | Replace public properties + `fresh()` with immutable DTOs |
| Swallowed Exceptions | `catch (exception $e) { DB::rollBack(); }` with no logging, lowercase `exception` |
| Raw SQL to Repository | Admin WithdrawController, TransactionController raw join/sum queries |

## Separate Track

| Item | Description |
|------|-------------|
| Laravel 7 → 11 upgrade | Design docs exist, ~10 week estimate, currently 0% test coverage |
