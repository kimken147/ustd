#!/bin/bash
set -e

# 執行獲取環境變數的腳本
/usr/local/bin/fetch-env.sh

# Create necessary directories
mkdir -p /var/www/storage/logs
mkdir -p /var/log/php-fpm
mkdir -p /var/log/nginx
mkdir -p /var/log/supervisor
mkdir -p /var/run/supervisor
mkdir -p /var/run/php-fpm

# Create log files if they don't exist
touch /var/www/storage/logs/laravel.log
touch /var/log/php-fpm/php-fpm.log
touch /var/log/php-fpm/access.log
touch /var/log/php-fpm/error.log

# Get host UID/GID (Elastic Beanstalk webapp user)
HOST_UID=$(stat -c %u /var/log/containers/laravel)
HOST_GID=$(stat -c %g /var/log/containers/laravel)

# If the UID is not 0 (root), assume it's webapp user
if [ $HOST_UID -ne 0 ]; then
    # Update www-data user to match webapp UID
    usermod -u $HOST_UID www-data
    groupmod -g $HOST_GID www-data
fi

# Set proper ownership
chown -R www-data:www-data /var/www
chown -R www-data:www-data /var/log/php-fpm
chown -R www-data:www-data /var/run/php-fpm
chown -R www-data:www-data /var/log/supervisor
chown -R www-data:www-data /var/run/supervisor

# Set directory permissions
chmod -R 755 /var/www
chmod -R 775 /var/www/storage
chmod -R 775 /var/www/bootstrap/cache
chmod 664 /var/www/storage/logs/laravel.log
chmod -R 775 /var/log/php-fpm
chmod -R 775 /var/run/php-fpm

# Generate PHP-FPM configuration
/usr/local/bin/generate-php-fpm-config.sh

# 執行 Composer 和 Laravel 命令
su www-data -s /bin/bash -c "cd /var/www && \
    composer dump-autoload --optimize --no-dev && \
    php artisan storage:link && \
    php artisan config:clear && \
    php artisan route:cache && \
    php artisan view:cache && \
    php artisan migrate --seed --force"

# Start Supervisor
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
