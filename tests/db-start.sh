#!/bin/sh
# Starts a throw-away MariaDB for the database tests on 127.0.0.1:3399 and creates the test databases.
# Usage: tests/db-start.sh [data directory]   (default: /tmp/cs-test-mariadb; delete the directory to start from scratch)
DIR="${1:-/tmp/cs-test-mariadb}"
if mysql -h127.0.0.1 -P3399 -uroot -e "select 1" >/dev/null 2>&1; then echo "already running"; else
    [ -d "$DIR/data" ] || { mkdir -p "$DIR"; mariadb-install-db --user=root --datadir="$DIR/data" --auth-root-authentication-method=normal >/dev/null 2>&1; }
    nohup mariadbd --user=root --datadir="$DIR/data" --socket="$DIR/m.sock" --pid-file="$DIR/m.pid" --port=3399 --bind-address=127.0.0.1 >"$DIR/server.log" 2>&1 &
    for i in 1 2 3 4 5 6 7 8 9 10; do sleep 1; mysql -h127.0.0.1 -P3399 -uroot -e "select 1" >/dev/null 2>&1 && break; done
fi
for db in cs_test cs_chain cs_data; do mysql -h127.0.0.1 -P3399 -uroot -e "create database if not exists $db character set utf8mb4"; done
echo "ready on 127.0.0.1:3399 (databases cs_test, cs_chain, cs_data)"
