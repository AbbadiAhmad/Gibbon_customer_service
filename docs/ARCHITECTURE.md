# Architecture and decisions

Developer overview of the administration module: what exists, how a request flows, why things are the way they are.
The user documentation (features, setup) is in the [README](../README.md); the rules to follow when changing the code are
the project skills in [`.claude/skills/`](../.claude/skills) (summarised in [`CLAUDE.md`](../CLAUDE.md)).

## Status - read this first

* Written against the Gibbon v31 core source and the Sepa module as reference.
* **Verified against a real MariaDB (`tests/`)**: the install (`manifest.php` tables + default data), the upgrade chain from the first
  release to the current one (schema identical to a fresh install), and the module's own gateways, filters, form configuration, workflow and
  automation engine on the default data (90 checks). Run them with a throw-away database: see `tests/bootstrap.php`.
* **Not verified: anything that needs Gibbon itself.** No page or process script has been rendered or submitted (forms, tables, CSRF,
  permissions, notifications, uploads, the module installer). Gibbon core could not be installed in the authoring environment
  (`composer install` was blocked), so expect small mismatches with Gibbon's form/table APIs on first install, and test each role.

## Version history

| Version | Content |
|---|---|
| 0.1.0 | Teams, ticket types + custom fields, spaces, boards (filters, status columns), roles + permissions, space members, request form, dashboard, ticket view, kanban board |
| 0.2.0 | Notifications, attachments, internal notes, per-type workflow, resolution note + reopen/confirm, auto-close CLI, ticket search + saved filters |
| 0.3.0 | Automation rules (modular actions), start/due dates + reminders, subtasks, checklists, ticket links, tags, watchers |
| 0.4.0 | Customer portal, request-form builder, request-type audience, agent field editing, access hardening |
| 0.5.0 | Tables renamed `csX` -> `gibbonCsX` (Gibbon convention) |
| 0.7.0 | Quick create (type, title, summary, assignee) as page, board box and dashboard link |
| 0.6.0 | Status categories (To Do / In Progress / Done) + status registry, default school data (space, teams, 6 request types, boards, rules), ticket tabs (Comments / History / All), DB tests |

## Domain model

```
Team ---< TeamMember >--- User
TicketType --- Team (default team)         TicketType ---< Field   (custom fields; showOnForm, required, order)
                                           TicketType: workflow (JSON), formConfig (JSON), audience, groupName, icon

Space ---< SpaceMember >--- User, Role     Role ---< RolePermission   (permission keys: Service/Permissions.php)
Space ---< Board ---< BoardColumn          Board: filter (JSON conditions)

Ticket (type, status, priority, reporter, assignee, team, customData JSON, dates, resolution, parent)
  ---< TicketActivity (comment | event; Public | Internal)  ---< TicketAttachment
  ---< Checklist ---< ChecklistItem          ---< TicketTag >--- Tag
  ---< TicketWatcher                         ---< TicketLink (FROM blocks|duplicates|relates TO)

Status (name, category To Do | In Progress | Done)   - registry; board columns and tickets refer to it by name
AutomationRule (trigger, conditions JSON, actions JSON, optional ticket type)
SavedFilter (per user ticket search)
```

All 21 tables are `gibbonCs...`; primary and foreign key columns keep the `cs` prefix (`csTicketID`) - only tables follow the
Gibbon convention. A ticket is **not** stored in a space: boards pull tickets by filter, which is what lets a ticket appear on
several boards and lets a board mix ticket types.

## Who can see / do what

1. **Gibbon role permission** (Manage Permissions) decides which *actions* (pages) a user has: `isActionAccessible()`.
2. **Global admin** = holds the *Manage Ticket Types* action (`csIsAdmin()`): sees every ticket and every space.
3. **Space roles**: a user has roles *in a space*; a role is a set of permissions (`view_board`, `comment`, `edit_ticket`,
   `transition_ticket`, `assign_ticket`, `delete_ticket`, `manage_boards`, `manage_members`).
4. **A ticket is visible** to its requester, to admins, and to members (with `view_board`) of a space with an active board whose
   filter matches the ticket (`csCanViewTicket`). A permission on a ticket (`csHasTicketPermission`) is held when the user has it in
   a space that shows the ticket.
5. **Internal notes / agent-only fields / subtasks / checklists / links / tags** are for people who can view the boards that
   show the ticket (`csCanSeeInternal`), never for the plain requester.

## Request flow

```
Portal (cs_portal.php) -> request form (cs_request_new.php, built by FormConfig) -> cs_request_newProcess.php
   -> ticket (status New) + activity "Request raised" + attachments
   -> Notifier::ticketCreated  -> AutomationEngine::fire('ticket_created')
Ticket page (cs_ticket_view.php) -> cs_ticket_process.php (update | comment | move | fields | reopen | confirm | delete)
                                 -> cs_ticket_extrasProcess.php (subtasks, checklists, tags, links, watchers)
Board (cs_board_view.php): card "Move to" form -> cs_ticket_process.php action=move
Quick create (agents): cs_ticket_quick.php / board box -> cs_ticket_quickProcess.php -> Service/QuickCreate::create (permission, assignee rules, ticket, history, notification, rules)
Cron: cli_reminders.php (due soon / overdue), cli_autoClose.php (Resolved -> Closed)
```

Status changes go through one closure in `cs_ticket_process.php` (`$changeStatus`): workflow check, comment/resolution
requirement, open-subtask and open-blocker guards, `Workflow::statusChanges()` bookkeeping, activity, notification, then the
`status_changed` trigger.

## File map

| Area | Files |
|---|---|
| Module definition | `manifest.php` (tables, seed data, actions, hooks), `CHANGEDB.php` (upgrades), `version.php`, `CHANGELOG.txt` |
| Shared helpers | `moduleFunctions.php` (URL/redirect, access, visibility, status options, delete targets, date helpers) |
| Quick create | `cs_ticket_quick(+Process)`, `Service/QuickCreate`, `csQuickCreateForm()` in `moduleFunctions.php` |
| End-user pages | `cs_portal`, `cs_request_new(+Process)`, `cs_dashboard`, `cs_ticket_view`, `cs_ticket_search`, `cs_attachment` |
| Staff pages | `cs_board_view`, `cs_ticket_process`, `cs_ticket_extrasProcess`, `cs_search_process` |
| Configuration | `cs_team_*`, `cs_type_*`, `cs_field_*`, `cs_form_*`, `cs_role_*`, `cs_space_*`, `cs_board_*`, `cs_rule_*`, `cs_delete(+_process)` |
| Dashboard hook / cron | `hook_dashboard.php`, `cli_reminders.php`, `cli_autoClose.php` |
| Data access | `src/Domain/*Gateway.php` (one per table group) |
| Logic without UI | `src/Service`: `BoardFilter` (conditions -> SQL), `Workflow`, `FormConfig`, `CustomFields`, `Permissions`, `Notifier`, `Attachments`, `FilterForm` |
| Defaults / tests | `src/Install/DefaultData.php` (the only description of the installed defaults), `tests/` (database tests) |
| Automation | `src/Automation`: `AutomationEngine`, `AutomationContext`, `ActionRegistry`, `BaseAction`, `ActionForm`, `Actions/*` |

## Key decisions

* **Boards are saved filters**, not containers. The filter language (`BoardFilter`) is shared by boards, automation conditions
  and (partly) search. A condition on a custom field a ticket's type lacks evaluates as "empty" and never errors.
* **Custom fields are stored as JSON** on the ticket (`customData`, keyed by `fieldKey`) and filtered with `JSON_EXTRACT`; the same
  key on several types is the same field for filters.
* **Status categories**: a status is just a name on the ticket; its category (To Do / In Progress / Done) comes from the registry
  `gibbonCsStatus`, shared by all boards. "Finished" is `category = Done` everywhere (SQL: `Workflow::DONE_SQL`, PHP: `Workflow::isDone()`),
  never a hard-coded list of names. Boards add statuses through their column editor.
* **Default data** is generated from one PHP description (`DefaultData`) into INSERT statements by `manifest.php`; icons are 3-byte
  symbols because Gibbon's DB connection is `utf8`. Upgrades never insert default data (existing installs keep their own).
* **Workflow** is optional JSON per type (allowed moves, comment-required statuses); Resolved/Closed always need a resolution note.
* **Automation actions are plug-ins**: one class per action in `src/Automation/Actions/`, discovered by scanning the folder; the
  rule editor form is generated from each action's `params()`. Depth and run counters stop loops.
* **Notifications** use Gibbon's `NotificationSender` (in-app + e-mail per user preference) and never block the request.
* **Attachments** use Gibbon's `FileUploader`; downloads go through `export.php` with a ticket access check.
* **Request form** = `FormConfig` (core fields in `formConfig` JSON + custom fields flagged `showOnForm`); the submit script
  re-derives the shown fields from the same config, so a hidden field can't be posted.

## Known gaps

E-mail-in channel, SLAs, knowledge base, request on behalf of someone, drag-and-drop on boards, reports/dashboards widgets,
@mentions, bulk actions, per-user board views. The "from status" of a transition is not available to rule conditions.
Core table/column names `cs...ID` are not Gibbon-style (`gibbonCs...ID`). Automated tests do not exist (the pure logic classes
are testable stand-alone; see the skill `customerservice-extend-module`).
