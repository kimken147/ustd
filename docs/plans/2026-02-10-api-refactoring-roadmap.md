# API Refactoring Roadmap

## Completed

| Phase | Description | Commit | Date |
|-------|-------------|--------|------|
| Phase 1 | Dead code cleanup — 39 files removed | `95184cfab` | 2026-02-10 |
| Phase 2 | Extract 4 shared services — QrCodeService, CertificateService, DateRangeValidator, UserChannelAccountService | `12c6a9e4c` | 2026-02-10 |
| Phase 3 | TransactionFactory refactoring — extract TransactionFeeService, add BankCardTransferObject::toFromChannelAccount(), remove dead code (1597 → 919 lines) | `3113c7644` | 2026-02-10 |
| Phase 4 | TransactionUtil refactoring — extract TransactionLockService (133 lines) and TransactionStatusService (1,311 lines), TransactionUtil reduced to ~105-line thin proxy | `d185a7dd1` | 2026-02-10 |

## Remaining — P0: God Classes

### Phase 5: Provider/Merchant Controller Duplication (621+549 lines)
- `ProviderController` and `MerchantController` have 6+ identical methods (resetPassword, resetGoogle2fa, store validation, etc.)
- Extract `UserManagementService`
- Status: Pending

## Remaining — P1: Service Layer Unification

### CreateTransactionService (1,335 lines, 63 methods)
- 59 private methods covering validation, channel matching, rate limiting, floating amounts, query building, QR codes, notifications
- Extract: ChannelMatchingService, AccountQueryBuilder, TransactionValidationService
- Status: Pending

### Admin WithdrawController
- Still calls TransactionUtil directly, not using the new WithdrawService pattern
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
