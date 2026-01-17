#!/bin/bash
set -e

# 獲取系統總記憶體（KB）
TOTAL_MEM=$(grep MemTotal /proc/meminfo | awk '{print $2}')
# 轉換為 MB
TOTAL_MEM_MB=$((TOTAL_MEM / 1024))

# 預留 20% 給系統和其他服務
AVAILABLE_MEM_MB=$((TOTAL_MEM_MB * 80 / 100))

# 假設每個 PHP-FPM 進程使用 64MB
PROCESS_MEM_MB=64
MAX_CHILDREN=$((AVAILABLE_MEM_MB / PROCESS_MEM_MB))

# 計算其他參數
START_SERVERS=$((MAX_CHILDREN * 20 / 100))
MIN_SPARE_SERVERS=$((START_SERVERS * 50 / 100))
MAX_SPARE_SERVERS=$((START_SERVERS * 150 / 100))

# 生成配置文件
cat >/usr/local/etc/php-fpm.d/www.conf <<EOF
[www]
user = www-data
group = www-data
listen = 127.0.0.1:9000

pm = dynamic
pm.max_children = ${MAX_CHILDREN}
pm.start_servers = ${START_SERVERS}
pm.min_spare_servers = ${MIN_SPARE_SERVERS}
pm.max_spare_servers = ${MAX_SPARE_SERVERS}
pm.max_requests = 500

; 日誌配置
access.log = /var/log/php-fpm/access.log
access.format = "%R - %u %t \"%m %r%Q%q\" %s %f %{mili}d %{kilo}M %C%%"
catch_workers_output = yes
php_admin_value[error_log] = /var/log/php-fpm/error.log
php_admin_flag[log_errors] = on

; 性能調整
pm.process_idle_timeout = 10s
request_terminate_timeout = 60s

; 路徑配置
php_value[session.save_handler] = files
php_value[session.save_path] = /var/www/storage/framework/sessions
php_value[upload_tmp_dir] = /var/www/storage/framework/uploads

; 設定 Laravel 存儲目錄權限
php_admin_value[open_basedir] = /var/www:/tmp:/usr/local/lib/php:/proc
EOF