#!/bin/sh
set -e

# Thực hiện tối ưu hóa cấu hình Laravel khi chạy ở môi trường production
if [ "$APP_ENV" = "production" ]; then
    echo "🚀 Optimizing Laravel configuration for production..."
    php artisan optimize:clear
    php artisan config:cache
    php artisan route:cache
    if [ -d resources/views ]; then
        php artisan view:cache
    else
        echo "ℹ️ Skipping view:cache (no resources/views)"
    fi
    php artisan event:cache
else
    echo "🛠️ Clearing cache for development environment..."
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    php artisan event:clear
fi

# Thực thi lệnh chính của container (CMD ở Dockerfile, mặc định là php-fpm)
exec "$@"
