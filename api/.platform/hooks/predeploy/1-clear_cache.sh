#!/bin/bash

# 切換到應用程序目錄
cd /var/app/staging

# 清理 Laravel 緩存
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# 如果需要，調整存儲目錄的權限
chmod -R 755 storage
chown -R webapp:webapp storage

# 如果有必要，可以添加刪除特定緩存目錄的命令
rm -rf storage/framework/cache/data/*

# 輸出一條消息到部署日誌
echo "Cache cleared and permissions updated"
