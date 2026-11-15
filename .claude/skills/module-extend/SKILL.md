---
name: module-extend
description: Checklist for adding a feature, page, field type, automation script, filter, setting or table to the ServiceDesk Gibbon module - where code goes, the schema/upgrade/docs steps, naming, how to verify. Use before any change to this repository.
---

# Extending ServiceDesk

Read `docs/ARCHITECTURE.md` first (use cases, model, access rules, file map, missing use cases M1-M15 that the owner
may ask for next). Follow `gibbon-module-conventions`, `gibbon-access-control`, and test with `gibbon-live-testing`.

## Where things go
| You are adding | Put it in |
|---|---|
| SQL | the matching `src/Domain/*Gateway.php` (new table -> new gateway) |
| A rule or multi-step change to a ticket | `src/Service/TicketService.php` (history + notification + automation happen there; process scripts call it) |
| Who may see/do something | `src/Service/TicketAccess.php` |
| A request-form field type | `RequestFields`: `TYPES`, `addToForm()`, `collect()` (validate!), `describe()`; the `type` enum of `gibbonServiceDeskField` (manifest + CHANGEDB); the type list in `types_field_edit.php` |
| An automation script | new class in `src/Automation/Scripts/` implementing `AutomationScript` - nothing else (copy `RecordAbsence.php`) |
| A filter criterion | `TicketFilters::clean()` + `apply()`, a rule in `TicketGateway::queryTickets()`, a row in the form of `tickets.php` |
| A permission variant of an existing page | a grouped action `<Name>_<suffix>` in `manifest.php` + CHANGEDB, a `can...()` method in `TicketAccess` using `getHighestGroupedAction` (see `canCreateOnBehalf`) |
| Something on the board | board section of `tickets.php` (columns, card data, limits), `templates/board.twig.html`, `js/board.js`, `tickets_boardProcess.php`; stage changes still go through `TicketService::changeStage()` |
| A per-person display preference | `ViewPreferenceGateway` (one row per person) |
| A default shared filter | `manifest.php` INSERT with `audience` (`Agents` / `Requesters`) and, for an upgrade, an INSERT in CHANGEDB |
| A rule shared between SQL and PHP (e.g. "who sees a ticket") | write it once as a gateway method and once in the list scope, name both in the skill `gibbon-access-control`, and test both (list and single ticket) in `tests/live/` |
| A dashboard tab | `hook_dashboard_*.php` + the `$hooks[]` entry in `manifest.php` and the same INSERT in CHANGEDB; logic in `src/Service/DashboardWidget.php` |
| A scheduled job | `cli_<name>.php` (CLI check first) + the service method it calls; document the cron line in `README.md` |
| Something that must disappear with a ticket | `TicketService::deleteTicket` (comments, subscribers, notifications whose text starts with `#<id> `) - a new table that refers to a ticket must be added there |
| A stage behaviour (flag) | a column on `gibbonServiceDeskStage` (manifest + CHANGEDB + copy in `StageGateway::copyDefaultWorkflow`), fields in `workflow_stage_edit.php` + its process, behaviour in `TicketService::changeStage` |
| A notification event | call `Notifier::notify()` from the `TicketService` method that makes the change |
| A page | `<area>_<verb>.php` + `<area>_<verb>Process.php`, and both in an action's `URLList` |
| A view with more than a few lines of HTML | `templates/<name>.twig.html` + classes in `css/module.css` |
| Default data | `manifest.php` INSERTs (explicit ids) - upgrades do not insert defaults |

## Change checklist
1. Schema change: `manifest.php` **and** a new `CHANGEDB.php` entry (never edit a released one; migrate data if
   columns change); bump `$version` (manifest) and `$moduleVersion` (version.php) together; add to `CHANGELOG.txt`.
2. New page/action: `manifest.php` `$actionRows` + `URLList`; for an upgrade also `INSERT gibbonAction` and
   `gibbonPermission` in CHANGEDB.
3. Guards on every entry point (`gibbon-access-control`), including re-validation in the process script.
4. User text through `__m()` (module) or `__()` (text core already has); then `php tests/make-pot.php`.
5. Ids: compare and key with `intval()` (zero-filled strings from the database).
6. Docs: `README.md` (feature), `docs/ARCHITECTURE.md` (model, rules, file map, test status), a row in
   `docs/DECISIONS.md` for every question you had to answer yourself, a row in `docs/ASSUMPTIONS.md` for every
   assumption. When a "missing use case" gets built, move it out of section 7.
7. Verify: `php tests/static.php`, then the live tests; add a step to `tests/live/flow.js` or `admin.js` for the new
   behaviour, and a pattern to `tests/static.php` for every mistake a live run uncovers. Report what was run.
8. New verified knowledge or pitfall -> add it to the matching skill in `.claude/skills/` (the owner asked for this).
9. Commit on the working branch with a message that says what and why; no pull request unless asked.

## Conventions worth keeping
* Keep it simple: the owner chose a small model on purpose (one list page, one workflow per type, no boards, no
  custom roles). Prefer a new ticket type / field / stage / script over new tables or pages.
* One path per change: stage changes only through `TicketService::changeStage()`, comments through `addComment()`,
  so history, notifications and automation stay consistent.
* A ticket keeps a snapshot of its form answers (`fields` JSON with `name, label, type, value, display`); scripts
  read `value` by field `name` via `AutomationContext::field()`. Changing a type's form never rewrites tickets.
* Stages reference their "next stage" by name within the workflow; renaming goes through
  `StageGateway::renameNextStage()`. Stage `category` (Open / In Progress / Closed) is what filters and colours use -
  never test for a stage name in code.
* The default workflow is the stages with no ticket type; it is copied on type creation, not linked.
* History line types: `Comment` and `Log` are public, `Internal Note` and `Internal Log` are for the team.
* Notification policy: person default (`gibbonServiceDeskNotificationPreference`, no row = both on) copied to
  `gibbonServiceDeskTicketSubscriber` by `SubscriberGateway::subscribe()` when the person becomes involved.
* Things that must not break the user's request (notifications, email) are caught and logged.
* Dates in the database are `Y-m-d` / `Y-m-d H:i:s`; show with `Format::date`, `Format::dateTimeReadable`,
  `Format::relativeTime`.
