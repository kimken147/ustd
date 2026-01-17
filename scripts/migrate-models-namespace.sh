#!/bin/bash
set -e

echo "🚀 開始 Models Namespace 遷移..."

BACKUP_FILE="backup-before-model-migration-$(date +%Y%m%d-%H%M%S).tar.gz"
cd api
tar -czf "../$BACKUP_FILE" app/
cd ..
echo "✅ 備份已儲存至: $BACKUP_FILE"

cd api
if [ -d "app/Model" ]; then
    mv app/Model app/Models
    echo "✅ 目錄已移動"
else
    echo "⚠️  app/Model 目錄不存在，跳過"
    cd ..
    exit 0
fi

find app -name "*.php" -type f -exec sed -i '' 's/namespace App\\Model;/namespace App\\Models;/g' {} +
echo "✅ Namespace 已更新"

find app routes config database -name "*.php" -type f -exec sed -i '' 's/use App\\Model\\/use App\\Models\\/g' {} + 2>/dev/null || true
echo "✅ Use 語句已更新"

composer dump-autoload
echo "✅ Autoload 已重新生成"

OLD_NAMESPACE_COUNT=$(grep -r "App\\\\Model" app/ routes/ config/ 2>/dev/null | wc -l | tr -d ' ' || echo "0")
if [ "$OLD_NAMESPACE_COUNT" -gt 0 ]; then
    echo "⚠️  發現 $OLD_NAMESPACE_COUNT 處仍使用舊 namespace"
    grep -rn "App\\\\Model" app/ routes/ config/ --color 2>/dev/null || true
else
    echo "✅ 未發現舊 namespace"
fi

cd ..
echo "✨ Models Namespace 遷移完成！"
