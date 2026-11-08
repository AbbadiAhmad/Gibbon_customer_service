---
name: gibbon-access-control
description: Security and permission rules for every entry point of the administration Gibbon module - isActionAccessible, named actions, POST-only processes, CSRF, sanitising, ticket visibility, audience, internal data, downloads. Use whenever adding or reviewing a page, process script, download, hook or CLI script.
---

# Access control rules (use Gibbon's own functions)

Every file a browser can request must authorise itself. Checklist, in order:

1. **Gibbon role permission**: first executable check is `isActionAccessible($guid, $connection2, '/modules/administration/<page>.php')`
   (in this module: `csAccess($guid, $connection2, '<page>.php', '<Action name>')`).
   * Pages: on failure `$page->addError(__('You do not have access to this action.')); return;`
   * Process scripts: redirect with `return=error0`.
   * The check passes if *any* action lists the page (LIKE on `URLList`). For a page listed in several actions (the shared delete page)
     pass the **named action** (`$sub`) that owns the record - `csDeleteTarget()['action']`.
   * Every page and process script must be listed in an action's `URLList` in `manifest.php` (and in CHANGEDB for upgrades), otherwise
     nobody - or the wrong people - can reach it.
2. **Process scripts**: `csRequirePost($session)` right after requiring `moduleFunctions.php`; name them `...Process.php`/`..._process.php` so
   gibbon.php applies the CSRF token + nonce check; sanitise with `$container->get(Validator::class)->sanitize($_POST)`. Pages call
   `csSanitizeRequest($container)` for `$_GET`.
3. **Record-level rules on top of the role permission** (the action only says the page may be used):
   * Ticket: `csCanViewTicket()` (requester, admin, or member with `view_board` of a space whose active board filter matches the ticket).
     Deny with the same answer for "not found" and "not allowed" when linking (`link_add`).
   * Ticket changes: `csHasTicketPermission($container, $session, $ticket, Permissions::X, $isAdmin)` - the permission must be held in a space that shows the ticket.
   * Internal notes, internal events, their attachments, agent-only fields, subtasks, checklists, links, tags, watchers: only when
     `csCanSeeInternal()`; the requester never receives them (filter in the query, not only in the template).
   * Space management: global admin (`csIsAdmin()` = holds *Manage Ticket Types*) or the space permission `manage_members` / `manage_boards`;
     the space of a board/member is read from the stored record, never trusted from the request.
   * Quick create (`QuickCreate`): admin or `edit_ticket` in some space; the assignee must be in the people of the spaces where the user has `assign_ticket` (or the type's team) - validated in the service, not only in the form.
   * Forms: active (with an active ticket type) **and** allowed for the user (`FormAccess::allows`: role category OR specific role; nothing set = every registered user) - checked on the portal, when opening the form *and* in the submit script. Visibility belongs to the form, never to the ticket type.
   * IDs that arrive in the request (checklist, link, saved filter, attachment) are always re-read together with the owning ticket/user
     (`getForTicket`, `getOwned`, `removeLink($id, $ticketId)`).
4. **Submit scripts re-derive what the form allowed**: only the questions of the stored form are read and `FormMapper::map` re-checks every answer against the ticket field it is mapped to
   (options, dates, numbers); status, reporter, team and form come from the server, never from the request.
5. **Output**: escape with `htmlspecialchars` (names, titles, labels). Values going into SQL always use bindings - identifiers (field keys) are validated
   by `BoardFilter::isValidKey` before being used in a JSON path.
6. **Files**: uploads only through `FileUploader`; downloads only via `cs_attachment.php` (ticket access + internal visibility check, `isSafePath`,
   `Content-Disposition: attachment`, `nosniff`). Remove files when the ticket is deleted.
7. **Non-page files**: `hook_dashboard.php` exits unless Gibbon included it and the user holds the action; `cli_*.php` exit unless `PHP_SAPI === 'cli'` before
   loading anything; `src/` has an `.htaccess` deny.
8. **Defaults**: staff tools (Boards, configuration) are not granted to Student/Parent roles; configuration actions are Administrator-only.

When adding an entry point, copy the guard block of a similar file, then add a row to the access table in `docs/ARCHITECTURE.md` if it introduces a new rule.
