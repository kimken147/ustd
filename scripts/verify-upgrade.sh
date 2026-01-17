#!/bin/bash

EXPECTED_VERSION=$1

if [ -z "$EXPECTED_VERSION" ]; then
    echo "用法: ./verify-upgrade.sh <expected-version>"
    echo "範例: ./verify-upgrade.sh 8"
    exit 1
fi

cd api

echo "🔍 驗證 Laravel ${EXPECTED_VERSION} 升級..."

CURRENT_VERSION=$(php artisan --version | grep -oE '[0-9]+\.[0-9]+' | head -1)
MAJOR_VERSION=$(echo $CURRENT_VERSION | cut -d. -f1)

if [ "$MAJOR_VERSION" != "$EXPECTED_VERSION" ]; then
    echo "❌ 版本不符！期望: ${EXPECTED_VERSION}.x，實際: $CURRENT_VERSION"
    exit 1
fi

echo "✅ Laravel 版本正確: $CURRENT_VERSION"

echo "🔍 檢查 autoload..."
composer dump-autoload --optimize 2>&1 | grep -i error && {
    echo "❌ Autoload 有錯誤"
    exit 1
}
echo "✅ Autoload 正常"

echo "🔍 檢查基本指令..."
php artisan route:list > /dev/null 2>&1 || {
    echo "❌ route:list 失敗"
    exit 1
}
echo "✅ 路由正常"

if [ -f "storage/logs/laravel.log" ]; then
    ERROR_COUNT=$(grep -c "ERROR" storage/logs/laravel.log 2>/dev/null || echo "0")
    if [ "$ERROR_COUNT" -gt 0 ]; then
        echo "⚠️  發現 $ERROR_COUNT 個 ERROR（可能是舊的）"
    else
        echo "✅ 無錯誤日誌"
    fi
fi

cd ..
echo "✨ 驗證完成！"
