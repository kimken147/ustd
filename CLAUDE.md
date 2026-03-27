# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

pnpm monorepo for a payment processing platform (跑分/代付系統):

```
ustd/
├── api/                    # Laravel 11 REST API (PHP 8.2+)
├── apps/
│   ├── admin/              # Admin portal (React 18 + Refine v5 + Ant Design v5)
│   └── merchant/           # Merchant portal (React 18 + Refine v5 + Ant Design v5)
├── packages/
│   └── shared/             # Shared TypeScript interfaces, hooks, providers
├── frontend-env/           # Shared base .env files for frontend apps
└── docker-compose.yml      # MySQL 8.0 + Redis 7
```

**Package names:** `@morgan-ustd/admin`, `@morgan-ustd/merchant`, `@morgan-ustd/shared`

## Common Commands

### PHP Version

Requires PHP 8.2+ (recommend 8.3). Local machine may default to PHP 8.0:
```bash
./switch-php.sh 8.3          # Switch PHP version
```

### API (Laravel)

```bash
cd api
composer install
php artisan serve
php artisan test                    # All tests
php artisan test tests/Unit         # Unit tests only (~837 tests, takes ~11 min)
php artisan test --filter=TestName  # Single test
php artisan migrate
```

### Frontend

```bash
pnpm install                              # Install all (from root)
pnpm --filter @morgan-ustd/admin dev      # Admin dev server (port 4001)
pnpm --filter @morgan-ustd/merchant dev   # Merchant dev server (port 4002)
pnpm -r typecheck                         # TypeScript check all packages
```

Build per brand: `pnpm run build:morgan`, `pnpm run build:dogepay`, etc. (in app dir)

## Git Branching

- `master` — primary branch (origin/HEAD)
- `dev` — development → deploys to `ustd-dev` Elastic Beanstalk
- `prod` — production → deploys to `GAMAPAY-USDT` Elastic Beanstalk

CI/CD: `.github/workflows/` — triggers on push to `dev` or `prod` (api/** changes only). No automated tests in pipeline; tests run locally.

## Architecture

### Domain Model — Key Constants

**User roles** (class constants on `User` model):
- `ROLE_ADMIN=1`, `ROLE_PROVIDER=2`, `ROLE_MERCHANT=3`, `ROLE_SUB_ACCOUNT=4`, `ROLE_MERCHANT_SUB_ACCOUNT=5`

**Transaction types** (`Transaction` model):
- `TYPE_PAUFEN_TRANSACTION=1` (跑分收款), `TYPE_PAUFEN_WITHDRAW=2` (跑分代付)
- `TYPE_NORMAL_DEPOSIT=3`, `TYPE_NORMAL_WITHDRAW=4`, `TYPE_INTERNAL_TRANSFER=5`, `TYPE_NATIVE_TRANSFER=6`
- Sub-types: `SUB_TYPE_WITHDRAW=1`, `SUB_TYPE_AGENCY_WITHDRAW=2` (代付), `SUB_TYPE_WITHDRAW_PROFIT=3`

**Transaction statuses**: `PENDING_REVIEW=1`, `REVIEW_PASSED=101`, `MATCHING=2`, `PAYING=3`, `SUCCESS=4`, `MANUAL_SUCCESS=5`, `MATCHING_TIMED_OUT=6`, `PAYING_TIMED_OUT=7`, `FAILED=8`, `PAYED=9`, `RECEIVED=10`, `THIRD_PAYING=11`

**Currencies**: `USDT`, `TRX`, `ETH`, `BNB`

**Critical: Transaction field semantics (from/to) vary by type:**
- 代付單 (withdraw): `from` = 目標(收款方), `to` = 來源(出款帳號) — **跟直覺相反**
- 內轉 (internal transfer): `from` = 來源, `to` = 目標 — 符合直覺

### API Layer (Laravel)

**Controller organization** — by role subdirectory:
```
app/Http/Controllers/
├── Admin/       (31+ controllers)
├── Merchant/    (12+ controllers)
├── Provider/    (17+ controllers)
└── ThirdParty/  (7 controllers — external API integrations)
```

**Service layer patterns:**
- `BaseAuthService` (abstract) → `AdminAuthService`, `MerchantAuthService`, `ProviderAuthService` — template method pattern with hook methods (`validateUserBeforeAttempt`, `validateAfterLogin`, `updateLoginRecord`)
- `BaseWithdrawService` (abstract) → `WithdrawService`, `AgencyWithdrawService` — core `execute()` flow with validation, cost calculation, transaction creation. Uses DTOs: `WithdrawContext`, `WithdrawResult`
- `CreateTransactionService` — transaction creation with `CreateTransactionContext`/`CreateTransactionResult` DTOs

**DTOs** in `app/DTOs/` and within service directories:
- `TransactionParams`, `WithdrawContext`, `WithdrawResult`, `CreateTransactionContext`, `CreateTransactionResult`, `ChainTransaction`, `MatchedInfo`

**ThirdChannel pattern** (`app/ThirdChannel/`):
- Each payment gateway extends abstract `ThirdChannel` base class
- Standard interface: `sendDeposit()`, `queryDeposit()`, `sendDaifu()`, `queryDaifu()`, `callback()`, `queryBalance()`, `makesign()`
- `ThirdChannelDispatcher` routes withdraw requests to appropriate channel

**Crypto/blockchain** (`app/Services/Crypto/`):
- `ChainAdapterInterface` → `Trc20Adapter` (TRON), `EvmAdapter` (ETH/BSC)
- `ChainAdapterFactory` resolves adapter by chain network
- `ChainTransactionSyncService`, `ChainTransactionMatchService`, `CryptoMonitorService`
- Energy rental: `EnergyRentalProviderInterface` → `NettsProvider`, `NullProvider`

**Key utility classes** (`app/Utils/`):
- `TransactionFactory`, `TransactionMutator`, `TransactionDataBuilder` — transaction lifecycle
- `WalletUtil`, `WalletBalanceCalculator` — balance operations
- `BCMathUtil`, `FloatUtil` — arbitrary precision arithmetic
- `AtomicLockUtil` — pessimistic locking for concurrent transactions
- `SignatureCalculator` — HMAC-SHA256 for merchant API verification

**Builder pattern** (`app/Builders/Transaction`) — fluent query builder for complex transaction filtering (status, provider, merchant, channel, amount range, etc.)

**Repository pattern** (`app/Repository/`): `FeatureToggleRepository`, `StatisticsRepository`, `UserTransactionStatRepository`

**Job queue** (`app/Jobs/`):
- `ProcessUsdtWithdraw`, `ConfirmUsdtWithdraw`, `BatchTransferUsdt`, `ProcessNativeTransfer`
- `NotifyTransaction` — merchant webhook with retry (exponential backoff)
- `ConsolidateChildAccount`, `BackfillChainTransactions`
- `MarkPaufenTransactionMatchingTimedOut`, `MarkPaufenTransactionPayingTimedOut`
- Queue priority levels: high, normal, low (config in `config/queue.php`)

**Routes:** `routes/api-v1.php` — organized by role prefix (`admin/`, `merchant/`, `provider/`, `third-party/`) with middleware chains for auth, role, permission, IP validation.

**Authentication:** JWT (`php-open-source-saver/jwt-auth`) + optional Google 2FA

### Frontend Layer (React)

Both apps use **Refine v5** framework with custom providers:

**Provider stack** (per app):
- `dataProvider.ts` — custom REST data provider; shared factory in `@morgan-ustd/shared` (`createDataProvider`). Adds `X-Locale` header via axios interceptor.
- `authProvider.ts` — cookie-based JWT auth (`admin_access_token` cookie), stores profile/permissions in localStorage, supports Google 2FA
- `accessControlProvider.ts` — permission-based access control by role ID

**Admin app** (27 Refine resources, ~289 files) — full platform management: transactions, withdrawals, providers, merchants, channels, crypto, feature toggles, permissions, announcements
- `AppModeContext` — toggles between Paufen (跑分) and standard modes

**Merchant app** (8 Refine resources, ~72 files) — merchant-facing: collections, withdrawals, wallet history, team management

**Shared package exports:**
- Interfaces: `User`, `Transaction`, `Withdraw`, `Channel`, `UserChannel`, `ChainTransaction`, `Merchant`, `Tag`, `Bank`
- Hooks: `useSelector`, `useWithdrawStatus`, `useTransactionStatus`, `useTransactionCallbackStatus`, `useUpdateModal`
- Components: `ListPageLayout` (compound component with Filter + Table)
- Data provider factory: `createDataProvider(config, httpClient)`

**Frontend imports use absolute paths** from `src/` (configured via `tsconfig.json` baseUrl):
```typescript
import { SomeComponent } from 'components/SomeComponent';  // → src/components/
```

### Environment Configuration

**Frontend env layering:** base files in `frontend-env/.env.base.*` + app overrides in `apps/{app}/env-config/.env.*`. Loaded via `dotenv -e` chaining.

Key env vars: `REACT_APP_API_HOST`, `REACT_APP_IS_PAUFEN`, `REACT_APP_TRON_NETWORK`, `REACT_APP_REVERB_*` (WebSocket)

**API:** standard Laravel `api/.env`

### Internationalization

**API:** `api/resources/lang/{zh_CN,en}/common.php` — usage: `__('common.Key')`

**Frontend:** i18next with HTTP backend loading from `public/locales/{{lng}}/{{ns}}.json`. 13 namespaces (common, transaction, merchant, etc.). Fallback: `zh-CN`.

Language detection: `X-Locale` header → `Accept-Language` → `locale` query param → user setting → app default

### Infrastructure

- **Docker:** PHP 8.3-FPM + Nginx + Supervisor (queue workers + Reverb WebSocket)
- **Database:** MySQL 8.0 (utf8mb4)
- **Cache/Queue:** Redis 7
- **Deployment:** AWS Elastic Beanstalk (ap-southeast-1), zip-based deploy via GitHub Actions
- **Frontend hosting:** AWS Amplify (auto-build on branch push)
- **WebSocket:** Laravel Reverb — `NewWithdrawCreated` event for real-time notifications
- **Notifications:** Telegram bot integration

## Code Style

**PHP:** StyleCI with Laravel preset (`.styleci.yml`). No PHP enums — all constants are class constants.

**JS/TS:** ESLint + Prettier (`apps/admin/.eslintrc.json`)

## Testing

**PHP:** PHPUnit 10 — ~49 unit test files organized by Services, Utils, Jobs, Repository. Feature/Browser tests directories exist but are empty. Test env uses `QUEUE_CONNECTION=sync`, `CACHE_DRIVER=array`.

**Frontend:** Minimal — Jest via react-scripts, only 1 test file exists.
