
FROM composer:2.7 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./

# Install production deps only — no scripts yet (app not fully copied)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize --no-dev

FROM php:8.2-fpm-alpine AS production
# ============================================================
# Stage 1: Composer dependencies
# ============================================================
LABEL maintainer="educonnect"

# ── System dependencies ──────────────────────────────────────
RUN apk add --no-cache \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    libzip-dev \
    libxml2-dev \
    oniguruma-dev \
    icu-dev \
    linux-headers \
    $PHPIZE_DEPS

# ── PHP extensions ───────────────────────────────────────────
# Lưu ý: base php:8.2-fpm-alpine đã bundle sẵn mbstring, fileinfo, Zend OPcache
RUN docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j2 \
    pdo_mysql \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
    sockets \
    xml

# Redis (phpredis — không dùng predis)
RUN pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/pear

# ── PHP config ───────────────────────────────────────────────
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# ── Non-root user ────────────────────────────────────────────
RUN addgroup -g 1000 -S appgroup \
    && adduser -u 1000 -S appuser -G appgroup

# ── App files ────────────────────────────────────────────────
WORKDIR /var/www/html

COPY --chown=appuser:appgroup . .
COPY --chown=appuser:appgroup --from=vendor /app/vendor ./vendor

# Proper permissions
RUN chown -R appuser:appgroup storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Copy và thiết lập entrypoint script dưới quyền root
COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]

USER appuser

EXPOSE 9000

CMD ["php-fpm"]

# ============================================================
# Stage 2: Development — kế thừa production + Xdebug/pcov
# Dùng cho docker-compose.yml (dev): bind-mount source, debug IDE
# ============================================================
FROM production AS development

USER root

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && { \
         echo 'xdebug.mode=debug'; \
         echo 'xdebug.start_with_request=yes'; \
         echo 'xdebug.client_host=host.docker.internal'; \
         echo 'xdebug.client_port=9003'; \
         echo 'xdebug.log=/tmp/xdebug.log'; \
       } >> "$PHP_INI_DIR/conf.d/docker-php-ext-xdebug.ini"

USER appuser