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
   * Create on behalf (`canCreateOnBehalf()` = strongest action on `request_new.php` is `New Request_onBehalf`):
     all active types and any active user as requester. The process script ignores a posted
     `gibbonPersonIDOnBehalf` unless the permission is held (answers `error0`), and validates the answers against what
     the *requester* may choose (their children), not the creator. The permission gives no access to tickets.
   * Comments (`canEditComment`, `canChangeCommentVisibility`): level from the action family (`_my` = the comment's
     author is the user, `_all` = any) **and** the ticket rules: ticket visible; internal notes only for `canManage`;
     visibility changes only for `canManage`; only types `Comment` / `Internal Note` (never log lines). The comment is
     always read together with its ticket (`CommentGateway::getCommentByTicket`). The edit process applies text and
     visibility separately, each only if its own permission covers the comment; changes by others, visibility changes
     and deletions write an `Internal Log` line.
   * Family (`isFamilyMember`): only for ticket types with `shareWithFamily = 'Y'`; the viewer shares a family with the
     requester **and has `childDataAccess = 'Y'`**. The same rule appears twice and must stay in step: in SQL for the
     list scope (`TicketGateway::queryTickets`) and in `GuardianGateway::isCoAdult` for single tickets and
     `selectCoAdultIDs` for notifications. `isRequesterSide` (requester or family) may comment, withdraw, reopen.
   * Withdraw / reopen (`canWithdraw`, `canReopen`): requester side only; the ticket type must allow it (`allowWithdraw`, `allowReopen`, read with the ticket in `getTicketByID`); withdraw while Open / In Progress and only if
     the workflow has a `withdrawn` stage; reopen for `REOPEN_DAYS` after `timestampClosed`. The confirmation page
     and its process script check the same functions; failing says "not found".
   * Delete (`canDelete`): the action *Delete Tickets* **and** `canView`. The retention script has no user: it only
     selects what `selectExpiredTickets` returns (closed, type with retention, old enough).
   * Move to another type (`move` action of `tickets_viewProcess.php`): `canManage` of the current ticket; the target
     must exist and be active; afterwards `canView` is re-checked for the redirect.
   * Teacher notices go to people who may not see the ticket: the text must contain no reason, no free text, no
     internal data (`RequestFields::summary` builds it from children, classes and dates only) and is sent through the
     notification centre only (`Notifier::notifyCentre`). Since 1.6.00 this is the script `TellTeachers`.
   * Search: a filter rule inside `queryTickets`, so it only narrows what the person may see. It searches public
     comments (`type IN ('Comment','Log')`) and never internal notes or internal log lines, otherwise a requester
     could probe them.
   * "Updated" mark: computed per person in `queryTickets` (4th argument = the ticket types the person manages); people
     who do not manage a ticket are compared against `timestampModifiedPublic`, so internal activity is not revealed to
     requesters even as a mark. Marking a ticket as seen on a GET (opening it) only changes the person's own state.
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
   * board: a dropped card posts ticket id + stage id to `tickets_boardProcess.php`, which applies the ticket-page
     rules again (`canManage`, stage belongs to the ticket's type) and rebuilds the return address from cleaned
     criteria. The `movable` flag and the board itself (agents only) are conveniences. Saving the last view on a GET
     is accepted: it only changes the user's own display preference.
   * filter audience: `isAgent()` (administrator, or member of a team with ticket types) decides which shared
     filters are offered - `Agents` or `Requesters`; everybody who is not an agent is a requester, whatever their
     Gibbon role. This is display only: the ticket query scope is what protects tickets.
   * filters: personal by default; only administrators may share; delete only own filters (shared: administrators).
   * team default assignee: must be a member of that team (else stored as empty); cleared when the member is removed.
   * admin forms: an id from the request is re-read and checked against its parent (field belongs to the type, stage
     belongs to the workflow, member row belongs to the team) before it is changed or deleted.

5. **Output**: Twig autoescapes. In PHP-built HTML (`DataTable` format callbacks, echo) wrap data in
   `htmlspecialchars()`. SQL values always through bindings.

6. **Automation scripts** are PHP files deployed with the module (`src/Automation/Scripts/`). Never add a way to
   type or upload code through the browser. A stage stores only the class name, validated against
   `AutomationRegistry` when saved. Scripts run with the database rights of Gibbon: they must check their inputs
   (the ticket's stored answers) and verify their result.

7. **Non-page files** (`cli_*.php`: first statement is the `PHP_SAPI` check; `hook_*.php`: named by a hook in `manifest.php`; both checked by `tests/static.php`): `src/` has `.htaccess` deny; templates and CSS hold no logic. A future CLI/cron script must
   exit unless `PHP_SAPI === 'cli'` before loading anything; a dashboard hook must check the action itself.

9. **Lessons from the audit (`docs/AUDIT.md`)** - check them for every new list, form and job:
   * a list query limits sort columns and page size itself (the browser can post both);
   * times and marks shown to a requester come from `timestampModifiedPublic` / `lastUpdate`, never from
     `timestampModified`, or internal work shows through;
   * anything a person can repeat freely needs a limit (20 self-service requests per hour);
   * log lines hold no personal data (addresses are masked) and are rate-limited (one per kind per hour, one per
     batch) - a log must not grow with every notification or every deleted ticket;
   * ids from the address are turned into numbers at the top of the page (`intval(...) ?: ''`);
   * folders that are not pages carry a `.htaccess` deny; a new top-level folder needs one too;
   * what must disappear with a ticket is deleted in `TicketService::deleteTickets`; what can be orphaned by a
     deleted person or ticket gets an entry in `TicketGateway::orphanChecks()` (the retention script runs them).
   Prove a rule with a probe in `tests/live/security.js`: edit the hidden fields of a real form (`tamper()` sets
   *every* input of that name - Gibbon forms can hold a field twice and the last one wins) and check the database.

When adding an entry point, copy the guard block of a similar file and add the rule to `docs/ARCHITECTURE.md`
section 3 if it is new. Verify with a second user in the live test (`tests/live/flow.js` logs in as another parent
and expects to be refused).
