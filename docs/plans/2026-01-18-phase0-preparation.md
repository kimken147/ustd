# Phase 0: Laravel Upgrade Preparation Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** 準備 Laravel 7 → 11 升級所需的環境、腳本和基準測試，為後續階段打好基礎。

**Architecture:** 建立多個 git worktree 隔離環境，準備自動化遷移腳本，建立 API 端點基準清單，配置 PHP 8.3 環境。

**Tech Stack:** Git Worktrees, Bash Scripts, Laravel 7, PHP 8.0/8.3, Composer

---

## Task 1: 安裝和配置 PHP 8.3

**Files:**
- Verify: PHP installation via Homebrew

**Step 1: 檢查當前 PHP 版本**

Run: `php --version`
Expected: 顯示 PHP 8.0.30

**Step 2: 安裝 PHP 8.3**

```bash
# 使用 Homebrew 安裝 PHP 8.3
brew install php@8.3

# 驗證安裝
/opt/homebrew/opt/php@8.3/bin/php --version
```

Expected: 顯示 PHP 8.3.x

**Step 3: 配置 PHP 版本切換**

```bash
# 創建切換腳本
cat > switch-php.sh << 'EOF'
#!/bin/bash
VERSION=$1
if [ -z "$VERSION" ]; then
    echo "用法: ./switch-php.sh [8.0|8.3]"
    exit 1
fi

if [ "$VERSION" = "8.0" ]; then
    brew unlink php@8.3
    brew link php@8.0 --force
elif [ "$VERSION" = "8.3" ]; then
    brew unlink php@8.0
    brew link php@8.3 --force
else
    echo "不支援的 PHP 版本"
    exit 1
fi

php --version
EOF

chmod +x switch-php.sh
```

**Step 4: 測試切換功能**

```bash
./switch-php.sh 8.3
./switch-php.sh 8.0
```

Expected: 成功切換並顯示對應版本

**Step 5: Commit**

```bash
git add switch-php.sh
git commit -m "chore(prepare): add PHP version switcher script"
```

---

## Task 2: 建立 Models Namespace 自動遷移腳本

**Files:**
- Create: `scripts/migrate-models-namespace.sh`

**Step 1: 創建遷移腳本**

```bash
cat > scripts/migrate-models-namespace.sh << 'EOF'
#!/bin/bash
set -e  # 遇到錯誤立即退出

echo "🚀 開始 Models Namespace 遷移..."

# 備份
echo "📦 建立備份..."
BACKUP_FILE="backup-before-model-migration-$(date +%Y%m%d-%H%M%S).tar.gz"
cd api
tar -czf "../$BACKUP_FILE" app/
cd ..
echo "✅ 備份已儲存至: $BACKUP_FILE"

# 移動目錄
echo "📁 移動 app/Model -> app/Models..."
cd api
if [ -d "app/Model" ]; then
    mv app/Model app/Models
    echo "✅ 目錄已移動"
else
    echo "⚠️  app/Model 目錄不存在，跳過"
    cd ..
    exit 0
fi

# 更新 namespace
echo "🔧 更新 namespace..."
find app -name "*.php" -type f -exec sed -i '' 's/namespace App\\Model;/namespace App\\Models;/g' {} +
echo "✅ Namespace 已更新"

# 更新 use 語句
echo "🔧 更新 use 語句..."
find app routes config database -name "*.php" -type f -exec sed -i '' 's/use App\\Model\\/use App\\Models\\/g' {} + 2>/dev/null || true
echo "✅ Use 語句已更新"

# 重新生成 autoload
echo "🔄 重新生成 autoload..."
composer dump-autoload
echo "✅ Autoload 已重新生成"

# 檢查殘留
echo "🔍 檢查殘留的舊 namespace..."
OLD_NAMESPACE_COUNT=$(grep -r "App\\\\Model" app/ routes/ config/ 2>/dev/null | wc -l | tr -d ' ' || echo "0")
if [ "$OLD_NAMESPACE_COUNT" -gt 0 ]; then
    echo "⚠️  發現 $OLD_NAMESPACE_COUNT 處仍使用舊 namespace："
    grep -rn "App\\\\Model" app/ routes/ config/ --color 2>/dev/null || true
    echo ""
    echo "⚠️  請手動檢查並修復"
else
    echo "✅ 未發現舊 namespace"
fi

cd ..
echo "✨ Models Namespace 遷移完成！"
echo "📝 請執行測試確認功能正常"
EOF

chmod +x scripts/migrate-models-namespace.sh
```

**Step 2: 測試腳本（dry run）**

```bash
# 創建測試環境檢查腳本
cat scripts/migrate-models-namespace.sh | grep -E "echo|if|find" | head -10
```

Expected: 顯示腳本邏輯無語法錯誤

**Step 3: Commit**

```bash
git add scripts/migrate-models-namespace.sh
git commit -m "feat(prepare): add models namespace migration script"
```

---

## Task 3: 建立升級驗證腳本

**Files:**
- Create: `scripts/verify-upgrade.sh`

**Step 1: 創建驗證腳本**

```bash
cat > scripts/verify-upgrade.sh << 'EOF'
#!/bin/bash

EXPECTED_VERSION=$1

if [ -z "$EXPECTED_VERSION" ]; then
    echo "用法: ./verify-upgrade.sh <expected-version>"
    echo "範例: ./verify-upgrade.sh 8"
    exit 1
fi

cd api

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

# 檢查 logs
echo "🔍 檢查最近的錯誤..."
if [ -f "storage/logs/laravel.log" ]; then
    ERROR_COUNT=$(grep -c "ERROR" storage/logs/laravel.log 2>/dev/null || echo "0")
    if [ "$ERROR_COUNT" -gt 0 ]; then
        echo "⚠️  發現 $ERROR_COUNT 個 ERROR（可能是舊的）"
        echo "最近 5 個錯誤："
        grep "ERROR" storage/logs/laravel.log | tail -5
    else
        echo "✅ 無錯誤日誌"
    fi
fi

cd ..
echo "✨ 驗證完成！"
EOF

chmod +x scripts/verify-upgrade.sh
```

**Step 2: 測試驗證腳本**

```bash
cd .worktrees/upgrade-prepare
./scripts/verify-upgrade.sh 7
```

Expected: 驗證通過，顯示 Laravel 7.x

**Step 3: Commit**

```bash
git add scripts/verify-upgrade.sh
git commit -m "feat(prepare): add Laravel upgrade verification script"
```

---

## Task 4: 匯出 API 端點基準清單

**Files:**
- Create: `api/docs/baseline-routes.txt`
- Create: `api/docs/baseline-routes.json`

**Step 1: 創建 docs 目錄**

```bash
mkdir -p api/docs
```

**Step 2: 匯出路由清單（文字格式）**

```bash
cd api
php artisan route:list > docs/baseline-routes.txt
```

Expected: 生成包含所有路由的文字檔案

**Step 3: 匯出路由清單（JSON 格式）**

```bash
php artisan route:list --json > docs/baseline-routes.json
```

Expected: 生成 JSON 格式的路由清單

**Step 4: 驗證匯出內容**

```bash
# 檢查路由數量
wc -l docs/baseline-routes.txt
cat docs/baseline-routes.json | grep -c '"uri"'
```

Expected: 顯示相同數量的路由

**Step 5: Commit**

```bash
git add docs/baseline-routes.txt docs/baseline-routes.json
git commit -m "docs(prepare): export API routes baseline for upgrade comparison"
```

---

## Task 5: 建立支付通道清單

**Files:**
- Create: `api/docs/payment-channels.json`
- Create: `api/docs/payment-channels-test-checklist.md`

**Step 1: 匯出支付通道資料**

```bash
cd api
php artisan tinker --execute="
echo json_encode(
    App\\Model\\Channel::all(['id', 'name', 'code', 'is_active'])
        ->toArray(),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
);
" > docs/payment-channels.json
```

Expected: 生成包含所有通道的 JSON 檔案

**Step 2: 建立測試清單模板**

```bash
cat > api/docs/payment-channels-test-checklist.md << 'EOF'
# 支付通道升級測試清單

## 測試說明
每次升級 Laravel 版本後，需要驗證以下通道的基本功能。

## 優先測試通道（前 20 個常用）

根據 `payment-channels.json` 選擇交易量最大的 20 個通道：

### Channel ID: [自動填入]
- [ ] 通道名稱：
- [ ] 狀態：Active/Inactive
- [ ] 存款測試：通過 / 失敗 / 跳過
- [ ] 提款測試：通過 / 失敗 / 跳過
- [ ] 回調測試：通過 / 失敗 / 跳過
- [ ] 備註：

---

## 測試記錄

### Laravel 7 基準測試
- 測試日期：2026-01-18
- 測試者：
- 通過數量：
- 失敗數量：

### Laravel 8 升級後
- 測試日期：
- 測試者：
- 通過數量：
- 失敗數量：
- 問題記錄：

### Laravel 9 升級後
- 測試日期：
- 測試者：
- 通過數量：
- 失敗數量：
- 問題記錄：

### Laravel 10 升級後
- 測試日期：
- 測試者：
- 通過數量：
- 失敗數量：
- 問題記錄：

### Laravel 11 升級後
- 測試日期：
- 測試者：
- 通過數量：
- 失敗數量：
- 問題記錄：
EOF
```

**Step 3: 驗證檔案生成**

```bash
cat docs/payment-channels.json | head -20
cat docs/payment-channels-test-checklist.md | head -30
```

Expected: 顯示檔案內容正確

**Step 4: Commit**

```bash
git add docs/payment-channels.json docs/payment-channels-test-checklist.md
git commit -m "docs(prepare): add payment channels baseline and test checklist"
```

---

## Task 6: 建立剩餘 Worktrees

**Files:**
- Create worktrees for each upgrade phase

**Step 1: 建立 Laravel 8 worktree**

```bash
cd /Users/apple/projects/morgan/ustd
git worktree add .worktrees/upgrade-laravel-8 -b upgrade/laravel-8
```

Expected: Worktree 建立成功

**Step 2: 建立 Laravel 9 worktree**

```bash
git worktree add .worktrees/upgrade-laravel-9 -b upgrade/laravel-9
```

**Step 3: 建立 Laravel 10 worktree**

```bash
git worktree add .worktrees/upgrade-laravel-10 -b upgrade/laravel-10
```

**Step 4: 建立 Laravel 11 worktree**

```bash
git worktree add .worktrees/upgrade-laravel-11 -b upgrade/laravel-11
```

**Step 5: 建立 PHP 8.3 worktree**

```bash
git worktree add .worktrees/upgrade-php-8.3 -b upgrade/php-8.3
```

**Step 6: 建立 cleanup worktree**

```bash
git worktree add .worktrees/upgrade-cleanup -b upgrade/cleanup
```

**Step 7: 驗證所有 worktrees**

```bash
git worktree list
```

Expected: 顯示 7 個 worktrees（prepare + 6 個升級階段）

**Step 8: 記錄 worktree 結構**

```bash
cat > docs/worktree-structure.md << 'EOF'
# Git Worktree 結構

## Worktree 列表

1. **upgrade/prepare** - `.worktrees/upgrade-prepare`
   - 準備階段：環境設置、腳本準備、基準測試

2. **upgrade/laravel-8** - `.worktrees/upgrade-laravel-8`
   - Laravel 7 → 8 升級

3. **upgrade/laravel-9** - `.worktrees/upgrade-laravel-9`
   - Laravel 8 → 9 升級

4. **upgrade/laravel-10** - `.worktrees/upgrade-laravel-10`
   - Laravel 9 → 10 升級

5. **upgrade/laravel-11** - `.worktrees/upgrade-laravel-11`
   - Laravel 10 → 11 升級

6. **upgrade/php-8.3** - `.worktrees/upgrade-php-8.3`
   - PHP 8.0 → 8.3 升級

7. **upgrade/cleanup** - `.worktrees/upgrade-cleanup`
   - 依賴清理與代碼現代化

## 工作流程

每個階段：
1. 在對應 worktree 工作
2. 完成後 commit
3. 下一階段 merge 前一階段的變更
4. 驗證功能正常
5. 繼續下一階段

## 清理指令

```bash
# 移除所有 worktrees（完成後）
git worktree remove .worktrees/upgrade-prepare
git worktree remove .worktrees/upgrade-laravel-8
# ... 等等
```
EOF
```

**Step 9: Commit**

```bash
git add docs/worktree-structure.md
git commit -m "docs(prepare): document git worktree structure"
```

---

## Task 7: 建立依賴分析文檔

**Files:**
- Create: `api/docs/dependencies-analysis.md`

**Step 1: 分析當前依賴**

```bash
cd api
composer show --tree > docs/dependencies-tree.txt
composer outdated > docs/dependencies-outdated.txt 2>&1 || true
```

**Step 2: 建立分析文檔**

```bash
cat > docs/dependencies-analysis.md << 'EOF'
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

EOF
```

**Step 3: Commit**

```bash
git add docs/dependencies-*.txt docs/dependencies-analysis.md
git commit -m "docs(prepare): add dependencies analysis and upgrade strategy"
```

---

## Task 8: 合併準備工作到主分支

**Files:**
- Merge all preparation commits

**Step 1: 檢查準備分支狀態**

```bash
cd /Users/apple/projects/morgan/ustd/.worktrees/upgrade-prepare
git status
git log --oneline | head -10
```

Expected: 顯示所有準備工作的 commits

**Step 2: 切換到主專案並合併**

```bash
cd /Users/apple/projects/morgan/ustd
git checkout master
git merge upgrade/prepare --no-ff -m "chore: merge phase 0 preparation work"
```

Expected: 合併成功，無衝突

**Step 3: 驗證合併結果**

```bash
ls -la scripts/
ls -la api/docs/
git log --oneline | head -10
```

Expected: 所有腳本和文檔都已合併

**Step 4: 推送到遠端（如果有）**

```bash
# 如果有遠端倉庫
git push origin master
git push origin upgrade/prepare
```

---

## 完成檢查清單

### 環境準備
- [ ] PHP 8.3 已安裝
- [ ] PHP 版本切換腳本可用
- [ ] 所有 worktrees 已建立

### 腳本準備
- [ ] Models namespace 遷移腳本
- [ ] 升級驗證腳本
- [ ] 所有腳本已測試可執行

### 基準資料
- [ ] API 路由清單已匯出
- [ ] 支付通道清單已匯出
- [ ] 測試檢查清單已建立
- [ ] 依賴分析文檔已完成

### Git 工作流程
- [ ] 所有變更已 commit
- [ ] 準備分支已合併到 master
- [ ] Worktree 結構文檔已建立

### 下一步
- [ ] 準備開始 Phase 1: Laravel 7 → 8 升級
- [ ] 閱讀 Laravel 8 升級指南
- [ ] 準備 Laravel 8 實作計劃

---

**預估完成時間：** 2-3 小時  
**實際完成時間：** ___________  
**遇到的問題：** ___________  
**解決方案：** ___________
