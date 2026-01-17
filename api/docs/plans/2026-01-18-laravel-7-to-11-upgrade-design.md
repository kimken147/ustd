# Laravel 7 → 11 升級設計文檔

**專案：** USTD Payment API  
**建立日期：** 2026-01-18  
**目標：** 從 Laravel 7.30.7 + PHP 8.0 → Laravel 11.x + PHP 8.3  
**預估時間：** 10 週  

---

## 專案概覽

### 當前狀態
- **Laravel 版本：** 7.30.7
- **PHP 版本：** 8.0.30
- **代碼規模：**
  - 585 個 PHP 檔案
  - 132 個 Controllers
  - 38 個 Models (位於 `App\Model` - 非標準架構)
  - 121 個第三方支付通道
- **測試覆蓋：** 0%（tests/ 目錄為空）
- **Git 狀態：** 全新倉庫，從舊專案遷移而來

### 升級目標
1. Laravel 框架：7.30.7 → 11.x（最新穩定版）
2. PHP 版本：8.0.30 → 8.3
3. 依賴清理：移除所有已棄用套件，升級到最新版本  
4. 代碼現代化：
   - 遷移 Models：`App\Model` → `App\Models`
   - 採用 Laravel 11 新架構
   - 使用 PHP 8.3 新特性
5. 建立測試：升級完成後建立測試套件

### 成功標準
- ✅ 所有 API 端點功能正常
- ✅ 所有 121 個支付通道正常運作
- ✅ 無 deprecation warnings
- ✅ 代碼符合 Laravel 11 最佳實踐
- ✅ 性能不低於原版本
- ✅ 測試覆蓋率達到 60%+

---

## 升級策略

### 路徑選擇
**逐步升級（Incremental）：** Laravel 7 → 8 → 9 → 10 → 11

**理由：**
- 可控性高，每步可驗證
- 問題容易定位
- 可隨時中斷和恢復
- 降低風險

### 測試策略
**升級後補測試（Post-upgrade testing）**
- 升級過程依賴手動測試
- 完成升級後建立完整測試套件
- 使用 Postman/API 測試清單驗證功能

### 依賴處理
**激進清理（Aggressive cleanup）**
- 移除所有已棄用套件
- 升級所有依賴到最新穩定版
- 清理未使用的套件
- 代碼完全現代化

---

## 升級路徑與時程（10 週）

### 階段 0：準備工作（第 1 週）

**環境準備**
- 安裝 PHP 8.3
- 配置多版本 PHP 環境（phpbrew 或 homebrew）
- 設置專用資料庫（複製生產資料）

**依賴分析**
```bash
# 檢查所有套件的升級路徑
composer outdated
composer show --tree
```

**建立基準清單**
- 匯出所有 API 路由：`php artisan route:list > docs/baseline-routes.txt`
- 記錄所有支付通道清單
- 建立功能測試檢查表

**Git 策略設置**
```bash
# 使用 git worktree 建立隔離分支
git worktree add ../ustd-laravel-8 -b upgrade/laravel-8
git worktree add ../ustd-laravel-9 -b upgrade/laravel-9
git worktree add ../ustd-laravel-10 -b upgrade/laravel-10
git worktree add ../ustd-laravel-11 -b upgrade/laravel-11
```

**自動化腳本準備**
- Model namespace 批量替換腳本
- Import 語句更新腳本
- 自動化測試腳本

---

### 階段 1：Laravel 7 → 8（第 2 週）

**主要 Breaking Changes**

1. **Models 命名空間遷移**
   - 移動：`app/Model/*.php` → `app/Models/*.php`
   - 全局替換：`namespace App\Model` → `namespace App\Models`
   - 更新所有 import：`use App\Model\` → `use App\Models\`
   - 更新字串引用（config、routes 等）

2. **移除 fideloper/proxy**
   ```bash
   composer remove fideloper/proxy
   ```
   - 使用內建 `TrustedProxies` middleware
   - 更新 `app/Http/Middleware/TrustProxies.php`

3. **Faker 套件更換**
   ```json
   // composer.json
   - "fzaninotto/faker": "^1.9.1"
   + "fakerphp/faker": "^1.23"
   ```

4. **Factory 改為 Class-based**
   - 從 `database/factories/*.php` 遷移到 class
   - 使用新的 Factory 語法

5. **Seeders 目錄重命名**
   ```bash
   mv database/seeds database/seeders
   ```
   - 更新 namespace：`Database\Seeders`
   - 更新 composer.json autoload

**升級步驟**

```bash
# 1. 更新 composer.json
composer require laravel/framework:^8.0
composer require fakerphp/faker --dev
composer remove fideloper/proxy

# 2. 執行升級
composer update

# 3. 清除快取
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# 4. 重新生成 autoload
composer dump-autoload

# 5. 執行 migration（如有新增）
php artisan migrate
```

**驗證檢查清單**
- [ ] `php artisan --version` 顯示 Laravel 8.x
- [ ] `php artisan route:list` 無錯誤
- [ ] `php artisan migrate:status` 正常
- [ ] 啟動開發伺服器：`php artisan serve`
- [ ] 測試 10 個主要 API 端點
- [ ] 測試 3-5 個常用支付通道
- [ ] 檢查 `storage/logs/laravel.log` 無嚴重錯誤

**Commit**
```bash
git add .
git commit -m "upgrade(laravel8): complete Laravel 8 upgrade with models namespace migration"
```

---

### 階段 2：Laravel 8 → 9（第 3 週）

**主要 Breaking Changes**

1. **Flysystem 3.0 升級**
   ```json
   "league/flysystem-aws-s3-v3": "^3.0"
   ```
   - 影響所有檔案上傳功能
   - 更新 S3 配置

2. **移除 laravel-cors**
   ```bash
   composer remove fruitcake/laravel-cors
   ```
   - Laravel 9 內建 CORS 支援
   - 更新 `config/cors.php`（使用內建版本）

3. **Symfony 6.0 兼容**
   - 所有 Symfony 元件升級到 v6

4. **Accessor/Mutator 現代化**
   ```php
   // 舊語法
   public function getFirstNameAttribute($value) {}
   
   // 新語法（選擇性）
   use Illuminate\Database\Eloquent\Casts\Attribute;
   protected function firstName(): Attribute {
       return Attribute::make(get: fn($value) => ucfirst($value));
   }
   ```

**升級步驟**

```bash
# 1. 更新 composer.json
composer require laravel/framework:^9.0
composer require league/flysystem-aws-s3-v3:^3.0
composer remove fruitcake/laravel-cors

# 2. 執行升級
composer update

# 3. 發佈新配置
php artisan config:publish cors

# 4. 清除快取
php artisan optimize:clear
```

**驗證檢查清單**
- [ ] 檔案上傳功能正常（S3）
- [ ] CORS 設定正常
- [ ] API 端點完整測試
- [ ] 支付通道驗證

**Commit**
```bash
git commit -m "upgrade(laravel9): upgrade to Laravel 9 with Flysystem 3.0"
```

---

### 階段 3：Laravel 9 → 10（第 4 週）

**主要 Breaking Changes**

1. **PHP 8.1+ 要求**
   - 使用 native type declarations
   - 採用 readonly properties（選擇性）
   - 使用 union types

2. **Invokable Validation Rules**
   ```php
   // 新語法
   public function __invoke($attribute, $value, $fail) {}
   ```

3. **Process 元件重構**
   - 更新所有 `Process::run()` 調用

4. **Doctrine DBAL 升級**
   ```json
   "doctrine/dbal": "^3.0"
   ```

**升級步驟**

```bash
composer require laravel/framework:^10.0
composer require doctrine/dbal:^3.0
composer update
php artisan optimize:clear
```

**代碼現代化**
- 為 Models 添加 type hints
- 使用 readonly properties（適當的地方）
- 更新 validation rules

**驗證與 Commit**

---

### 階段 4：Laravel 10 → 11（第 5-6 週）

**主要 Breaking Changes（重大架構變更）**

1. **移除 HTTP Kernel**
   - 刪除 `app/Http/Kernel.php`
   - 在 `bootstrap/app.php` 註冊 middleware

   ```php
   // bootstrap/app.php（新）
   return Application::configure(basePath: dirname(__DIR__))
       ->withRouting(
           web: __DIR__.'/../routes/web.php',
           api: __DIR__.'/../routes/api.php',
           commands: __DIR__.'/../routes/console.php',
       )
       ->withMiddleware(function (Middleware $middleware) {
           $middleware->web(append: [
               \App\Http\Middleware\HandleInertiaRequests::class,
           ]);
       })
       ->create();
   ```

2. **移除 Console Kernel**
   - Commands 自動發現
   - Schedule 改在 `routes/console.php`

3. **Model Casts 改用方法**
   ```php
   // 舊語法
   protected $casts = ['is_active' => 'boolean'];
   
   // 新語法
   protected function casts(): array {
       return ['is_active' => 'boolean'];
   }
   ```

4. **簡化目錄結構**
   - 移除不必要的 config 檔案（改用 .env）
   - Broadcasting、Mail 等配置簡化

**升級步驟**

```bash
# 1. 重大升級
composer require laravel/framework:^11.0
composer update

# 2. 使用 Laravel Shift 或手動重構
# 建議使用 Laravel Shift（付費）自動化處理架構變更

# 3. 手動遷移 HTTP Kernel
# - 將所有 middleware 遷移到 bootstrap/app.php
# - 刪除 app/Http/Kernel.php

# 4. 更新所有 Models 的 casts
# 批量替換：protected $casts → protected function casts()

# 5. 清理配置檔案
rm config/broadcasting.php  # 改用環境變數
# 保留必要的 config 檔案
```

**重點驗證**
- [ ] Middleware 全部正常運作
- [ ] Commands 可發現並執行
- [ ] Schedule 任務正常
- [ ] Model casts 功能正常
- [ ] 完整 API 測試

**Commit**
```bash
git commit -m "upgrade(laravel11): major upgrade to Laravel 11 with new architecture"
```

---

### 階段 5：PHP 升級至 8.3（第 7 週）

**環境切換**

```bash
# 使用 homebrew（macOS）
brew install php@8.3
brew link php@8.3

# 或使用 phpbrew
phpbrew install 8.3
phpbrew switch 8.3
```

**更新 composer.json**
```json
{
    "require": {
        "php": "^8.3"
    }
}
```

**採用 PHP 8.3 新特性**

1. **Typed Class Constants**
   ```php
   class Channel {
       public const string STATUS_ACTIVE = 'active';
       public const int MAX_RETRY = 3;
   }
   ```

2. **json_validate()**
   ```php
   // 取代
   json_decode($data) !== null
   // 改用
   json_validate($data)
   ```

3. **Override Attribute**
   ```php
   #[\Override]
   public function save(array $options = []) {
       parent::save($options);
   }
   ```

**性能測試**
- 使用 Apache Bench 或 K6 進行壓力測試
- 比對 PHP 8.0 與 8.3 的性能差異

---

### 階段 6：依賴清理與現代化（第 8 週）

**清理已棄用套件**

```bash
# 檢查未使用的套件
composer show --tree
composer unused  # 需要安裝 composer-unused

# 移除未使用的套件
composer remove [package-name]
```

**升級所有依賴**

```bash
# 升級到最新穩定版
composer update

# 檢查套件兼容性
composer outdated
```

**關鍵套件升級驗證**
- `tymon/jwt-auth` - JWT 認證
- `irazasyed/telegram-bot-sdk` - Telegram 通知
- `tttran/viet_qr_generator` - QR Code（可能需要 fork）

**代碼現代化**
- 使用 PHP 8.3 特性重構代碼
- 採用 Laravel 11 最佳實踐
- 移除過時的 workarounds

---

### 階段 7：測試與驗證（第 9-10 週）

**建立測試套件**

1. **Feature Tests（優先）**
   ```php
   // tests/Feature/Api/TransactionTest.php
   public function test_create_transaction() {
       $response = $this->postJson('/api/transactions', [...]);
       $response->assertStatus(201);
   }
   ```

2. **Unit Tests**
   ```php
   // tests/Unit/Models/ChannelTest.php
   public function test_channel_scope_active() {
       $channels = Channel::active()->get();
       $this->assertTrue($channels->every->is_active);
   }
   ```

**測試覆蓋目標**
- API 端點：80%+
- Models：60%+
- Services：50%+
- 總覆蓋率：60%+

**全面功能驗證清單**

支付通道測試（優先測試前 20 個常用通道）：
- [ ] Channel 1-20 基本功能
- [ ] 存款流程
- [ ] 提款流程
- [ ] 回調處理

API 端點測試：
- [ ] 認證系統
- [ ] Transaction CRUD
- [ ] Channel 管理
- [ ] Merchant 管理
- [ ] Report 功能

**性能驗證**
```bash
# 使用 Apache Bench
ab -n 1000 -c 10 http://localhost/api/transactions

# 或使用 K6
k6 run performance-test.js
```

**文檔更新**
- 更新 README.md
- 記錄升級過程中的問題與解決方案
- 更新部署文檔

---

## 關鍵技術挑戰

### 挑戰 1：Models Namespace 大遷移

**影響範圍：** 577 個檔案（38 Models + 539 使用這些 Models 的檔案）

**自動化腳本：**

```bash
#!/bin/bash
# scripts/migrate-models-namespace.sh

# 1. 移動檔案
mv app/Model app/Models

# 2. 更新 namespace
find app -name "*.php" -exec sed -i '' 's/namespace App\\Model/namespace App\\Models/g' {} +

# 3. 更新 use 語句
find app -name "*.php" -exec sed -i '' 's/use App\\Model\\/use App\\Models\\/g' {} +
find routes -name "*.php" -exec sed -i '' 's/use App\\Model\\/use App\\Models\\/g' {} +
find config -name "*.php" -exec sed -i '' 's/use App\\Model\\/use App\\Models\\/g' {} +

# 4. 更新字串引用（謹慎處理）
grep -r "'App\\\\Model\\\\" app/ routes/ config/

# 5. 重新生成 autoload
composer dump-autoload
```

**驗證步驟：**
```bash
# 檢查是否還有舊 namespace
grep -r "App\\Model" app/ routes/ config/ --color
grep -r "'App\\\\Model" app/ routes/ config/ --color

# 測試 autoload
php artisan tinker
>>> App\Models\Channel::count();
```

---

### 挑戰 2：已棄用依賴處理

**需要完全移除：**
| 套件 | 替代方案 | 影響 |
|------|---------|------|
| `fideloper/proxy` | Laravel 內建 TrustedProxies | 中等 |
| `fruitcake/laravel-cors` | Laravel 9+ 內建 | 低 |
| `fzaninotto/faker` | `fakerphp/faker` | 低（幾乎無痛） |

**需要重大升級：**
| 套件 | 從版本 | 到版本 | 風險 |
|------|---------|---------|------|
| `doctrine/dbal` | 2.x | 3.x | 高 |
| `league/flysystem-aws-s3-v3` | 1.x | 3.x | 中 |
| `guzzlehttp/guzzle` | 6.x/7.x | 7.8+ | 低 |

**需要兼容性檢查：**
- `irazasyed/telegram-bot-sdk`: 檢查 Laravel 11 支援
- `tttran/viet_qr_generator`: 可能需要 fork（無維護）
- `tymon/jwt-auth`: 確認最新版本兼容

**處理策略：**
```bash
# 1. 先在 Laravel 8 階段處理簡單的
composer remove fideloper/proxy
composer require fakerphp/faker --dev

# 2. Laravel 9 階段處理 Flysystem
composer require league/flysystem-aws-s3-v3:^3.0

# 3. Laravel 10 階段處理 Doctrine DBAL
composer require doctrine/dbal:^3.0

# 4. 檢查無維護的套件
composer show tttran/viet_qr_generator
# 如需要，fork 並更新 composer.json:
# "repositories": [{"type": "vcs", "url": "https://github.com/your-org/viet_qr_generator"}]
```

---

### 挑戰 3：Laravel 11 架構大變更

**HTTP Kernel 遷移**

舊架構（`app/Http/Kernel.php`）：
```php
protected $middleware = [...];
protected $middlewareGroups = ['web' => [...], 'api' => [...]];
protected $routeMiddleware = [...];
```

新架構（`bootstrap/app.php`）：
```php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        ]);
        
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
        
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        ]);
    })
    ->create();
```

**遷移步驟：**
1. 備份當前 `app/Http/Kernel.php`
2. 創建新的 `bootstrap/app.php`
3. 逐個遷移 middleware
4. 測試所有路由
5. 刪除 `app/Http/Kernel.php`

---

### 挑戰 4：無測試覆蓋的風險緩解

**手動測試清單模板**

```markdown
## Laravel X → Y 升級驗證清單

### 基礎功能
- [ ] 應用程式啟動：`php artisan serve`
- [ ] 資料庫連接：`php artisan tinker` → `DB::connection()->getDatabaseName()`
- [ ] Redis 連接：`Redis::ping()`
- [ ] 快取功能：`Cache::put('test', 'value')` → `Cache::get('test')`

### 認證系統
- [ ] JWT Token 生成
- [ ] Token 驗證
- [ ] Token 刷新
- [ ] 登出功能

### 核心 API 端點（前 20 個）
- [ ] POST /api/transactions
- [ ] GET /api/transactions/{id}
- [ ] GET /api/channels
- [ ] POST /api/deposits
- [ ] POST /api/withdrawals
... (列出所有關鍵端點)

### 支付通道測試（前 10 個常用）
- [ ] Channel #1: [名稱] - 存款測試
- [ ] Channel #1: [名稱] - 提款測試
- [ ] Channel #2: ...

### 背景任務
- [ ] Queue 處理正常
- [ ] Schedule 任務執行
- [ ] Event 觸發與監聽

### 檔案與儲存
- [ ] 檔案上傳（S3）
- [ ] 圖片處理
- [ ] QR Code 生成

### Logs 檢查
- [ ] `storage/logs/laravel.log` 無 ERROR
- [ ] 無 deprecation warnings
```

**Postman 集合建立**
1. 匯出所有 API 端點到 Postman
2. 建立環境變數（dev, staging）
3. 每次升級後執行完整測試
4. 記錄測試結果

---

## 風險評估矩陣

| 風險項目 | 影響 | 可能性 | 優先級 | 緩解策略 |
|---------|------|--------|--------|----------|
| Models namespace 遷移失敗 | 高 | 中 | 🔴 高 | 自動化腳本 + 完整測試 |
| 第三方套件不兼容 | 高 | 中 | 🔴 高 | 提前檢查 + 準備替代方案 |
| 支付通道功能中斷 | 嚴重 | 低 | 🟠 中 | 沙盒測試 + 測試清單 |
| 資料庫遷移問題 | 嚴重 | 低 | 🟠 中 | 完整備份 + 副本測試 |
| 隱藏字串類名引用 | 中 | 高 | 🟠 中 | 全局搜索 + 功能測試 |
| 性能退化 | 中 | 低 | 🟡 低 | 每階段性能測試 |
| 環境配置差異 | 低 | 中 | 🟡 低 | 更新 .env.example |

**緩解策略詳情：**

1. **資料庫備份策略**
   ```bash
   # 每個階段升級前
   mysqldump -u root -p ustd_db > backup-$(date +%Y%m%d)-laravel-X.sql
   
   # 或使用 Laravel
   php artisan backup:run
   ```

2. **回滾計畫**
   ```bash
   # 每個階段都是獨立 git 分支，可快速回滾
   git worktree remove ../ustd-laravel-X
   git branch -D upgrade/laravel-X
   ```

3. **支付通道測試策略**
   - 優先測試交易量最大的前 20 個通道
   - 使用沙盒環境（如果可用）
   - 記錄每個通道的測試結果

---

## Git 工作流程

### 分支結構

```
main (Laravel 7.30.7)
  │
  ├─ upgrade/prepare          (準備階段)
  ├─ upgrade/laravel-8        (階段 1)
  ├─ upgrade/laravel-9        (階段 2)
  ├─ upgrade/laravel-10       (階段 3)
  ├─ upgrade/laravel-11       (階段 4)
  ├─ upgrade/php-8.3          (階段 5)
  ├─ upgrade/cleanup          (階段 6)
  └─ upgrade/testing          (階段 7)
       │
       └─ main (merge after success)
```

### Git Worktree 使用

**優點：**
- 同時保留多個版本
- 快速切換和比對
- 獨立的 vendor/ 和環境

**設置：**
```bash
# 在專案根目錄
cd /Users/apple/projects/morgan/ustd/api

# 建立各階段 worktree
git worktree add ../ustd-prepare -b upgrade/prepare
git worktree add ../ustd-laravel-8 -b upgrade/laravel-8
git worktree add ../ustd-laravel-9 -b upgrade/laravel-9
git worktree add ../ustd-laravel-10 -b upgrade/laravel-10
git worktree add ../ustd-laravel-11 -b upgrade/laravel-11
git worktree add ../ustd-php83 -b upgrade/php-8.3
git worktree add ../ustd-cleanup -b upgrade/cleanup

# 檢查 worktree 列表
git worktree list
```

**工作流程：**
```bash
# 在 prepare 分支工作
cd ../ustd-prepare
# ... 完成準備工作 ...
git add .
git commit -m "prepare: add upgrade scripts and documentation"

# 切換到 laravel-8
cd ../ustd-laravel-8
git merge upgrade/prepare  # 合併準備工作
# ... 執行 Laravel 8 升級 ...
git commit -m "upgrade(laravel8): complete upgrade"

# 依此類推...
```

### Commit 規範

使用語義化 commit messages：

```bash
# 格式
<type>(<scope>): <subject>

# 類型
upgrade(laravel8): ...    # 升級相關
fix(laravel8): ...        # 修復問題
refactor(laravel8): ...   # 重構
test(laravel8): ...       # 測試
docs: ...                 # 文檔

# 範例
upgrade(laravel8): update composer dependencies to Laravel 8.x
upgrade(laravel8): migrate App\Model namespace to App\Models
fix(laravel8): resolve deprecated Faker usage in factories
refactor(laravel8): convert database factories to class-based
test(laravel8): verify all payment channels working
docs: update README with Laravel 8 requirements
```

---

## 執行檢查清單（Checklist）

### 階段 1：Laravel 7 → 8 詳細步驟

**前置準備**
- [ ] 備份資料庫
- [ ] 建立 git worktree：`upgrade/laravel-8`
- [ ] 複製 `.env` 並配置
- [ ] 執行 `composer install`（確保當前環境正常）

**升級 Composer 依賴**
- [ ] 更新 `composer.json`：
  ```json
  "laravel/framework": "^8.0"
  "fakerphp/faker": "^1.23"
  ```
- [ ] 移除：`composer remove fideloper/proxy`
- [ ] 執行：`composer update`
- [ ] 檢查：`composer outdated`（確認無警告）

**Models Namespace 遷移**
- [ ] 執行自動化腳本：`bash scripts/migrate-models-namespace.sh`
- [ ] 驗證：`grep -r "App\\\\Model" app/ routes/ config/`（應無結果）
- [ ] 測試 autoload：`php artisan tinker` → `App\Models\Channel::count()`

**Factories 遷移**
- [ ] 將 `database/factories` 改為 class-based
- [ ] 更新 `database/seeders` namespace
- [ ] 測試：`php artisan db:seed --class=ChannelSeeder`

**其他 Breaking Changes**
- [ ] 移除 `app/Http/Middleware/TrustProxies.php` 中對 fideloper/proxy 的引用
- [ ] 更新 Event Discovery（如有使用）
- [ ] 更新 Pagination views（如有自定義）

**測試與驗證**
- [ ] `php artisan --version`（應顯示 8.x）
- [ ] `php artisan serve`（啟動成功）
- [ ] `php artisan route:list`（無錯誤）
- [ ] 測試 20 個主要 API 端點
- [ ] 測試 10 個常用支付通道
- [ ] 檢查 `storage/logs/laravel.log`（無 ERROR）

**Git Commit**
- [ ] `git add .`
- [ ] `git commit -m "upgrade(laravel8): complete Laravel 8 upgrade"`
- [ ] `git push origin upgrade/laravel-8`

---

### 階段 2-7：簡化檢查清單

**每個階段的標準流程：**

1. **前置準備**
   - [ ] 備份資料庫
   - [ ] 切換到對應 worktree
   - [ ] Merge 前一階段的變更

2. **執行升級**
   - [ ] 更新 `composer.json`
   - [ ] `composer update`
   - [ ] 處理 breaking changes
   - [ ] 清除快取：`php artisan optimize:clear`

3. **測試驗證**
   - [ ] `php artisan --version`
   - [ ] `php artisan route:list`
   - [ ] 執行手動測試清單
   - [ ] 檢查 logs

4. **Commit**
   - [ ] `git commit -m "upgrade(laravelX): ..."`

---

## 自動化腳本

### 1. Models Namespace 遷移腳本

```bash
#!/bin/bash
# scripts/migrate-models-namespace.sh

set -e  # 遇到錯誤立即退出

echo "🚀 開始 Models Namespace 遷移..."

# 備份
echo "📦 建立備份..."
tar -czf backup-before-model-migration-$(date +%Y%m%d-%H%M%S).tar.gz app/

# 移動目錄
echo "📁 移動 app/Model -> app/Models..."
if [ -d "app/Model" ]; then
    mv app/Model app/Models
else
    echo "⚠️  app/Model 目錄不存在，跳過"
fi

# 更新 namespace
echo "🔧 更新 namespace..."
find app -name "*.php" -type f -exec sed -i '' 's/namespace App\\Model;/namespace App\\Models;/g' {} +

# 更新 use 語句
echo "🔧 更新 use 語句..."
find app routes config database -name "*.php" -type f -exec sed -i '' 's/use App\\Model\\/use App\\Models\\/g' {} +

# 更新 composer.json
echo "🔧 更新 composer.json..."
sed -i '' 's/"App\\\\\\\\"/"App\\\\\\\\Models\\\\\\\\"/g' composer.json

# 重新生成 autoload
echo "🔄 重新生成 autoload..."
composer dump-autoload

# 檢查殘留
echo "🔍 檢查殘留的舊 namespace..."
OLD_NAMESPACE_COUNT=$(grep -r "App\\\\Model" app/ routes/ config/ 2>/dev/null | wc -l || echo "0")
if [ "$OLD_NAMESPACE_COUNT" -gt 0 ]; then
    echo "⚠️  發現 $OLD_NAMESPACE_COUNT 處仍使用舊 namespace："
    grep -r "App\\\\Model" app/ routes/ config/ --color
    echo ""
    echo "請手動檢查並修復"
else
    echo "✅ 未發現舊 namespace"
fi

echo "✨ Models Namespace 遷移完成！"
echo "📝 請執行測試確認功能正常"
```

### 2. 升級驗證腳本

```bash
#!/bin/bash
# scripts/verify-upgrade.sh

EXPECTED_VERSION=$1

if [ -z "$EXPECTED_VERSION" ]; then
    echo "用法: ./verify-upgrade.sh <expected-version>"
    echo "範例: ./verify-upgrade.sh 8"
    exit 1
fi

echo "🔍 驗證 Laravel ${EXPECTED_VERSION} 升級..."

# 檢查版本
CURRENT_VERSION=$(php artisan --version | grep -oE '[0-9]+\.[0-9]+' | head -1)
MAJOR_VERSION=$(echo $CURRENT_VERSION | cut -d. -f1)

if [ "$MAJOR_VERSION" != "$EXPECTED_VERSION" ]; then
    echo "❌ 版本不符！期望: ${EXPECTED_VERSION}.x，實際: $CURRENT_VERSION"
    exit 1
fi

echo "✅ Laravel 版本正確: $CURRENT_VERSION"

# 檢查 autoload
echo "🔍 檢查 autoload..."
composer dump-autoload --optimize 2>&1 | grep -i error && {
    echo "❌ Autoload 有錯誤"
    exit 1
}
echo "✅ Autoload 正常"

# 檢查基本指令
echo "🔍 檢查基本指令..."
php artisan route:list > /dev/null 2>&1 || {
    echo "❌ route:list 失敗"
    exit 1
}
echo "✅ 路由正常"

# 檢查資料庫連接
echo "🔍 檢查資料庫連接..."
php artisan tinker --execute="echo DB::connection()->getDatabaseName();" > /dev/null 2>&1 || {
    echo "❌ 資料庫連接失敗"
    exit 1
}
echo "✅ 資料庫連接正常"

# 檢查 logs
echo "🔍 檢查最近的錯誤..."
if [ -f "storage/logs/laravel.log" ]; then
    ERROR_COUNT=$(grep -c "ERROR" storage/logs/laravel.log 2>/dev/null || echo "0")
    if [ "$ERROR_COUNT" -gt 0 ]; then
        echo "⚠️  發現 $ERROR_COUNT 個 ERROR"
    else
        echo "✅ 無錯誤日誌"
    fi
fi

echo "✨ 驗證完成！"
```

### 3. 性能測試腳本

```bash
#!/bin/bash
# scripts/performance-test.sh

API_URL=${1:-"http://localhost:8000"}
REQUESTS=${2:-1000}
CONCURRENCY=${3:-10}

echo "🚀 性能測試開始..."
echo "API: $API_URL"
echo "請求數: $REQUESTS"
echo "並發數: $CONCURRENCY"
echo ""

# 測試端點
ENDPOINTS=(
    "/api/health"
    "/api/channels"
    "/api/transactions"
)

for endpoint in "${ENDPOINTS[@]}"; do
    echo "📊 測試: $endpoint"
    ab -n $REQUESTS -c $CONCURRENCY "$API_URL$endpoint" 2>&1 | grep -E "Requests per second|Time per request|Failed requests"
    echo ""
done

echo "✨ 性能測試完成！"
```

---

## 參考資源

### 官方升級指南
- [Laravel 8 升級指南](https://laravel.com/docs/8.x/upgrade)
- [Laravel 9 升級指南](https://laravel.com/docs/9.x/upgrade)
- [Laravel 10 升級指南](https://laravel.com/docs/10.x/upgrade)
- [Laravel 11 升級指南](https://laravel.com/docs/11.x/upgrade)

### 工具推薦
- **Laravel Shift**: https://laravelshift.com/ (付費自動化升級工具)
- **Rector**: https://github.com/rectorphp/rector-laravel (自動重構工具)
- **PHPStan/Larastan**: 靜態分析工具

### 社群資源
- Laravel News: 升級相關文章
- Laracasts: 視頻教學

---

## 附錄

### A. 手動測試清單模板

見前述「挑戰 4」章節

### B. API 端點清單

```bash
# 匯出當前所有路由
php artisan route:list > docs/baseline-routes.txt

# 匯出 JSON 格式（可用於 Postman）
php artisan route:list --json > docs/baseline-routes.json
```

### C. 支付通道清單

```bash
# 匯出所有通道
php artisan tinker --execute="
    App\Models\Channel::all(['id', 'name', 'code', 'is_active'])
        ->toJson()
" > docs/payment-channels.json
```

### D. 環境變數檢查清單

Laravel 11 移除了部分 config 檔案，改用環境變數：

```env
# .env.example (Laravel 11)

# 新增的環境變數
APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12
VITE_APP_NAME="${APP_NAME}"

# Broadcasting
BROADCAST_CONNECTION=log

# 其他保持不變...
```

---

## 總結

### 預估工作量
- **總時間：** 10 週
- **關鍵里程碑：**
  - 第 2 週：Laravel 8 完成
  - 第 4 週：Laravel 9 完成  
  - 第 6 週：Laravel 11 完成
  - 第 7 週：PHP 8.3 完成
  - 第 10 週：測試與驗證完成

### 成功關鍵因素
1. ✅ 逐步升級，每步驗證
2. ✅ 使用 Git Worktree 管理多版本
3. ✅ 自動化腳本減少人為錯誤
4. ✅ 完整的手動測試清單
5. ✅ 充分的備份與回滾計畫

### 下一步行動
1. **立即執行：** 閱讀並批准此設計文檔
2. **第 1 週：** 準備環境、建立 Git Worktree、執行依賴分析
3. **第 2 週：** 開始 Laravel 7 → 8 升級

---

**文檔版本：** 1.0  
**最後更新：** 2026-01-18  
**狀態：** 待批准

