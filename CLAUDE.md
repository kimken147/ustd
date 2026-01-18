# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a monorepo for a payment processing platform with three main components:
- **api/** - Laravel 11 REST API backend (PHP 8.2+)
- **admin/** - Admin portal (React 18 + Refine v4 + Ant Design)
- **merchant/** - Merchant portal (React 18 + Refine v4 + Ant Design)

## Common Commands

### PHP Version

This project requires PHP 8.2+. If you encounter PHP version issues, use the `switch-php.sh` script to switch between versions:

```bash
# Switch to PHP 8.3 (recommended)
./switch-php.sh 8.3

# Switch to PHP 8.0 (if needed for compatibility)
./switch-php.sh 8.0

# Check current PHP version
php --version
```

### API (Laravel)

```bash
cd api

# Install dependencies
composer install

# Run development server
php artisan serve

# Run tests
php artisan test                    # All tests
php artisan test tests/Unit         # Unit tests only
php artisan test tests/Feature      # Feature tests only
php artisan test --filter=TestName  # Single test

# Database
php artisan migrate
php artisan db:seed

# Cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Admin Portal

```bash
cd admin

# Install dependencies
yarn install

# Development server
yarn local                # Local environment
yarn dev                  # Development environment

# Build for specific environment
yarn build:morgan         # Morgan environment
yarn build:dogepay        # Dogepay environment
# (see package.json for all environments)

# Tests
yarn test
```

### Merchant Portal

```bash
cd merchant

# Same commands as admin
yarn install
yarn local
yarn dev
yarn build:morgan
yarn test
```

## Architecture

### API Layer (Laravel)

**Design Patterns:**
- MVC + Repository Pattern
- Service Layer for business logic
- API Resource classes for serialization

**Key Directories:**
- `app/Http/Controllers/` - 24+ controllers organized by role (Admin, Merchant, Provider, ThirdParty)
- `app/Models/` - 40+ Eloquent models
- `app/Services/` - Business logic services
- `app/Utils/` - 30+ utility classes
- `app/ThirdChannel/` - Third-party payment channel integrations
- `app/Notifications/` - Telegram, Email notifications
- `app/Exceptions/` - 19+ custom exception classes

**Main Route File:** `routes/api-v1.php`

**Authentication:** JWT-based (`php-open-source-saver/jwt-auth`) with optional 2FA (`pragmarx/google2fa-laravel`)

### Frontend Layer (React)

Both admin and merchant portals use:
- **Refine v4** - Enterprise admin framework
- **Ant Design v4** - UI component library
- **i18next** - Internationalization
- **TypeScript**

**Key Files:**
- `src/App.tsx` - Main application component
- `src/dataProvider.ts` - API data provider
- `src/authProvider.ts` - Authentication provider

### Internationalization (i18n)

**API Language Files:** `api/resources/lang/{zh_CN,en}/common.php`

**Language Detection Priority:**
1. `X-Locale` Header
2. `Accept-Language` Header
3. `locale` Query Parameter
4. User's language setting
5. App default

**Usage in Controllers:**
```php
__('common.User not found')
__('common.Missing parameter: :attribute', ['attribute' => 'username'])
```

See `api/I18N_SETUP.md` and `api/COMMON_PHP_REFACTOR_SUMMARY.md` for details.

## Code Style

**PHP:** StyleCI with Laravel preset (see `.styleci.yml`)

**JavaScript/TypeScript:** ESLint + Prettier (see `admin/.eslintrc.json`)

## CI/CD

GitHub Actions workflows in `.github/workflows/`:
- `morgan-*.yaml` - Development branch builds
- `prod-*.yaml` - Production deployments

## Environment Configuration

Environment files are located in `.env/` directories:
- Admin: `admin/.env/.env.{local,development,morgan,...}`
- Merchant: `merchant/.env/.env.{local,development,morgan,...}`
- API: `api/.env` (standard Laravel)

## Multi-tenant Deployment

The system supports multiple branded deployments (Morgan, Dogepay, SinoPay, PowerPay, etc.) via environment-specific builds. Each has its own:
- Environment configuration
- Build script (`yarn build:{brand}`)
- CI/CD workflow
