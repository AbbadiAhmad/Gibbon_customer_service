---
name: gibbon-live-testing
description: How to build a real, throw-away Gibbon (core v31 + demo data + MariaDB + PHP server) in this environment and test the ServiceDesk module in a browser with Playwright - setup script, test users, what the scripts cover, how to read failures. Use to verify any change to pages, forms, queries, permissions or automation, and before claiming something works.
---

# Testing in a live Gibbon

`php -l` and reading code missed every real bug found so far (zero-filled id lookups, a container injection error,
a query-builder SQL error). A live run takes a few minutes and is the standard check for this module.

## Build the site (about 3 minutes the first time)
```sh
apt-get update && apt-get install -y --no-install-recommends mariadb-server php8.3-bcmath   # if missing
tests/live/setup.sh /tmp/servicedesk-live        # clones GibbonEdu/core, composer install, DB + demo data, PHP server
node tests/live/install.js                       # installs the module through System Admin > Manage Modules
```
* Site: `http://127.0.0.1:8088`, database `gibbon` on `127.0.0.1:3399` (root, no password):
  `mariadb -h127.0.0.1 -P3399 -uroot gibbon -e "..."`.
* Every demo user has the password `Test1234!`. Useful users: `192` administrator, `119` teacher (the flow test adds
  them to team Administration), `463` parent with two enrolled children, `427` another parent.
* The setup stretches the current school year and its last term to include today (the demo data is dated in the
  past; without it `isSchoolOpen` is false and the absence automation fails).
* PHP errors: `/tmp/servicedesk-live/php-server.log` (`grep "PHP "`). A warning about `gibbonPlannerEntry.gibbonSpaceID`
  comes from Gibbon's own demo database, not from the module.
* Playwright and Chromium are pre-installed (`require('playwright')` works anywhere; do not install them).

## After changing code
```sh
tests/live/sync.sh /tmp/servicedesk-live         # copy the module again (a symlink does not work) + empty the Twig cache
```
Re-run `setup.sh` + `install.js` only when `manifest.php` changed (tables, default data, actions): it rebuilds the
database. A CHANGEDB upgrade is tested by `onbehalf.js` step 0 when the site holds an older version. To re-run an upgrade on an
existing site, undo its effects by hand and set the version back (e.g. `DELETE` the new `gibbonAction` +
`gibbonPermission` rows, drop the new column, `UPDATE gibbonModule SET version='1.0.00' WHERE name='ServiceDesk'`).

## The test scripts (`tests/live/`)
| Script | Covers |
|---|---|
| `smoke.js` | every page as administrator, looking for PHP/SQL errors and Gibbon error boxes. One FAIL is expected: a stage id that belongs to another workflow (the page must answer "cannot be found") |
| `flow.js` | admin adds a team member; parent files an absence for two children; teacher comments (internal + public), assigns, sets priority, approves; automation records attendance and closes; parent sees the result without internal notes; per-ticket and personal notification settings; another parent is refused |
| `admin.js` | new ticket type gets the default workflow; fields (derived name, select, duplicate name); stage add/delete; staff request form; custom filter, save shared with a team, delete; type with tickets cannot be deleted; automation failure (weekend-only absence): ticket stays, internal log, warning; server-side required check |
| `notifications.js` | (after `flow.js`) entries on Gibbon's notification page, bell count, the link opens the ticket, a ticket for a team without members reaches the administrators |
| `onbehalf.js` | (after `flow.js`) updates the module through Manage Modules if the code is newer (CHANGEDB test), then: administrator creates a request on behalf of a parent; teacher without the permission is refused |
| `assignee.js` | (after `flow.js`) team default assignee: set, cleared, cleared by removing the member |
| `update.js` | updates the module through Manage Modules when the code is newer (runs CHANGEDB) |
| `comments.js` | (after `flow.js`) comment permissions: teacher "_my", administrator "_all", parent none; edit, visibility, delete, refusals, traces |
| `board.js` | (after `flow.js`) view switch and its per-person memory, status vs stage columns, real drag and drop (`locator.dragTo`), automation confirmation dialog, parent has no board, type colour |
| `mail-on.sh` + `smtp-sink.py` | starts a local SMTP sink and points Gibbon at it; sent emails land in `/tmp/servicedesk-mail.txt` (log in again after running it) |
| `shot.js <user> '<page&params>' <name>` | one screenshot into `tests/live/shots/` (tall viewport) |
| `lib.js` | `login(browser, username)`, `check(page, label)` (prints `ok`/`FAIL` with the error text found) |

After a run, confirm in the database what the browser cannot show, e.g.
`select gibbonPersonID,type,reason,context,date from gibbonAttendanceLogPerson where comment like 'Service Desk%'`
and the rows of `gibbonNotification`, `gibbonServiceDeskTicketSubscriber`.

## Writing a new check
* Forms: `page.fill('input[name=x]', v)`, `page.selectOption('select[name=x]', v)`, date inputs take `YYYY-MM-DD`,
  Yes/No toggles are clicked through `label[id^=toggle-label][for=<name>]`, submit with
  `Promise.all([page.waitForNavigation(), page.click('form#<id> button[type=submit]')])` and read the `return=` code
  from the URL.
* Module field inputs are named `field<gibbonServiceDeskFieldID>`.
* `check()` only knows the error patterns listed in `lib.js`: when a page shows a new kind of error, add its pattern.
* Look at a screenshot before saying a page looks right; use a tall viewport rather than `fullPage` (sticky submit
  rows look misplaced in stitched full-page screenshots).
* Write test files with the editor tool, not a shell heredoc, when they contain arrow functions and template strings.

## What a live run does not prove
Delivery through a real mail provider (only a local SMTP sink is used), other Gibbon versions, other languages, production data volumes. Say so.
