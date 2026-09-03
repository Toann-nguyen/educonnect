#!/bin/bash
set -euo pipefail
mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS \`finance_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER IF NOT EXISTS 'finance_user'@'%' IDENTIFIED BY '${FINANCE_DB_PASSWORD}';
    GRANT ALL PRIVILEGES ON \`finance_db\`.* TO 'finance_user'@'%';
    FLUSH PRIVILEGES;
EOSQL
echo "✅ finance_db ready"
