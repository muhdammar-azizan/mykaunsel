#!/usr/bin/env bash
# Runs the whole test suite on a THROW-AWAY MySQL/MariaDB server and removes it afterwards. Nothing of your own databases is touched:
# it starts its own server on its own port with its own temporary data folder.
#
#   bash tool/test-on-mysql.sh                 # migrate:fresh --seed, then the whole suite
#   SUITE_ARGS="tests/Feature/Platform" bash tool/test-on-mysql.sh   # only some tests
#
# MYSQL_BIN is the folder with mysqld, mysql, mysqladmin and (MariaDB) mysql_install_db / (MySQL) mysqld --initialize.
# Default: XAMPP on Windows. Set it for another install, e.g. MYSQL_BIN=/usr/bin or /usr/local/mysql/bin.
# Do not run another test run in the same checkout at the same time: the tests share storage/framework/testing.
set -u

MYSQL_BIN="${MYSQL_BIN:-/c/xampp/mysql/bin}"
PORT="${MYSQL_TEST_PORT:-3399}"
DB="mykaunsel_test"
WORK="$(mktemp -d)"
DATA="$WORK/data"
SUITE_ARGS="${SUITE_ARGS:-}"
EXE=""; [ -x "$MYSQL_BIN/mysqld.exe" ] && EXE=".exe"

cleanup() {
  "$MYSQL_BIN/mysqladmin$EXE" --no-defaults -h127.0.0.1 -P"$PORT" -uroot shutdown >/dev/null 2>&1
  sleep 2
  rm -rf "$WORK"
}
trap cleanup EXIT

win() { command -v cygpath >/dev/null 2>&1 && cygpath -w "$1" || echo "$1"; }

mkdir -p "$DATA"
if [ -x "$MYSQL_BIN/mysql_install_db$EXE" ]; then
  "$MYSQL_BIN/mysql_install_db$EXE" --datadir="$(win "$DATA")" --password= >/dev/null 2>&1 || { echo "Could not create the temporary database folder"; exit 2; }
else
  "$MYSQL_BIN/mysqld$EXE" --no-defaults --initialize-insecure --datadir="$(win "$DATA")" >/dev/null 2>&1 || { echo "Could not create the temporary database folder"; exit 2; }
fi

"$MYSQL_BIN/mysqld$EXE" --no-defaults --datadir="$(win "$DATA")" --port="$PORT" --bind-address=127.0.0.1 \
  --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci >"$WORK/server.log" 2>&1 &

for i in $(seq 1 30); do
  "$MYSQL_BIN/mysql$EXE" --no-defaults -h127.0.0.1 -P"$PORT" -uroot -e "select 1" >/dev/null 2>&1 && break
  sleep 1
done
"$MYSQL_BIN/mysql$EXE" --no-defaults -h127.0.0.1 -P"$PORT" -uroot -e "create database $DB character set utf8mb4 collate utf8mb4_unicode_ci" || { echo "The temporary server did not start (see $WORK/server.log)"; exit 2; }
"$MYSQL_BIN/mysql$EXE" --no-defaults -h127.0.0.1 -P"$PORT" -uroot -e "select version() as server_version, @@sql_mode as sql_mode" 

export DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT="$PORT" DB_DATABASE="$DB" DB_USERNAME=root DB_PASSWORD=

echo "== 1. migrate:fresh --seed from an empty database (what a new server does, plus the demo data)"
APP_ENV=local php artisan migrate:fresh --seed --force || { echo "FAILED: migrate:fresh --seed"; exit 1; }

echo "== 2. the whole test suite on MySQL (this includes the parallel promo-code test, which is skipped on SQLite)"
# shellcheck disable=SC2086
php artisan test $SUITE_ARGS
exit $?
