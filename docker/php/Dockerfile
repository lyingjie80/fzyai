FROM php:8.2-fpm

# 安装系统依赖
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install pdo_mysql mysqli zip mbstring exif pcntl bcmath opcache

# 安装 Redis 扩展
RUN pecl install redis && docker-php-ext-enable redis

# 安装 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 设置工作目录
WORKDIR /var/www

# 复制 PHP 配置
COPY php.ini /usr/local/etc/php/

# 创建日志目录
RUN mkdir -p /var/log/php && chown -R www-data:www-data /var/log/php

EXPOSE 9000
CMD ["php-fpm"]
