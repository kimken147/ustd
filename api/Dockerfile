# Dockerfile
FROM php:8.0-fpm

# 設定環境變數
ENV TZ=UTC \
    DEBIAN_FRONTEND=noninteractive \
    PHP_OPCACHE_ENABLE=1 \
    PHP_OPCACHE_ENABLE_CLI=0 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=1 \
    PHP_OPCACHE_REVALIDATE_FREQ=1 \
    HIGH_PRIORITY_QUEUE=hpq \
    MEDIUM_PRIORITY_QUEUE=mpq \
    S3_BUCKET=ark-api-env

# 安裝系統套件
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    supervisor \
    default-mysql-client \
    libpq-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libmcrypt-dev \
    net-tools \
    iproute2 \
    procps \
    lsof \
    telnet \
    iputils-ping \
    nginx \
    python3 \
    python3-pip \
    && rm -rf /var/lib/apt/lists/*

# 配置並安裝 PHP 擴展
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    mysqli \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    opcache

# 安裝 Redis 擴展
RUN pecl install redis && docker-php-ext-enable redis

# 安裝 AWS CLI
RUN pip3 install --no-cache-dir awscli

# 安裝 Composer
COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

# 設定工作目錄
WORKDIR /var/www

# 創建必要的目錄結構
RUN mkdir -p \
    /var/www/bootstrap/cache \
    /var/www/storage/framework/sessions \
    /var/www/storage/framework/views \
    /var/www/storage/framework/cache \
    /var/www/storage/framework/cache/data \
    /var/www/storage/framework/testing \
    /var/www/storage/logs \
    /var/log/supervisor \
    /var/log/nginx \
    /var/log/php-fpm \
    /var/run/supervisor \
    /var/run/php-fpm

# 複製專案檔案
COPY composer.json composer.lock ./

# 設定初始權限
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www \
    && chmod -R 775 /var/www/storage \
    && chmod -R 775 /var/www/bootstrap/cache

# 切換到 www-data 用戶安裝基本依賴
USER www-data
RUN composer install --no-scripts --no-autoloader --no-interaction --no-dev

# 切換回 root 用戶
USER root

# 複製專案其餘檔案
COPY . .

# 複製配置文件
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/generate-php-fpm-config.sh /usr/local/bin/
COPY docker/docker-entrypoint.sh /usr/local/bin/
COPY docker/fetch-env.sh /usr/local/bin/

# 設定執行權限
RUN chmod +x /usr/local/bin/generate-php-fpm-config.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/fetch-env.sh

# 配置 PHP
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini

# Opcache 配置
RUN { \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=4000'; \
    echo 'opcache.revalidate_freq=2'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'opcache.enable_cli=1'; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini

# 最終權限設定
RUN mkdir -p /var/www/storage/logs \
    && touch /var/www/storage/logs/laravel.log \
    && chown -R www-data:www-data /var/www \
    && chown -R www-data:www-data /var/log/supervisor \
    && chown -R www-data:www-data /var/log/nginx \
    && chown -R www-data:www-data /var/log/php-fpm \
    && chown -R www-data:www-data /var/run/supervisor \
    && chown -R www-data:www-data /var/run/php-fpm \
    && chmod -R 755 /var/www \
    && chmod -R 775 /var/www/storage \
    && chmod -R 775 /var/www/bootstrap/cache \
    && chmod -R 775 /var/log/supervisor \
    && chmod -R 775 /var/log/nginx \
    && chmod -R 775 /var/log/php-fpm \
    && chmod -R 775 /var/run/supervisor \
    && chmod -R 775 /var/run/php-fpm \
    && chmod 664 /var/www/storage/logs/laravel.log

EXPOSE 9001

# 使用 entrypoint 腳本啟動服務
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]