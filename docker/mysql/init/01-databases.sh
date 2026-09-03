#!/bin/bash
# ============================================================
# 01-databases.sh — khởi tạo databases theo microservice split
# Chạy 1 lần khi volume MySQL được tạo mới (docker-entrypoint-initdb.d)
# DB: identity_db, school_db, finance_db (+ laravel cho default connection)
# ============================================================
set -euo pipefail

echo "🌱 Khởi tạo databases cho microservices..."

mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS \`laravel\`    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE DATABASE IF NOT EXISTS \`identity_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE DATABASE IF NOT EXISTS \`school_db\`  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE DATABASE IF NOT EXISTS \`finance_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

    -- Identity service user
    CREATE USER IF NOT EXISTS 'identity_user'@'%' IDENTIFIED BY '${IDENTITY_DB_PASSWORD}';
    GRANT ALL PRIVILEGES ON \`identity_db\`.* TO 'identity_user'@'%';

    -- School service user
    CREATE USER IF NOT EXISTS 'school_user'@'%' IDENTIFIED BY '${SCHOOL_DB_PASSWORD}';
    GRANT ALL PRIVILEGES ON \`school_db\`.* TO 'school_user'@'%';

    -- Finance service user
    CREATE USER IF NOT EXISTS 'finance_user'@'%' IDENTIFIED BY '${FINANCE_DB_PASSWORD}';
    GRANT ALL PRIVILEGES ON \`finance_db\`.* TO 'finance_user'@'%';

    FLUSH PRIVILEGES;
EOSQL

echo "✅ Databases sẵn sàng: laravel, identity_db, school_db, finance_db"
