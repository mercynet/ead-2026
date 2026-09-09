#!/bin/sh

set -eu

valid_identifier() {
    case "$1" in
        ''|*[!A-Za-z0-9_]* )
            echo "Invalid database identifier supplied." >&2
            exit 1
            ;;
    esac
}

valid_identifier "$MYSQL_DATABASE"
valid_identifier "$DB_BOOTSTRAP_USERNAME"
valid_identifier "$DB_RUNTIME_USERNAME"
valid_identifier "$DB_MIGRATION_USERNAME"

escape_sql_string() {
    printf '%s' "$1" | sed "s/'/''/g"
}

runtime_password=$(escape_sql_string "$DB_RUNTIME_PASSWORD")
migration_password=$(escape_sql_string "$DB_MIGRATION_PASSWORD")
bootstrap_username=$(escape_sql_string "$DB_BOOTSTRAP_USERNAME")
runtime_username=$(escape_sql_string "$DB_RUNTIME_USERNAME")
migration_username=$(escape_sql_string "$DB_MIGRATION_USERNAME")

MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql \
    --protocol=socket \
    --socket=/var/run/mysqld/mysqld.sock \
    --user=root \
    --batch \
    --skip-column-names <<SQL
DROP USER IF EXISTS '${bootstrap_username}'@'%';
CREATE USER IF NOT EXISTS '${runtime_username}'@'%' IDENTIFIED BY '${runtime_password}';
ALTER USER '${runtime_username}'@'%' IDENTIFIED BY '${runtime_password}';
REVOKE ALL PRIVILEGES, GRANT OPTION FROM '${runtime_username}'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON \`${MYSQL_DATABASE}\`.* TO '${runtime_username}'@'%';
CREATE USER IF NOT EXISTS '${migration_username}'@'%' IDENTIFIED BY '${migration_password}';
ALTER USER '${migration_username}'@'%' IDENTIFIED BY '${migration_password}';
GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}\`.* TO '${migration_username}'@'%';
FLUSH PRIVILEGES;
SQL
