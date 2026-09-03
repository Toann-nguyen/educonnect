#!/bin/bash
set -euo pipefail
mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS \`school_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    CREATE USER IF NOT EXISTS 'school_user'@'%' IDENTIFIED BY '${SCHOOL_DB_PASSWORD}';
    GRANT ALL PRIVILEGES ON \`school_db\`.* TO 'school_user'@'%';
    FLUSH PRIVILEGES;
EOSQL
echo "✅ school_db ready"
