# 依賴套件分析

## 需要完全移除的套件

### fideloper/proxy (v4.4.2)
- **原因：** Laravel 8+ 已內建 TrustedProxies
- **替代方案：** 使用 `App\Http\Middleware\TrustProxies`
- **移除階段：** Laravel 7 → 8

### fruitcake/laravel-cors (v2.x)
- **原因：** Laravel 9+ 已內建 CORS 支援
- **替代方案：** Laravel 內建 CORS middleware
- **移除階段：** Laravel 8 → 9

### fzaninotto/faker
- **原因：** 專案已停止維護
- **替代方案：** `fakerphp/faker`
- **移除階段：** Laravel 7 → 8

## 需要重大升級的套件

### doctrine/dbal
- **當前版本：** 2.13.x  
- **目標版本：** 3.x
- **Breaking Changes：** API 變更，類型系統改進
- **升級階段：** Laravel 9 → 10
- **風險：** 中高（資料庫操作）

### league/flysystem-aws-s3-v3
- **當前版本：** 1.0.x
- **目標版本：** 3.x
- **Breaking Changes：** Flysystem 3.0 完全重寫
- **升級階段：** Laravel 8 → 9
- **風險：** 中（檔案上傳功能）

### guzzlehttp/guzzle
- **當前版本：** 6.x/7.x
- **目標版本：** 7.8+
- **Breaking Changes：** 較少，主要是棄用部分 API
- **升級階段：** Laravel 7 → 8
- **風險：** 低

## 需要檢查兼容性的套件

### irazasyed/telegram-bot-sdk (v3.9)
- **Laravel 11 兼容性：** 需要驗證
- **備用方案：** 尋找其他 Telegram SDK

### tymon/jwt-auth (dev-develop)
- **當前使用：** develop 分支
- **Laravel 11 兼容性：** 需要驗證
- **風險：** 中（使用 dev 分支）

### tttran/viet_qr_generator (v0.6)
- **維護狀態：** 可能已無維護
- **Laravel 11 兼容性：** 未知
- **備用方案：** Fork 並自行維護

## 升級策略總結

### Phase 1: Laravel 7 → 8
- 移除 `fideloper/proxy`
- 替換 `fzaninotto/faker` → `fakerphp/faker`
- 升級 `guzzlehttp/guzzle` 到 7.8+

### Phase 2: Laravel 8 → 9
- 移除 `fruitcake/laravel-cors`
- 升級 `league/flysystem-aws-s3-v3` 到 3.x
- 測試檔案上傳功能

### Phase 3: Laravel 9 → 10
- 升級 `doctrine/dbal` 到 3.x
- 測試資料庫操作

### Phase 4: Laravel 10 → 11
- 驗證所有套件兼容性
- 處理不兼容套件（fork 或替換）
