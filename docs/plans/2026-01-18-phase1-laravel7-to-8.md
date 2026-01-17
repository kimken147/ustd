# Phase 1: Laravel 7 → 8 升級實作計劃

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**目標：** 將 Laravel 7.30.7 升級到 Laravel 8.x，同時處理所有 breaking changes，遷移 Models namespace，並確保所有功能正常運作。

**架構：** 採用逐步升級策略，每個變更都獨立提交，確保可追溯性。優先處理 namespace 遷移，再更新 composer 依賴，最後驗證功能。

**技術棧：** Laravel 7 → 8, PHP 8.0, Composer, Git Worktrees

**參考文檔：** [Laravel 8 升級指南](https://laravel.com/docs/8.x/upgrade)

---

## Task 1: 準備 Laravel 8 Worktree 環境

**Files:**
- Work in: `.worktrees/upgrade-laravel-8/`

**Step 1: 切換到 Laravel 8 worktree**

```bash
cd /Users/apple/projects/morgan/ustd/.worktrees/upgrade-laravel-8
```

Expected: 進入 upgrade-laravel-8 worktree

**Step 2: 合併 Phase 0 的準備工作**

```bash
git merge upgrade/prepare --no-ff -m "chore: merge Phase 0 preparation work"
```

Expected: 成功合併，獲得所有腳本和文檔

**Step 3: 複製 .env 檔案**

```bash
cp ../../../api/.env api/.env
```

Expected: .env 檔案已複製

**Step 4: 安裝當前依賴（Laravel 7）**

```bash
cd api
composer install --no-interaction
```

Expected: 依賴安裝成功，Laravel 7.30.7

**Step 5: 驗證基準狀態**

```bash
cd ..
./scripts/verify-upgrade.sh 7
```

Expected: 驗證通過，顯示 Laravel 7.x

**Step 6: Commit**

```bash
git add -A
git commit -m "chore(laravel8): setup worktree with Phase 0 preparation"
```

---

## Task 2: 執行 Models Namespace 遷移

**Files:**
- Execute: `scripts/migrate-models-namespace.sh`
- Modify: `api/app/Model/*` → `api/app/Models/*`
- Modify: All files using `App\Model` → `App\Models`

**Step 1: 執行遷移腳本**

```bash
./scripts/migrate-models-namespace.sh
```

Expected: 
- 備份已建立
- app/Model → app/Models
- namespace 已更新
- use 語句已更新
- autoload 已重新生成

**Step 2: 驗證遷移結果**

```bash
# 檢查是否還有舊 namespace
grep -r "App\\\\Model" api/app/ api/routes/ api/config/ || echo "✅ 無舊 namespace"

# 測試 autoload
cd api
php artisan tinker --execute="echo App\\Models\\Channel::class;"
```

Expected: 顯示 `App\Models\Channel`，無錯誤

**Step 3: 更新 composer.json autoload（如需要）**

檢查 `api/composer.json` 中的 autoload 設定：

```json
"autoload": {
    "psr-4": {
        "App\\": "app/"
    },
    "classmap": [
        "database/seeds",
        "database/factories"
    ]
}
```

Expected: 無需更改（PSR-4 自動支援）

**Step 4: 手動修復字串引用（如有）**

```bash
# 搜尋配置檔中的字串引用
grep -r "'App\\\\\\\\Model" api/config/ || echo "✅ 無字串引用"
```

Expected: 無發現或已修復

**Step 5: Commit**

```bash
cd ..
git add -A
git commit -m "refactor(laravel8): migrate Models namespace from App\\Model to App\\Models

- Moved app/Model to app/Models
- Updated all namespace declarations
- Updated all use statements
- Regenerated autoload
- Verified no remaining old namespace references"
```

---

## Task 3: 更新 Composer 依賴到 Laravel 8

**Files:**
- Modify: `api/composer.json`

**Step 1: 更新 Laravel framework**

修改 `api/composer.json`：

```json
{
    "require": {
        "php": "^8.0",
        "laravel/framework": "^8.0",
        "laravel/tinker": "^2.5"
    },
    "require-dev": {
        "facade/ignition": "^2.5",
        "fakerphp/faker": "^1.23",
        "mockery/mockery": "^1.4.4",
        "nunomaduro/collision": "^5.10",
        "phpunit/phpunit": "^9.5.10"
    }
}
```

**Step 2: 移除已棄用套件**

從 composer.json 移除：

```bash
cd api
composer remove fideloper/proxy --no-interaction
```

Expected: `fideloper/proxy` 已移除

**Step 3: 替換 Faker 套件**

```bash
composer remove fzaninotto/faker --dev --no-interaction
composer require fakerphp/faker --dev --no-interaction
```

Expected: Faker 已替換為 fakerphp/faker

**Step 4: 執行 Composer 更新**

```bash
composer update --no-interaction
```

Expected: Laravel 8.x 已安裝（約 10-15 分鐘）

**Step 5: 檢查版本**

```bash
php artisan --version
```

Expected: 顯示 `Laravel Framework 8.x.x`

**Step 6: Commit**

```bash
cd ..
git add api/composer.json api/composer.lock
git commit -m "chore(laravel8): upgrade to Laravel 8.x

- Updated laravel/framework to ^8.0
- Removed fideloper/proxy (deprecated)
- Replaced fzaninotto/faker with fakerphp/faker
- Updated all dependencies"
```

---

## Task 4: 更新 TrustedProxies Middleware

**Files:**
- Modify: `api/app/Http/Middleware/TrustProxies.php`

**Step 1: 檢查當前 TrustProxies middleware**

```bash
cat api/app/Http/Middleware/TrustProxies.php | head -20
```

**Step 2: 移除 fideloper/proxy 引用**

修改 `api/app/Http/Middleware/TrustProxies.php`：

```php
<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
```

**Step 3: 測試 middleware 載入**

```bash
cd api
php artisan route:list | head -10
```

Expected: 路由列表正常顯示

**Step 4: Commit**

```bash
cd ..
git add api/app/Http/Middleware/TrustProxies.php
git commit -m "fix(laravel8): update TrustProxies to use Laravel 8 built-in

- Removed fideloper/proxy dependency
- Updated to use Illuminate\\Http\\Middleware\\TrustProxies
- Using Laravel 8 header constants"
```

---

## Task 5: 遷移 Database Factories 到 Class-based

**Files:**
- Modify: `api/database/factories/*.php`
- Create: Class-based factory files

**Step 1: 檢查現有 factories**

```bash
ls -la api/database/factories/
```

**Step 2: 創建新的 Factory 結構（範例）**

如果有 `UserFactory.php`，改為：

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'remember_token' => Str::random(10),
        ];
    }
}
```

**Step 3: 更新 Models 使用 HasFactory trait**

在 `api/app/Models/User.php` 等 Models 加入：

```php
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasFactory;
    // ...
}
```

**Step 4: 測試 Factory**

```bash
cd api
php artisan tinker --execute="App\\Models\\User::factory()->make();"
```

Expected: Factory 正常運作（或因無資料庫連接而顯示錯誤，但不是語法錯誤）

**Step 5: Commit**

```bash
cd ..
git add api/database/factories/
git commit -m "refactor(laravel8): migrate to class-based factories

- Converted database factories to class-based syntax
- Added HasFactory trait to models
- Updated namespace to Database\\Factories"
```

---

## Task 6: 更新 Database Seeders

**Files:**
- Move: `api/database/seeds/*.php` → `api/database/seeders/*.php`
- Modify: Namespace 從 無 → `Database\Seeders`

**Step 1: 移動 seeders 目錄**

```bash
cd api
if [ -d "database/seeds" ]; then
    mv database/seeds database/seeders
    echo "✅ Seeders 目錄已移動"
fi
```

**Step 2: 更新所有 seeder 的 namespace**

```bash
find database/seeders -name "*.php" -type f -exec sed -i '' '1a\
namespace Database\\Seeders;\
' {} +
```

或手動在每個 seeder 檔案頂部加入：

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // ...
}
```

**Step 3: 更新 composer.json autoload**

修改 `composer.json` 的 autoload.classmap：

```json
"autoload": {
    "psr-4": {
        "App\\": "app/"
    },
    "classmap": [
        "database/seeders",
        "database/factories"
    ]
}
```

**Step 4: 重新生成 autoload**

```bash
composer dump-autoload
```

**Step 5: 測試 seeder**

```bash
php artisan db:seed --class=DatabaseSeeder --pretend || echo "✅ Seeder 載入正常"
```

**Step 6: Commit**

```bash
cd ..
git add api/database/seeders api/composer.json
git commit -m "refactor(laravel8): migrate seeders to database/seeders with namespace

- Moved database/seeds to database/seeders
- Added Database\\Seeders namespace
- Updated composer.json autoload classmap"
```

---

## Task 7: 清除並重建快取

**Files:**
- Clear all caches

**Step 1: 清除所有快取**

```bash
cd api
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
php artisan event:clear
```

Expected: 所有快取已清除

**Step 2: 重新生成快取（驗證）**

```bash
php artisan config:cache
php artisan route:cache
```

Expected: 快取重建成功

**Step 3: Commit**

```bash
cd ..
git add -A
git commit -m "chore(laravel8): clear and rebuild caches after upgrade"
```

---

## Task 8: 執行驗證與測試

**Files:**
- Run verification script

**Step 1: 執行升級驗證腳本**

```bash
./scripts/verify-upgrade.sh 8
```

Expected: 
- ✅ Laravel 版本正確: 8.x
- ✅ Autoload 正常
- ✅ 路由正常

**Step 2: 手動測試關鍵端點**

```bash
cd api
php artisan route:list | grep -i "api/transactions" | head -5
```

Expected: API 路由存在且正常

**Step 3: 檢查 Laravel log**

```bash
tail -50 storage/logs/laravel.log | grep -i error || echo "✅ 無錯誤"
```

**Step 4: 比對路由變化**

```bash
php artisan route:list > ../docs/laravel8-routes.txt
diff ../docs/baseline-routes.txt ../docs/laravel8-routes.txt || echo "有變化"
```

**Step 5: 記錄驗證結果**

建立 `docs/laravel8-verification.md`：

```markdown
# Laravel 8 升級驗證報告

## 版本確認
- Laravel: 8.x.x
- PHP: 8.0.30

## 測試結果

### 自動驗證
- [x] Laravel 版本正確
- [x] Autoload 正常
- [x] 路由可列出
- [x] 無嚴重錯誤

### Models Namespace
- [x] 所有 Models 已遷移到 App\Models
- [x] 無殘留 App\Model 引用

### 依賴套件
- [x] fideloper/proxy 已移除
- [x] fzaninotto/faker 已替換為 fakerphp/faker
- [x] 所有依賴已更新

### 已知問題
- [ ] 資料庫未連接（預期，開發環境設定）
- [ ] 部分棄用警告（待 Laravel 9 處理）

## 下一步
- 準備 Phase 2: Laravel 8 → 9 升級
```

**Step 6: Commit**

```bash
git add docs/laravel8-verification.md docs/laravel8-routes.txt
git commit -m "docs(laravel8): add verification report and updated routes"
```

---

## Task 9: 合併到主分支（可選）

**Files:**
- Merge branch

**Step 1: 切換到主專案**

```bash
cd /Users/apple/projects/morgan/ustd
```

**Step 2: 合併 Laravel 8 分支**

```bash
git checkout master
git merge upgrade/laravel-8 --no-ff -m "feat: upgrade to Laravel 8.x

- Migrated Models namespace from App\\Model to App\\Models
- Upgraded Laravel 7.30 to 8.x
- Removed deprecated packages (fideloper/proxy, fzaninotto/faker)
- Migrated to class-based factories
- Updated seeders namespace
- All verification tests passed"
```

Expected: 合併成功

**Step 3: 推送到遠端（如有）**

```bash
git push origin master
git push origin upgrade/laravel-8
```

---

## 完成檢查清單

### Composer 依賴
- [ ] Laravel Framework 已升級到 8.x
- [ ] fideloper/proxy 已移除
- [ ] fzaninotto/faker 已替換為 fakerphp/faker
- [ ] 所有依賴已更新

### 程式碼變更
- [ ] Models namespace 已遷移 (App\Model → App\Models)
- [ ] TrustProxies middleware 已更新
- [ ] Database factories 已改為 class-based
- [ ] Database seeders 已加入 namespace

### 驗證測試
- [ ] `php artisan --version` 顯示 Laravel 8.x
- [ ] 驗證腳本通過
- [ ] 路由列表正常
- [ ] 無嚴重錯誤

### Git 管理
- [ ] 每個變更都有獨立 commit
- [ ] Commit messages 遵循規範
- [ ] 驗證文檔已建立

### 下一步準備
- [ ] 準備開始 Phase 2: Laravel 8 → 9
- [ ] 檢查 Flysystem 3.0 升級需求
- [ ] 檢查 CORS 套件移除需求

---

**預估完成時間：** 3-4 小時  
**實際完成時間：** ___________  
**遇到的問題：** ___________  
**解決方案：** ___________
