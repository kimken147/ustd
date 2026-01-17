# Phase 2: Laravel 8 → 9 升級實作計劃

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**目標：** 將 Laravel 8.83 升級到 Laravel 9.x，處理 Flysystem 3.0 等重大變更，並確保系統穩定。

**前置條件：**
- 已完成 Phase 1 (Laravel 7 → 8)
- PHP 版本需升級至 8.1 或 8.2 (Laravel 9 要求 PHP >= 8.0.2，但很多依賴需要 8.1)

**技術棧：** Laravel 8 → 9, PHP 8.1, Flysystem 3.0, Symfony Mailer

**參考文檔：** [Laravel 9 升級指南](https://laravel.com/docs/9.x/upgrade)

---

## Task 1: 準備 Laravel 9 Worktree 環境

**Files:**
- Work in: `.worktrees/upgrade-laravel-9/`

**Step 1: 準備 Worktree**
```bash
# 確保 master 是最新的
cd /Users/apple/projects/morgan/ustd
git checkout master
git pull

# 更新或建立分支
git branch -f upgrade/laravel-9 master

# 進入 worktree
cd .worktrees/upgrade-laravel-9
git checkout upgrade/laravel-9
git reset --hard master
```

**Step 2: 切換 PHP 版本到 8.1**
```bash
../../.worktrees/upgrade-prepare/switch-php.sh 8.1
php -v # 確認是 8.1
```

**Step 3: 複製環境設定**
```bash
cp ../../api/.env api/.env
```

**Step 4: 安裝依賴（基準狀態）**
```bash
cd api
composer install
```

---

## Task 2: 更新 Composer 依賴到 Laravel 9

**Files:**
- Modify: `api/composer.json`

**Step 1: 更新 Framework 版本**
修改 `composer.json`：
```json
"require": {
    "php": "^8.0.2",
    "laravel/framework": "^9.0",
    "nunomaduro/collision": "^6.1",
    "league/flysystem-aws-s3-v3": "^3.0"
},
"require-dev": {
    "fakerphp/faker": "^1.21",
    "laravel/tinker": "^2.7",
    "mockery/mockery": "^1.5.0",
    "phpunit/phpunit": "^9.5.10",
    "spatie/laravel-ignition": "^1.0"
}
```

**Step 2: 替換 Ignition**
Laravel 9 使用 `spatie/laravel-ignition` 替代 `facade/ignition`。
```bash
composer remove facade/ignition --dev --no-interaction
composer require spatie/laravel-ignition --dev --no-interaction
```

**Step 3: 移除 Laravel CORS**
Laravel 9 已內建 CORS 支援。
```bash
composer remove fruitcake/laravel-cors --no-interaction
```

**Step 4: 執行更新**
```bash
composer update --no-interaction
```

---

## Task 3: 遷移 FileSystem (Flysystem 3.x)

這是 Laravel 9 最大的 breaking change。

**Files:**
- Modify: `api/config/filesystems.php`
- Check: All `Storage::` usages

**Step 1: 更新設定檔**
Flysystem 3.0 不再支援 `cache` adapter 舊寫法。
檢查 `config/filesystems.php`，移除舊的 cache 設定，改用新的相關寫法（如果有的話）。S3 設定通常相容。

**Step 2: 檢查異常處理**
Flysystem 3.0 異常類別變更：
- `League\Flysystem\FileNotFoundException` → `League\Flysystem\UnableToReadFile`
需全域搜尋並替換。

```bash
grep -r "FileNotFoundException" api/app/
```

**Step 3: 檢查 Put/Write 變更**
`put` 預設現在會 overwriting。如果是 `put(..., ..., 'public')` 語法可能需要調整。

---

## Task 4: 遷移 Mailer (Symfony Mailer)

SwiftMailer 已被移除。

**Files:**
- Modify: `api/config/mail.php`

**Step 1: 更新 config/mail.php**
將 `smtp` 改名為 `mailers.smtp` 等新結構（如果尚未更新）。Laravel 8 可能已經支援新結構，需檢查確認。

**Step 2: 檢查 SwiftMailer 引用**
```bash
grep -r "Swift_" api/app/
```
如果有直接使用 SwiftMailer 類別，需重構成 Symfony Mailer。

---

## Task 5: 處理 Accessors & Mutators (可選)

Laravel 9 引入新的 `Attribute` 語法，但舊的 `getXXXAttribute` 仍支援。
此任務標記為 **Optional**，優先保證升級成功。

---

## Task 6: 處理 Helper Functions

**Step 1: server.php**
如果根目錄還有 `server.php`，可以移除（Laravel 9 不再需要）。

**Step 2: str_ 和 array_ 輔助函式**
大部分仍支援，但建議改用 `Str::` 和 `Arr::`。

---

## Task 7: 驗證與測試

**Step 1: 執行驗證腳本**
```bash
../scripts/verify-upgrade.sh 9
```

**Step 2: 檢查路由**
```bash
php artisan route:list
```

**Step 3: 測試核心功能**
- 登入
- 取得交易
- 存款/提款 API (模擬)

---

## Task 8: 合併 (Phase 2 Complete)

---
