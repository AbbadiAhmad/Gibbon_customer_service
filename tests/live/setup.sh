#!/bin/sh
# Builds a throw-away, real Gibbon (core + demo data) with this module copied in, for the live tests.
# Needs: git, php 8.1+ (pdo_mysql, gd, intl, zip, curl, mbstring, gettext, bcmath), composer, mariadb-server, node + playwright.
# Usage: tests/live/setup.sh [work directory]      (default: /tmp/servicedesk-live)
# Then:  node tests/live/install.js && node tests/live/flow.js && node tests/live/notifications.js && node tests/live/onbehalf.js && node tests/live/assignee.js && node tests/live/comments.js && node tests/live/admin.js && node tests/live/smoke.js
# After changing module code run tests/live/sync.sh [work directory] (copies the module again; no reinstall needed
# unless manifest.php changed - then run setup.sh again, it rebuilds the database).
set -e
WORK="${1:-/tmp/servicedesk-live}"
MODULE="$(cd "$(dirname "$0")/../.." && pwd)"
CORE="$WORK/core"
PORT_DB=3399
PORT_WEB=8088
DB="mariadb -h127.0.0.1 -P$PORT_DB -uroot"
mkdir -p "$WORK"

# 1. Gibbon core + its PHP libraries
[ -d "$CORE" ] || git clone --depth 1 https://github.com/GibbonEdu/core.git "$CORE"
[ -d "$CORE/vendor" ] || (cd "$CORE" && composer install --no-dev --no-interaction --no-progress)

# 2. Database server
if ! $DB -e "select 1" >/dev/null 2>&1; then
    [ -d "$WORK/db/data" ] || { mkdir -p "$WORK/db"; mariadb-install-db --user=root --datadir="$WORK/db/data" --auth-root-authentication-method=normal >/dev/null 2>&1; }
    nohup mariadbd --user=root --datadir="$WORK/db/data" --socket="$WORK/db/m.sock" --pid-file="$WORK/db/m.pid" --port=$PORT_DB --bind-address=127.0.0.1 >"$WORK/db/server.log" 2>&1 &
    for i in 1 2 3 4 5 6 7 8 9 10; do sleep 1; $DB -e "select 1" >/dev/null 2>&1 && break; done
fi

# 3. Schema + demo data (1000+ people: staff, parents with children, classes, attendance codes)
$DB -e "DROP DATABASE IF EXISTS gibbon; CREATE DATABASE gibbon CHARACTER SET utf8mb4"
$DB gibbon < "$CORE/gibbon.sql"
$DB --init-command="SET SESSION sql_mode=''" gibbon < "$CORE/gibbon_demo.sql"

cat > "$CORE/config.php" <<CONFIG
<?php
\$databaseServer = '127.0.0.1';
\$databasePort = '$PORT_DB';
\$databaseUsername = 'root';
\$databasePassword = '';
\$databaseName = 'gibbon';
\$guid = 'wocin5trm-vitd-iws-g2k-3huw6nzxtfp';
\$caching = 10;
\$allowImpersonateUser = [];
CONFIG

# 4. Settings, a school year and term that contain today, and one known password for everybody: Test1234!
HASH=$(php -r 'echo hash("sha256", "saltsaltsaltsaltsaltsa"."Test1234!");')
$DB gibbon -e "
UPDATE gibbonSetting SET value='$CORE' WHERE name='absolutePath';
UPDATE gibbonSetting SET value='http://127.0.0.1:$PORT_WEB' WHERE name='absoluteURL';
UPDATE gibbonSetting SET value='Test School' WHERE name='organisationName';
UPDATE gibbonSetting SET value='TS' WHERE name='organisationNameShort';
UPDATE gibbonSetting SET value='UTC' WHERE name='timezone';
UPDATE gibbonSchoolYear SET lastDay=DATE_ADD(CURDATE(), INTERVAL 1 YEAR) WHERE status='Current';
UPDATE gibbonSchoolYear SET firstDay=DATE_ADD(CURDATE(), INTERVAL 367 DAY), lastDay=DATE_ADD(CURDATE(), INTERVAL 2 YEAR) WHERE status='Upcoming';
UPDATE gibbonSchoolYearTerm SET lastDay=DATE_ADD(CURDATE(), INTERVAL 1 YEAR) WHERE gibbonSchoolYearID=(SELECT gibbonSchoolYearID FROM gibbonSchoolYear WHERE status='Current') ORDER BY lastDay DESC LIMIT 1;
UPDATE gibbonPerson SET passwordStrong='$HASH', passwordStrongSalt='saltsaltsaltsaltsaltsa', passwordForceReset='N', failCount=0 WHERE canLogin='Y';"
mkdir -p "$CORE/uploads" && chmod -R 777 "$CORE/uploads"

# 5. The module (a copy, not a symlink: process scripts load ../../gibbon.php relative to their real path)
"$MODULE/tests/live/sync.sh" "$WORK"

# 6. Web server
if ! curl -s -o /dev/null "http://127.0.0.1:$PORT_WEB/index.php"; then
    (cd "$CORE" && nohup php -S 127.0.0.1:$PORT_WEB -t . -d display_errors=1 -d error_reporting=E_ALL > "$WORK/php-server.log" 2>&1 &)
    sleep 2
fi

echo "Gibbon is on http://127.0.0.1:$PORT_WEB  (password of every user: Test1234!)"
echo "Users in the demo data: 192 = administrator, 119 = teacher, 463 = parent with two children, 427 = another parent"
echo "PHP errors: $WORK/php-server.log    Next: node tests/live/install.js"
