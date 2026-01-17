#!/bin/bash
VERSION=$1
if [ -z "$VERSION" ]; then
    echo "用法: ./switch-php.sh [8.0|8.3]"
    exit 1
fi

if [ "$VERSION" = "8.0" ]; then
    brew unlink php@8.3 2>/dev/null
    brew link php@8.0 --force
elif [ "$VERSION" = "8.3" ]; then
    brew unlink php@8.0 2>/dev/null
    brew link php@8.3 --force
else
    echo "不支援的 PHP 版本: $VERSION"
    exit 1
fi

echo "✅ PHP 已切換到:"
php --version
