# Phase 4: Laravel 10 → 11 升級實作計劃

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**目標：** 將 Laravel 10.x 升級到 Laravel 11.x。

**技術棧：** Laravel 10 → 11, PHP 8.3

**參考文檔：** [Laravel 11 升級指南](https://laravel.com/docs/11.x/upgrade)

---

## Task 1: 準備 Laravel 11 Worktree 環境

**Files:**
- Work in: `.worktrees/upgrade-laravel-11/`

**Step 1: 準備 Worktree**
```bash
cd /Users/apple/projects/morgan/ustd
git branch -f upgrade/laravel-11 master

# 建立 Worktree
```

**Step 2: 確保 PHP 8.3**
Laravel 11 需要 PHP 8.2+。我們使用 PHP 8.3。

**Step 3: 複製環境設定**
```bash
cp ../../api/.env api/.env
```

---

## Task 2: 更新 Composer 依賴到 Laravel 11

**Step 1: 更新 Framework 版本**
修改 `api/composer.json`：
```json
"require": {
    "php": "^8.2",
    "laravel/framework": "^11.0",
    "nunomaduro/collision": "^8.1",
    "spatie/laravel-ignition": "^2.4" 
},
"require-dev": {
    "barryvdh/laravel-ide-helper": "^3.0" 
}
```
*註：`spatie/laravel-ignition` 可能需要檢查 Laravel 11 相容性。`doctrine/dbal` 可移除（除非特定需要）。*

**Step 2: 執行更新**
```bash
export PATH="/usr/local/opt/php@8.3/bin:$PATH"
composer update --no-interaction
```

---

## Task 3: 處理 Breaking Changes

**Step 1: Doctrine DBAL**
Laravel 11 內建了更多資料庫 schema 操作功能，不再強烈依賴 `doctrine/dbal`。
檢查是否有直接使用 DBAL 的代碼。
```bash
grep -r "Doctrine" api/app/
```

**Step 2: Service Provider 變更**
Laravel 11 預設移除了某些 Service Provider discovery，但對升級的應用程式兼容。檢查 `config/app.php` 中的 providers 列表。

**Step 3: Middleware**
舊的 `App\Http\Kernel` 仍然有效。不需強制遷移。

---

## Task 4: 驗證與測試

**Step 1: 驗證腳本**
```bash
./scripts/verify-upgrade.sh 11
```

**Step 2: 路由檢查**
```bash
php artisan route:list
```

**Step 3: 驗證報告與合併**

---
