# Customer Service - a service desk module for Gibbon

Customers (students, parents, staff) raise **requests** through forms; the request shows up as a
ticket on the **boards** of the support teams, where the L1 team triages it and moves it forward.
Similar in spirit to the basic functions of Jira Service Management.

Module name / folder: `administration` (namespace `Gibbon\Module\administration`).

## Documentation

* [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) - data model, who can see what, request flow, file map, decisions, known gaps and the **verification status**.
* [`CLAUDE.md`](CLAUDE.md) and [`.claude/skills/`](.claude/skills) - the development rules (Gibbon module conventions, access control,
  how to extend this module, how to write automation actions), written down so every contributor and AI session follows the same rules.
* [`CHANGELOG.txt`](CHANGELOG.txt) - what changed per version.

## Install

1. Copy (or clone) this repository to `<gibbon>/modules/administration`.
2. In Gibbon go to **System Admin > Manage Modules**, find *administration* under "Not Installed" and install it.
3. **User Admin > Manage Permissions**: review which Gibbon roles can use the module's actions
   (end-user actions are enabled for every role by default, configuration actions for Administrators).
4. Reload Gibbon (Gibbon caches the menu per session).

Requires Gibbon v28+ and MySQL 5.7+ / MariaDB 10.2+ (JSON functions are used by board filters).

## Database

All 21 tables are prefixed `gibbonCs` (e.g. `gibbonCsTeam`, `gibbonCsTicket`, `gibbonCsTicketActivity`) and are created by `manifest.php`;
upgrades from 0.4.0 or older rename the old `cs...` tables in place (CHANGEDB 0.5.0).

## Concepts

| Concept | Where | What it is |
|---|---|---|
| Team | Manage Teams | Named group of users (e.g. *L1 Support*). A ticket type can route new tickets to a default team. |
| Ticket type | Manage Ticket Types | A kind of request (e.g. *Password reset*, *Bug*). Only active types can be requested. |
| Field | Manage Ticket Types > edit type | Form field of one type: short/long text, number, date, dropdown, checkboxes, radio, yes/no; optionally required. |
| Space | Manage Spaces | A working space (e.g. *IT*, *Administration*) that contains boards and members. |
| Role | Manage Roles | A named set of permissions (see below). |
| Member | Manage Spaces > edit space | A user with one or more roles **in a space**. |
| Board | Manage Spaces > edit space | A view on tickets defined by a *filter*, with ordered *status columns*. |

### Role permissions (per space)

`view_board`, `comment`, `edit_ticket` (title/priority/team), `transition_ticket` (move between columns),
`assign_ticket`, `delete_ticket`, `manage_boards`, `manage_members`.
A user with a role in a space only sees the boards of that space, and the tickets those boards' filters match.
Requesters can always see and comment on their own tickets.

Three roles are seeded: *Space Admin*, *Agent* (L1) and *Viewer*, plus a default team, ticket type, space and board.

### Board filters

A board shows the tickets that match **all** its conditions: `field  operator  value`.

* Core fields: `type` (name), `status`, `priority`, `team` (name), `assignee` / `reporter` (username), `title`.
* Any custom field by its key, e.g. `browser contains Firefox`. The same key on different ticket types is the same field for filters.
* Operators: is, is not, contains, does not contain, is empty, is set.
* **Tolerant by design:** if a ticket's type does not have the field, the value is simply empty. No error is raised;
  the condition just does not match (`is`/`contains`) or does match (`is not`/`is empty`).

### Board columns

One status per line, ordered. Moving a card into a column sets that status. New requests start as `New`.
Tickets whose status is not a column of the board appear in an extra "Other statuses" lane so they never vanish.

## Delegating space administration

*Manage Spaces* is only enabled for Administrators by default. Grant that action to other Gibbon roles
(User Admin > Manage Permissions) to let space admins work: they only see spaces where their role has
`manage_members` / `manage_boards`.

## Dashboard

* **My Dashboard**: tickets waiting for triage (status `New`) on your boards + all your own requests.
* A "Customer Requests" tab on the Staff, Student and Parent dashboards (hook) lists your open requests.

## Notifications

Gibbon notifications (and e-mail for users who enabled notification e-mails in their preferences) are sent for:
new request (assignee, else the team, else everybody who can work the boards that show it), status change
(requester + assignee), assignment (new assignee), public comments (requester/assignee, or the staff side when
the requester writes), and a reopened request. The person who made the change is never notified.

## Attachments

Up to 5 files on the request form and on every comment, stored with Gibbon's uploader (allowed file types follow
the Gibbon setting). Downloads go through an access check; attachments of internal notes are hidden from the requester.
Deleting a ticket removes its files.

## Internal notes

Anyone with the `comment` permission can write a comment as **Internal note**: highlighted, visible only to people
who can view the boards that show the ticket, never to the requester, and never notified to the requester.

## Workflow, resolution and reopen

* **Manage Ticket Types > edit type > Workflow**: `From: Target 1, Target 2` per line restricts the moves of that
  type (a status without a line can go anywhere) and `Comment Required For` lists statuses that need a comment.
* Moving a ticket to **Resolved** or **Closed** always needs a **resolution note** (entered on the ticket page; the
  board dropdown only offers moves that need no note). It is stored on the ticket and shown to the requester.
* The requester sees **Close Request** / **Reopen** on a Resolved ticket (and **Reopen** on a Closed one). Reopening sets
  the status back to `New`, clears the resolution and notifies the team.
* Auto-close: `php modules/administration/cli_autoClose.php 14` closes tickets that stayed Resolved for 14 days
  (run it from cron).

## Search

**Search Tickets** searches title, description and reference (`CS-12`) with status / type / priority / assignee
filters, over the tickets you can see (your own + the boards of your spaces). Quick views (my open requests, assigned to
me, unassigned, all open, resolved) and **saved filters** (per user) are on the same page.

## What a fresh installation contains

Everything below is created by the installer (`src/Install/DefaultData.php`) and can be changed or deleted in the module:

* **Space** *School Office* with **teams** *Front Office* (L1: triage, questions, absences), *Admissions* (registrations, leaving)
  and *Staff Services* (staff requests, materials). Add yourself and your colleagues in *Manage Teams* / *Manage Spaces*
  (Administrators see every space and board without being a member).
* **Request types** (portal groups *Families* and *Staff*, each with its own form and workflow):

  | Type | For | Form (besides summary / description) | Workflow |
  |---|---|---|---|
  | Absence Report | parents, staff | child, class, absent from/until, reason, phone, doctor's note | New -> In Progress -> Resolved |
  | School Registration | parents, staff | child, birth date, year, previous school, siblings, phone, desired start date, documents | New -> Documents Requested -> Interview Planned -> Resolved / Declined (Waiting in between) |
  | School Leaving | parents, staff | child, class, reason, new school, documents needed, last day at school | New -> In Progress -> Resolved (Waiting in between) |
  | General Question | everybody | topic, child | New -> In Progress -> Resolved (Waiting in between) |
  | Staff Request | staff | kind of request, from / needed by | New -> In Progress / Awaiting Approval -> Resolved / Declined |
  | Material Request | staff | item, quantity, room, budget code, needed by | New -> Awaiting Approval -> Ordered -> Resolved / Declined |

  Agent-only fields (e.g. "entered in the attendance register", "documents received", "fees settled") are on the ticket, not on the portal form.
  Registrations and leavings get a ready-made **checklist** (automation rules).
* **Boards**: *All requests* (every status), *Absences*, *Registrations*, *Leaving*, *Questions*, *Staff Services*.
* **Roles**: Space Admin, Agent, Viewer.

Upgrading an existing installation adds the status registry but not these defaults (your data stays as it is).

## Statuses and their categories

Every status belongs to one of three base categories: **To Do**, **In Progress** or **Done**. A board shows one column per status; you can add
your own statuses to a board (*Manage Spaces > board > Status Columns*) and give each a category. A status has the same category on every board
(it is kept in one registry), so changing the category of an existing status changes it everywhere. "Finished" always means category **Done**:
reminders stop, blockers and open subtasks are released, the requester may reopen it, and a resolution note is required to get there.

## Quick create

For agents who just need a ticket, without the request form: **Quick Create** (menu, dashboard link, and a "+ Quick create" box on every
board). Choose the **type**, give it a **title** and a short **summary** (it becomes the description) and optionally the **assignee**; press Create.

* Who: administrators, and anybody who has the `edit_ticket` permission in at least one space (the agents). The Quick Create action is staff-only by default.
* Assignee: the people of the spaces where you have `assign_ticket` (administrators: every space) and the members of the team of the chosen type.
  Without the permission the field is not shown. Leave it empty and the team of the type picks the ticket up.
* The ticket is a normal new request: status New, priority Medium, team of the type, you as the requester, history entry "Request raised (quick create)",
  notifications and automation rules as usual. Required custom fields of the type are not asked: complete them on the ticket.

## The ticket page

Like Jira: details and description on top, then three tabs - **Comments** (with the form to add one, public reply or internal note, with files),
**History** (every change with who and when: status, assignee, priority, team, dates, fields, tags, links, automation; old and new value) and
**All activity** (both together). Internal comments and internal history lines are only shown to the people working on the ticket.

## Customer portal and request forms (like Jira Service Management)

* **Customer Portal** is the first page of the module. It lists the request types as cards, grouped (IT, Facilities...), with a
  search box. Click a card to open its form; each form also has a direct link (shown on *Manage Ticket Types > Request Form*).
* **Request form builder** (*Manage Ticket Types > Request Form* icon): per request type, choose which basic fields are shown
  (Summary, Description, Priority, Start/Due date, Attachments), which are required, their labels and help text, and the order -
  mixed with the type's custom fields. A custom field can be set to *Hidden*: the requester never sees it, the agents fill it in
  on the ticket page (**Fields** form).
* **Who can raise it**: a request type can be limited to Staff, Students, Parents or Other users (Gibbon role categories);
  others neither see it on the portal nor can post to it.
* Portal group and icon are set on the request type.

## Security

Every page and process script starts with Gibbon's own `isActionAccessible()` check of its action (Manage Permissions decides
who gets which action), process scripts accept POST only (Gibbon's CSRF token + nonce check applies), requests are sanitised with
Gibbon's `Validator`, and on top of the role permission each ticket is only readable by its requester and by members of spaces
whose boards show it. The shared delete page requires the action that owns the record type (e.g. *Manage Spaces* for boards).
`src/` is blocked from direct web access, `hook_dashboard.php` refuses to run outside Gibbon and the cron scripts refuse to run
outside the command line. Boards are not offered to student / parent roles by default.

## Basic fields of every ticket type

Every type has these fields by default: **Title, Description, Priority, Start Date, Due Date, Attachments**. The fields you
define on a type (Manage Ticket Types) are extra fields on top of these.

## Due dates and reminders

* Start and due date are optional on the request form and editable by agents. **Overdue / Due today** markers show on
  boards, the dashboard, search results, subtasks and the ticket page.
* Run `php modules/administration/cli_reminders.php 1` once a day from cron: tickets due within N days (default 1) get one
  "due soon" reminder, overdue tickets one "overdue" notice. They go to whoever works on the ticket and to the watchers and
  fire the `ticket_due_soon` / `ticket_overdue` automation triggers. Changing the due date restarts the reminders.

## Subtasks and checklists

* **Subtask**: a full ticket (own status, assignee, due date) that belongs to a parent (one level). Added from the parent's page,
  shown on the parent with progress, and on boards with a "Subtask of CS-12" marker. A parent cannot be moved to Resolved / Closed
  while subtasks are open.
* **Checklist**: named lists of items inside a ticket, ticked off in place (several lists per ticket; items can be added or removed).
* Both are for the people working on the ticket; the requester does not see them.

## Linked tickets (dependencies)

*blocks / is blocked by*, *duplicates / is duplicated by*, *relates to*, entered by ticket reference (`CS-12`). A ticket that
is blocked by an unfinished ticket shows a banner, is flagged on boards, and cannot be resolved or closed until the blockers are done.
Links are only offered to tickets you can see.

## Tags

Free labels typed on the ticket (comma separated, created on the fly, case-insensitive). Filter by tag in Search, and use the
`Tag` condition in board filters and automation rules.

## Watchers

Anyone who can see a ticket can **Watch** it; agents can add or remove other watchers. Watchers get the status change, public
comment, reopen and due-date notifications, without owning the ticket.

## Automation (conditions and actions)

**Manage Automation** (Administrators): a rule is *trigger -> conditions -> actions*.

* **Triggers**: request raised, status changes, ticket assigned, public comment added, requester reopens, due date near, ticket overdue.
* **Conditions**: the same field / operator / value rows as board filters (all must match), including the date, overdue, blocked,
  subtask and tag fields. Optional ticket type: a rule can apply to one type or all.
* **Actions** (run in order): set priority, set status, assign to a person, route to a team, add a comment (public or internal),
  send a notification, add tags, set a due date, add a watcher, add a checklist.
* Everything an action does is written to the ticket activity as an *internal* "Automation" event. Rules can chain (a rule's
  status change fires `status_changed` for other rules) up to 3 levels, and one rule runs at most 3 times per ticket per request.
* Actions skip the workflow rules of the type (an automation may move a ticket anywhere).

### Writing a new action

Actions are plug-ins: add **one class file** to `src/Automation/Actions/` and it shows up in the rule editor, with the settings
you declare - no registration, no form code.

```php
namespace Gibbon\Module\administration\Automation\Actions;

use Gibbon\Module\administration\Automation\AutomationContext;
use Gibbon\Module\administration\Automation\BaseAction;

class SetPriorityUrgent extends BaseAction            // key is derived: set_priority_urgent
{
    public function label(): string { return __('Make urgent'); }

    public function params($container): array         // the settings the rule editor asks for (can be empty)
    {
        return ['reason' => ['label' => __('Reason'), 'type' => 'text', 'required' => true]];
        // types: text, textarea, number, select (with 'options'), person, team
    }

    public function execute(array $ticket, array $params, AutomationContext $ctx): void
    {
        $ctx->update(['priority' => 'Urgent'], sprintf(__('made urgent: %1$s'), $params['reason']));
        // other helpers: $ctx->changeStatus(), assign(), comment(), notify(), log(), fire(), ticket()
    }
}
```

An action whose class is removed later is skipped by existing rules (shown as "unavailable" in the rule list).
New **triggers** are one entry in `AutomationEngine::triggers()` plus a `->fire('your_trigger', $csTicketID)` call where it happens.

## Not implemented yet

E-mail channel, SLAs, knowledge base, request on behalf of another user, drag-and-drop on boards (cards are moved with a
dropdown), reports, @mentions, bulk actions.

## License

GNU GPL v3 or later, like Gibbon.
