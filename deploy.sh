#!/bin/bash
set -euo pipefail

echo "🚀 Starting production deploy..."

# 1. Pull latest code
git pull origin main

# 2. Khởi tạo thư mục và phân quyền trên host để tránh lỗi permission denied khi php container ghi logs
echo "📁 Preparing storage logs directory..."
mkdir -p storage/logs
chmod -R 775 storage/logs

# 3. Build front-end assets bằng Node Docker container (không cần cài node/npm trên host)
echo "📦 Building front-end assets (Vite)..."
docker run --rm -v "$(pwd)":/app -w /app node:20-alpine sh -c "npm ci && npm run build"

# 4. Build production PHP image
echo "🐳 Building production PHP image..."
docker compose -f docker-compose.prod.yml --env-file .env.production build php

# 5. Run migrations (trước khi swap traffic)
echo "🗄️ Running database migrations..."
docker compose -f docker-compose.prod.yml --env-file .env.production run --rm php \
    php artisan migrate --force

# 6. Restart services (rolling — Nginx giữ traffic trong lúc PHP restart)
# LƯU Ý: Container mới khởi chạy sẽ tự động chạy docker-entrypoint.sh để cache config/route
echo "🔄 Restarting application services..."
docker compose -f docker-compose.prod.yml --env-file .env.production up -d --no-deps php
docker compose -f docker-compose.prod.yml --env-file .env.production up -d --no-deps horizon
docker compose -f docker-compose.prod.yml --env-file .env.production up -d --no-deps scheduler

# 7. Reload Nginx (không downtime)
echo "🌐 Reloading Nginx configuration..."
docker compose -f docker-compose.prod.yml --env-file .env.production exec nginx nginx -s reload

echo "✅ Deploy complete!"