# Phase 3: Laravel 9 → 10 升級實作計劃

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**目標：** 將 Laravel 9.52 升級到 Laravel 10.x。

**技術棧：** Laravel 9 → 10, PHP 8.3

**參考文檔：** [Laravel 10 升級指南](https://laravel.com/docs/10.x/upgrade)

---

## Task 1: 準備 Laravel 10 Worktree 環境

**Files:**
- Work in: `.worktrees/upgrade-laravel-10/`

**Step 1: 準備 Worktree**
```bash
cd /Users/apple/projects/morgan/ustd
git branch -f upgrade/laravel-10 master

# 進入 worktree (或建立新的)
# 如果已存在先清理
```

**Step 2: 切換 PHP 版本到 8.3**
因為 Laravel 10 強制需要 PHP 8.1+，我們已經確認有 PHP 8.3。
需確保在此 worktree 環境下使用 `php8.3`。

**Step 3: 複製環境設定**
```bash
cp ../../api/.env api/.env
```

---

## Task 2: 更新 Composer 依賴到 Laravel 10

**Step 1: 更新 Framework 版本**
修改 `api/composer.json`：
```json
"require": {
    "php": "^8.1",
    "laravel/framework": "^10.0",
    "doctrine/dbal": "^3.0",
    "spatie/laravel-ignition": "^2.0",
    "nunomaduro/collision": "^7.0",
    "phpunit/phpunit": "^10.0" 
}
```
*註：如果不升級 PHPUnit 到 10，保持 9.5.10 也可以，Laravel 10 仍支援 PHPUnit 9。*

**Step 2: 執行更新**
```bash
composer update --no-interaction
```

---

## Task 3: 處理 Breaking Changes

**Files:**
- Modify: `api/app`

**Step 1: dispatchNow 替換**
Laravel 10 移除了 `dispatchNow`，需替換為 `dispatchSync`。
```bash
grep -r "dispatchNow" api/app/
# 替換動作
```

**Step 2: $dates 屬性棄用**
Model 中的 `$dates` 屬性已棄用，建議改用 `$casts`。
（這不是強制性的 breaking change，應用程式仍可運行，但建議調整）。

**Step 3: Form Request 授權方法**
Model Route Binding 在 Form Request 中的行為有變，如果 `authorize` 方法返回 false 會拋出 403。

**Step 4: SwiftMailer 完全移除**
在 Laravel 9 已經處理，這裡再次確認。

---

## Task 4: 驗證與測試

**Step 1: 驗證腳本**
```bash
./scripts/verify-upgrade.sh 10
```

**Step 2: 路由檢查**
```bash
php artisan route:list
```

**Step 3: Tinker 測試**

---

## Task 5: 合併 (Phase 3 Complete)

---
