---
name: gibbon-access-control
description: Security and permission rules for every entry point of the ServiceDesk Gibbon module - isActionAccessible, POST-only process scripts, CSRF, sanitising, ticket visibility, internal notes, re-validating what forms limited, automation scripts. Use whenever adding or reviewing a page, process script, query that lists tickets, template or automation script.
---

# Access control rules (ServiceDesk)

Every file a browser can request authorises itself. In order:

1. **Gibbon action permission** - first executable statement:
   `isActionAccessible($guid, $connection2, '/modules/ServiceDesk/<page>.php')`.
   * Pages: `$page->addError(__('You do not have access to this action.')); return;`
   * Process scripts: redirect with `return=error0`, then `exit`.
   * Every page and process script must be in an action's `URLList` (`manifest.php`; for upgrades also `CHANGEDB.php`).
     `php tests/static.php` fails when one is missing.
   * Defaults: *New Request*, *Tickets*, *Notification Settings* for every role; *Manage Ticket Types*, *Manage
     Workflows*, *Manage Teams* for Administrator only.

2. **Process scripts**: named `...Process.php` (Gibbon then checks CSRF token + nonce), built only from
   `Gibbon\Forms\Form` posts, `$_POST = $container->get(Validator::class)->sanitize($_POST)` before use. Changes
   happen only in process scripts, never on a GET page.

3. **Record-level rules** (`Service/TicketAccess`) - the action only says the page may be used:
   * Administrator = holds *Manage Ticket Types* (`isAdmin()`): all tickets.
   * Team member = in an active team linked to the ticket's type (`canManage($ticket)`): stage, assignee, priority,
     internal notes.
   * Requester (`isRequester`): view, comment, own notification settings.
   * `canView = requester or canManage`. On the ticket page and in its process script, "not found" and "not allowed"
     give the same answer.
   * Lists: `TicketGateway::queryTickets($criteria, $personID, $isAdmin)` puts the scope in the SQL. Any new query
     that returns tickets must take the same two arguments. Saved filters only narrow.
   * Internal notes / internal log lines: excluded **in the query** (`CommentGateway::selectCommentsByTicket($id,
     $includeInternal)`), never only hidden in the template. Notifications about internal events go to team members
     only (`Notifier::notify(..., $internal = true)`). Priority is not shown to the requester.

4. **Process scripts re-derive what the form limited** - the form is a convenience, not a control:
   * request: the type must be active and offered to the user's role category; only the stored fields of that type
     are read; students must be the user's own children (`People::filterSelectableStudents`), classes must be classes
     of those children, select values must be among the options, dates must be real dates; required fields are
     checked server-side; stage, requester and school year come from the server.
   * ticket: the new stage must belong to the ticket's type; a new assignee must be in a team of the type; only team
     members may post an internal note (the posted `type` is ignored for requesters).
   * filters: personal by default; only administrators may share; delete only own filters (shared: administrators).
   * admin forms: an id from the request is re-read and checked against its parent (field belongs to the type, stage
     belongs to the workflow, member row belongs to the team) before it is changed or deleted.

5. **Output**: Twig autoescapes. In PHP-built HTML (`DataTable` format callbacks, echo) wrap data in
   `htmlspecialchars()`. SQL values always through bindings.

6. **Automation scripts** are PHP files deployed with the module (`src/Automation/Scripts/`). Never add a way to
   type or upload code through the browser. A stage stores only the class name, validated against
   `AutomationRegistry` when saved. Scripts run with the database rights of Gibbon: they must check their inputs
   (the ticket's stored answers) and verify their result.

7. **Non-page files**: `src/` has `.htaccess` deny; templates and CSS hold no logic. A future CLI/cron script must
   exit unless `PHP_SAPI === 'cli'` before loading anything; a dashboard hook must check the action itself.

When adding an entry point, copy the guard block of a similar file and add the rule to `docs/ARCHITECTURE.md`
section 3 if it is new. Verify with a second user in the live test (`tests/live/flow.js` logs in as another parent
and expects to be refused).
