# Audit: speed, security, data retention (v1.8.00)

What was checked, how, what was found and what was changed. Everything here was measured or probed in a real Gibbon
v31 (PHP 8.3, MariaDB 10.11) built by `tests/live/setup.sh`; the scripts are in `tests/perf/` and `tests/live/`.

## 1. Speed with many tickets

**Test data** (`tests/perf/seed.sql`): 200 000 tickets (95 % closed, spread over three years), 1 000 000 history lines,
330 000 follower rows, 330 000 "seen" rows, 330 000 notifications. `tests/perf/measure.js` loads each page as a real
user and reads from MariaDB's query log what the page asked the database.

**Before**, every list, the board, the search and even the box on the home page read *all* tickets on every call, so
they slowed down in step with the number of closed tickets: 1-3 seconds, and a parent with 140 tickets of their own
waited as long as an administrator.

**What was changed**
1. *The page is found first, details are loaded for its tickets only.* Step 1 reads only the ticket table and the
   person's "seen" rows; step 2 loads names and labels for the 25 tickets of the page (`TicketGateway::queryTickets`).
2. *Two indexes that hold everything a list needs* (`listing`, `listingType`), so step 1 never touches the wide ticket
   rows with the form answers. Open tickets are reached through the stage, a single type through the type.
3. *Small tables are read first and turned into id lists* (stages for "status", the family, the teams, people matching
   a search word), so the ticket table is reached by index instead of joining and scanning.
4. *One search text per ticket* holding the form answers and the public comments, instead of looking through one
   million comments for every search.
5. *Lists no longer load the form answers and search text* of every ticket shown.

**Result** (database time and whole page time, 200 000 tickets)

| Page | Database before | Database after | Page before | Page after |
|---|---|---|---|---|
| admin: home page (sidebar box) | 1306 ms | 98 ms | 1625 ms | 477 ms |
| admin: list, default | 1822 ms | 525 ms | 2085 ms | 814 ms |
| admin: list, page 2 | 1419 ms | 452 ms | 1694 ms | 781 ms |
| admin: list, open tickets | 808 ms | 65 ms | 1121 ms | 437 ms |
| admin: list, closed tickets | 1217 ms | 527 ms | 1484 ms | 805 ms |
| admin: list, one type | 302 ms | 154 ms | 539 ms | 450 ms |
| admin: list, assigned to someone | 854 ms | 886 ms | 1122 ms | 1190 ms |
| admin: search one word | 2335 ms | 590 ms | 2655 ms | 930 ms |
| admin: search two words | 2737 ms | 500 ms | 2969 ms | 713 ms |
| admin: search a name | 2455 ms | 866 ms | 2805 ms | 1161 ms |
| admin: search nothing found | 2048 ms | 465 ms | 2316 ms | 698 ms |
| admin: board, all types | 715 ms | 115 ms | 946 ms | 396 ms |
| admin: board, one type | 193 ms | 53 ms | 525 ms | 361 ms |
| admin: one ticket | 1 ms | 1 ms | 233 ms | 221 ms |
| team member: home page (sidebar box) | 1659 ms | 91 ms | 2713 ms | 925 ms |
| team member: list, default | 1635 ms | 383 ms | 2341 ms | 880 ms |
| team member: list, assigned to me, open | 804 ms | 83 ms | 1705 ms | 538 ms |
| team member: search one word | 3108 ms | 621 ms | 3967 ms | 1384 ms |
| team member: board | 1394 ms | 248 ms | 2108 ms | 959 ms |
| parent: home page (sidebar box) | 770 ms | 22 ms | 1060 ms | 325 ms |
| parent: list, default | 880 ms | 22 ms | 1124 ms | 224 ms |
| parent: search one word | 1678 ms | 34 ms | 2143 ms | 265 ms |
| parent: new request page | 0 ms | 0 ms | 245 ms | 232 ms |

A page of this test site takes about 200 ms without any module work (see "one ticket"), so "page after" is mostly
Gibbon itself. What still grows with the number of tickets, and why:

* **The list of everything** (0.4-0.5 s at 200 000): counting and ordering all tickets "updated first" needs one pass
  over the index. Filters on open tickets, a type, or "assigned to me" stay fast because they read a small part.
* **Search** (0.5-0.9 s at 200 000): it finds words anywhere inside the text (also inside words, in any language), which
  no index can serve; it reads the search text of every ticket the person may see. For a requester this is a few rows.
* **"Assigned to" one person over all statuses** (0.9 s): 130 000 rows match. Use it together with a status.
* The numbers were taken with MariaDB's default memory (128 MB buffer pool); a production database is faster.

**If the number of closed tickets gets very large**: nothing breaks, the open work stays fast, the "all tickets" list
and the search slow down linearly (about 0.25 s per 100 000 tickets here). The remedy is retention (section 3): set a
retention time on ticket types whose old tickets are not needed, and schedule the script.

## 2. Security

**Probes** (`tests/live/security.js`, 46 checks, all pass): a parent, a parent of another family, a team member, a
teacher outside the teams, an administrator and a visitor without a login - editing the hidden fields of real forms,
changing addresses, posting without Gibbon's form token, sending markup and SQL fragments.

| Area | What was tried | Result |
|---|---|---|
| Script injection | `<script>`, `<img onerror>`, `<svg onload>` in subject, details, comments, filter name; markup in the search, stage, view and id parameters | Nothing runs for requester, team member or administrator on list, ticket, board, home box and Gibbon's notification page. Input is stripped of tags on the way in (Gibbon's validator) and escaped on the way out. |
| Other people's tickets | comment on, withdraw, move, delete, open a ticket of another family by changing the ticket number in a form or address | Refused; unchanged in the database. |
| Doing a team's job | requester posts stage / priority / assignee / "internal note" / "move to type" / board move | Refused; an "internal note" from a requester is stored as a normal comment. |
| Permissions | edit a comment, delete a ticket, share a filter, delete a shared or someone else's filter, request on behalf of someone, request a service not offered to the role, an absence for a child of another family | Refused. |
| Team limits | stage of another ticket type; assignee outside the teams | Refused. |
| Form token | post without Gibbon's token | Nothing changes. |
| Database | `' OR '1'='1`, `UNION SELECT`, `1 OR 1=1`, `) OR (1=1` in search, assignee, type, stage, priority, requester, filter id, ticket id | No error, nothing outside what the person may see. |
| Pages and files | administration pages as parent / team member; module pages without a login; the CLI script, hook, classes and process scripts requested directly | Refused / nothing revealed / nothing changed. |
| Internal work | what a requester can learn about internal notes | Not in the page, the search, the notifications, the "Updated" mark, nor (new) the "last update" time. |
| Flooding | 21 requests within an hour | The 21st is refused with a message. |

**Findings and what was done**

| # | Finding | Severity | Fixed in 1.8.00 |
|---|---|---|---|
| S1 | The browser can post its own sorting and page size to a Gibbon table. The ticket list accepted any column of a joined table as sort column (a team member could order tickets by, say, a requester's password hash and read something from the order) and any page size (all tickets in one page). | Medium (staff only for the first, anyone for the second) | Sort columns are limited to those the list offers; a page holds at most 500 tickets. |
| S2 | A requester saw the time of the last change including internal ones ("last update: 2 minutes ago" although nothing visible changed). | Low (reveals that the team worked on it) | Requesters see the time of the last change they can read; sorting uses the same time. |
| S3 | Nothing limited how many requests one person could send; a script or a stuck browser could flood a team with tickets and emails. | Medium | 20 self-service requests per person per hour (`TicketService::MAX_REQUESTS_PER_HOUR`); requests on behalf of others by permitted staff are not limited. |
| S4 | A failed email was written to Gibbon's log once per recipient, with the recipient's address in the message. With a broken mail server the log grows with every notification and collects addresses. | Low | At most one log line per kind of failure per hour; addresses are replaced by "[address]". |
| S5 | Ticket and comment numbers from the address were passed on as typed. No exploit was found (the database layer binds them, templates escape them), but they are now turned into numbers at the entry of every page. | Hardening | Done for all 22 places. |
| S6 | The folders `src`, `templates`, `tests`, `docs`, `i18n`, `.claude` are reachable by address when the repository is copied as it is. They hold no secrets and running them does nothing, but there is no reason to serve them. | Hardening | A `.htaccess` in each denies access (Apache). With nginx add a `location` rule, or do not copy `tests`, `docs` and `.claude` to the server. |

**Checked and left as it is**
* *PHP error text on direct requests.* If PHP's `display_errors` is on, requesting a page or class file directly
  (not through Gibbon) prints a PHP error with the server path - exactly as Gibbon's own files do. It is off on a
  production server; keep it off.
* *Saving a display preference on a GET request* (list/board, "seen"): it only changes the person's own view.
* *The "on behalf of" person list* loads every active user for staff with that permission (as Gibbon's own person
  pickers do); fine for thousands, slow for tens of thousands of users.
* *Automation scripts* run with Gibbon's database rights. They are files on the server; nobody can add or edit one
  through the browser, and a stage can only name a script that exists.
* Notifications by email cannot be recalled when a ticket is deleted.

## 3. Data retention and consistency

**What deleting a ticket removes** (by hand with *Delete Tickets*, or by the retention script): the ticket with its
form answers and search text, its history (comments, internal notes, log lines), follower rows, "seen" marks, and
every Gibbon notification about it (also the notices to teachers).

**What stays, by design**
* Changes the workflow made elsewhere in Gibbon: attendance rows written by "Record absence" (they carry the comment
  `Service Desk #<number>`), class enrolments changed by "Unenrol from classes".
* One line in Gibbon's log saying that tickets were deleted, when, by whom and their numbers - no content.
* Emails already sent.

**Checked at scale** (`tests/perf/`): retention on a type with 25 600 expired tickets.

| Check | Result |
|---|---|
| Speed | about 6 ms per ticket (5 000 tickets in 27 s, the rest in 97 s); a dry run takes 2 s |
| Log lines | 1 per 500 tickets (before: 1 per ticket) |
| Left-overs in the module's tables and in Gibbon's notifications | 0 |
| Open tickets, and tickets of types without retention | untouched |
| Second run | nothing to do |

**Findings and what was done**

| # | Finding | Fixed in 1.8.00 |
|---|---|---|
| R1 | Deleting one ticket read Gibbon's whole notification table (it has no fitting index): 0.3 s per ticket with 330 000 notifications, so a first retention run over thousands of tickets would take hours. | The script deletes in batches of 500 with one statement per table; one pass over the notifications per batch. `--max=` limits a run (default 20 000). |
| R2 | One log line per deleted ticket. | One per batch. |
| R3 | Rows can be left behind when a person or a ticket disappears by other means (a user deleted in Gibbon, a database tool): follower rows, "seen" marks, notification and view settings, team memberships, personal filters, a default assignee who left the team, an assignee who no longer exists. The demo data alone had 100 000 such rows after seeding. | The script now ends with a housekeeping step that removes them (`--dry-run` lists them first). |
| R4 | Tickets made before 1.5.00 had no search text. | The upgrade writes it for every ticket (10 s for 200 000 tickets). |

**To decide (not changed)**
* **Retention is off until you set it.** No ticket type has a retention time by default, so nothing is ever deleted and
  the tables grow. Set *Retention (days)* per ticket type (e.g. absences 400 days) and schedule
  `php <gibbon>/modules/ServiceDesk/cli_deleteExpiredTickets.php` nightly.
* **Open tickets never expire.** A ticket nobody closes stays for ever, retention or not. A rule such as "close tickets
  without activity for 90 days" would need building.
* **Tickets of people who left the school** stay until their retention time; there is no "delete everything about
  this person" function. *Delete Tickets* does it one ticket at a time; the search finds them by name.
* **Gibbon's own tables** grow too: `gibbonNotification` (Gibbon ships `cli/userAdmin_removeStaleNotifications.php`)
  and `gibbonLog`. This module now writes little to either.

## 4. How to repeat

```
tests/live/setup.sh /tmp/servicedesk-live && node tests/live/install.js && node tests/live/flow.js
mariadb -h127.0.0.1 -P3399 -uroot gibbon < tests/perf/seed.sql     # 200 000 tickets
node tests/perf/measure.js                                         # speed
node tests/live/security.js                                        # probes
php /tmp/servicedesk-live/core/modules/ServiceDesk/cli_deleteExpiredTickets.php --dry-run
```
