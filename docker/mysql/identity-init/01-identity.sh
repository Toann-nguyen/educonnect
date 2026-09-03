#!/bin/bash
set -euo pipefail
mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS \`identity_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER IF NOT EXISTS 'identity_user'@'%' IDENTIFIED BY '${IDENTITY_DB_PASSWORD}';
    GRANT ALL PRIVILEGES ON \`identity_db\`.* TO 'identity_user'@'%';
    FLUSH PRIVILEGES;
EOSQL
echo "✅ identity_db ready"
