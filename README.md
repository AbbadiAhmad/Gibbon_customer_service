# Customer Service - a service desk module for Gibbon

Customers (students, parents, staff) raise **requests** through forms; the request shows up as a
ticket on the **boards** of the support teams, where the L1 team triages it and moves it forward.
Similar in spirit to the basic functions of Jira Service Management.

Module name / folder: `CustomerService` (namespace `Gibbon\Module\CustomerService`).

## Install

1. Copy (or clone) this repository to `<gibbon>/modules/CustomerService`.
2. In Gibbon go to **System Admin > Manage Modules**, find *CustomerService* under "Not Installed" and install it.
3. **User Admin > Manage Permissions**: review which Gibbon roles can use the module's actions
   (end-user actions are enabled for every role by default, configuration actions for Administrators).
4. Reload Gibbon (Gibbon caches the menu per session).

Requires Gibbon v28+ and MySQL 5.7+ / MariaDB 10.2+ (JSON functions are used by board filters).

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

## Not implemented yet

Notifications/emails, attachments, SLAs, drag-and-drop on boards (cards are moved with a dropdown), request
on behalf of another user, per-type workflows.

## License

GNU GPL v3 or later, like Gibbon.
