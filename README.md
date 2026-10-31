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
* Auto-close: `php modules/CustomerService/cli_autoClose.php 14` closes tickets that stayed Resolved for 14 days
  (run it from cron).

## Search

**Search Tickets** searches title, description and reference (`CS-12`) with status / type / priority / assignee
filters, over the tickets you can see (your own + the boards of your spaces). Quick views (my open requests, assigned to
me, unassigned, all open, resolved) and **saved filters** (per user) are on the same page.

## Not implemented yet

E-mail channel, automation rules, SLAs, knowledge base, linked tickets, request on behalf of another user,
drag-and-drop on boards (cards are moved with a dropdown), reports.

## License

GNU GPL v3 or later, like Gibbon.
