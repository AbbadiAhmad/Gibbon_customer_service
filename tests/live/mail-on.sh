#!/bin/sh
# Starts the SMTP sink and points the live test Gibbon at it, so emails can be read in /tmp/servicedesk-mail.txt.
# Usage: tests/live/mail-on.sh      (log in again afterwards: Gibbon reads mail settings into the session at login)
DIR="$(cd "$(dirname "$0")" && pwd)"
: > /tmp/servicedesk-mail.txt
nohup python3 "$DIR/smtp-sink.py" 2525 /tmp/servicedesk-mail.txt >/tmp/servicedesk-smtp.log 2>&1 &
mariadb -h127.0.0.1 -P3399 -uroot gibbon -e "
UPDATE gibbonSetting SET value='Y' WHERE name='enableMailerSMTP';
UPDATE gibbonSetting SET value='127.0.0.1' WHERE name='mailerSMTPHost';
UPDATE gibbonSetting SET value='2525' WHERE name='mailerSMTPPort';
UPDATE gibbonSetting SET value='none' WHERE name='mailerSMTPSecure';
UPDATE gibbonSetting SET value='office@school.test' WHERE name='organisationEmail';"
echo "mail goes to /tmp/servicedesk-mail.txt"
