#!/bin/sh
set -eu

mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" <<'SQL'
CREATE DATABASE IF NOT EXISTS e_talent_core
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS e_talent_tests
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS e_talent_interviews
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS e_talent_evaluaciones_encuestas
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SQL

for schema in \
  e_talent_core \
  e_talent_tests \
  e_talent_interviews \
  e_talent_evaluaciones_encuestas
do
  mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" < "/workspace/database/${schema}.sql"
done
