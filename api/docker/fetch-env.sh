#!/bin/bash
set -e

# 檢查必要的環境變數
if [ -z "$S3_BUCKET" ] || [ -z "$BRANCH" ]; then
    echo "Error: Required environment variables are not set"
    echo "Required: S3_BUCKET, BRANCH"
    exit 1
fi

# 嘗試從S3下載環境檔案
echo "Fetching environment file from S3..."
if aws s3 cp "s3://${S3_BUCKET}/.env.${BRANCH}" /var/www/.env; then
    echo "Successfully downloaded environment file"
    # 設定正確的權限
    chown www-data:www-data /var/www/.env
    chmod 640 /var/www/.env
else
    echo "Failed to download environment file"
    exit 1
fi
