# Architecture

## 1. Use cases and where they are built

| Use case (as requested) | How it works | Main files |
|---|---|---|
| Parent selects services regarding their children | Service catalog: cards of the ticket types offered to the user's role category; field type *Students* lists the parent's own children | `request_new.php`, `templates/catalog.twig.html`, `People` |
| Absence: choose children, absence type, period; administration records it | Type *Absence Report* (fields students, reason, dateStart, dateEnd, details). Team moves it to *Approved*; the automation writes Attendance | `manifest.php` defaults, `RecordAbsence` |
| Unenrolment from a course: choose children/class; team updates finance and enrolment | Type *Course Unenrolment*; field type *Classes* lists the current classes of the parent's children. Team works it by hand (script *Unenrol from classes* available) | `RequestFields`, `UnenrolFromClasses` |
| De-registration: choose children, details; team updates finance, enrolment, deactivates accounts | Type *School De-registration*; worked by hand by the team | `manifest.php` defaults |
| Complaint: details; team answers in the ticket comments | Type *Complaint* (team Management); comments and internal notes on the ticket | `tickets_view.php`, `TicketService::addComment` |
| Parent chooses a notification policy: email or notification centre | *Notification Settings*: two switches | `notifications.php`, `SubscriberGateway` |
| The policy is copied to each ticket and can be changed per ticket | Row per person per ticket, created when the person becomes involved; form at the bottom of the ticket | `SubscriberGateway::subscribe`, `tickets_view.php`, `Notifier` |
| Admin defines ticket types | *Manage Ticket Types* incl. the request form | `types_*.php` |
| Admin creates teams with access to specific types | *Manage Teams* + teams chosen on the ticket type | `teams_*.php`, `TicketAccess` |
| Default workflow copied to every new type; editable per type | *Manage Workflows*; stages with no type = default; copied on type creation | `workflow_*.php`, `StageGateway::copyDefaultWorkflow` |
| Automation on the workflow: script with a clear interface, transactional, verifies, comments, moves to done | Stage has *automation on entry* + *then move to* | `src/Automation/*`, `TicketService::changeStage` |
| Staff with the permission create a ticket on behalf of someone (added in 1.1.00) | Action *New Request_onBehalf*: person picker on the service list, all active services, form built for that person, history line "Created by X on behalf of Y" | `request_new.php` (+Process), `TicketAccess::canCreateOnBehalf`, `TicketService::create` |
| Tickets are assigned directly on creation (added in 1.1.00) | Optional *Default Assignee* on the team (one of its members) | `teams_manage_edit.php`, `TeamGateway::getDefaultAssigneeByTicketType`, `TicketService::create` |
| Permission holders edit / delete comments and change their visibility, for own or any comment (added in 1.2.00) | Actions *Edit Comments_my/_all*, *Change Comment Visibility_my/_all*; Edit / Delete links on each comment | `tickets_comment_*.php`, `TicketAccess::canEditComment / canChangeCommentVisibility`, `TicketService::updateComment / setCommentVisibility / deleteComment` |
| User saves filters (type, status, assignee) | Filter form on the ticket list + *Save as Filter* | `tickets.php`, `tickets_filterProcess.php`, `TicketFilters` |
| Admin creates default filters, e.g. absence tickets for a team | Administrators save a filter for *Everyone* or for one team | same |

## 2. Data model (tables `gibbonServiceDesk*`)

```
Team ──< TeamMember >── gibbonPerson (staff)
  │
  └──< TicketTypeTeam >── TicketType ──< Field            (the request form)
                              │
                              ├──< Stage                  (workflow; Stage with type NULL = default workflow)
                              │
                              └──< Ticket ──< TicketComment      (Comment | Internal Note | Log | Internal Log)
                                     │
                                     └──< TicketSubscriber        (copy of the person's notification policy)

NotificationPreference (per person)         Filter (person NULL = shared; team = shared with that team only)
```

* `Ticket.fields`: JSON list of `[name, label, type, value, display]` - a snapshot of the form and answers.
  `value`: text; date `Y-m-d`; Students = list of `gibbonPersonID`; Classes = list of `{gibbonPersonID, gibbonCourseClassID}`.
* `Stage.category`: Open / In Progress / Closed. `Stage.automation`: class name of a script. `Stage.nextStage`: stage
  *name* in the same workflow (names, so a copied workflow needs no id remapping; renames are propagated).
* `Filter.criteria`: JSON of `search, type[], status[], stage, assignee (me|none|id), requester (me), priority`.

## 3. Access rules

Gibbon actions decide which pages a role may open (`manifest.php`). On top of that `Service/TicketAccess`:

| Who | Is | Can |
|---|---|---|
| Administrator | role holds action *Manage Ticket Types* | configure; see and manage all tickets; shared filters |
| Team member | in an active team linked to the ticket's type | see and manage those tickets; internal notes |
| Requester | raised the ticket (or it was raised on their behalf) | see it, comment, set own notifications |
| Holder of *Edit Comments_my / _all* | role holds that action | change the text of, or delete, own / any comment on tickets they can see (internal notes only when they work on the ticket; never log lines) |
| Holder of *Change Comment Visibility_my / _all* | role holds that action **and** works on the ticket | switch own / any comment between public and internal |
| Holder of *New Request_onBehalf* | role holds that action | see every active service; send a request for any active user. Gives no access to tickets by itself |

Enforced in three places: the list query (`TicketGateway::queryTickets` adds the scope to the SQL), the ticket page
and the process script (`canView` / `canManage`; "not found" and "not allowed" answer the same). Internal notes are
filtered in the query (`CommentGateway`), not in the template. Process scripts re-check everything the form limited:
the type is requestable by the role, students are the user's own children, the stage belongs to the ticket's type, the
assignee is in a team of the type, the team/type/stage ids in admin forms match the stored records.

## 4. Automation

`TicketService::changeStage()` is the only way a ticket changes stage:

1. begin transaction; move the ticket into the stage,
2. if the stage has a script: `run(AutomationContext)`; failure result or exception -> roll back, write an internal
   log line, return false (ticket stays where it was),
3. commit; write "Moved to X" (public log) and notify followers; write the script's message as a public log line,
4. if the stage has `nextStage`: repeat for that stage (at most 5 chained stages).

Scripts: `src/Automation/Scripts/*.php`, discovered by `AutomationRegistry` (no registration). Contract and rules in
`AutomationScript.php`. A stage whose script file was removed shows "Missing script" and behaves as a plain stage.

## 5. Notifications

`Notifier::notify($ticket, $text, $actorID, $internal, $withTeam)` - recipients are the ticket's subscribers (+ the
team members for new/unassigned tickets), minus the actor, minus non-team people for internal events. Each recipient
gets the channels of their row: notification centre (Gibbon `gibbonNotification`) and/or email (Gibbon `Mailer`).
All notification-centre entries are written before any email is sent, and the mailer is only created when an email
is really sent, so mail trouble cannot affect the centre. A new ticket of a type whose teams are empty goes to the
administrators. Failures are caught and logged (PHP error log + Gibbon log, title "Service Desk"); they never break
the request. **Nobody is notified about their own action** - testing with a single user shows no notifications.

## 6. File map

```
manifest.php, CHANGEDB.php, version.php     install, upgrade, version (keep the three in step)
request_new.php (+Process)                  catalog and request form
tickets.php, tickets_filterProcess.php      list, filters
tickets_view.php (+Process)                 ticket: comment | manage | notify
tickets_comment_edit.php (+Process)         comment text and/or visibility (comment permissions)
tickets_comment_delete.php (+Process)       delete a comment
notifications.php (+Process)                personal notification policy
types_manage*.php, types_field_*.php        ticket types and their form fields      (admin)
workflow_manage.php, workflow_stage_*.php   default and per-type workflows          (admin)
teams_manage*.php, teams_memberProcess.php  teams and members                        (admin)
src/Domain/*Gateway.php                     all SQL
src/Service/TicketService.php               create, comment, assign, priority, changeStage (+automation)
src/Service/TicketAccess.php                who may see / manage
src/Service/RequestFields.php               form fields: build, validate, describe
src/Service/People.php                      children of a parent, classes, staff (Gibbon tables)
src/Service/Notifier.php, TicketFilters.php notifications; filter criteria
src/Automation/                             interface, context, result, registry, Scripts/
templates/*.twig.html                       catalog, saved-filter bar, ticket view
css/module.css                              module classes (serviceDesk-*)
i18n/ServiceDesk.pot                        translation template
tests/                                      static.php, make-pot.php, live/ (real Gibbon + browser)
```

## 7. Missing use cases (raised, not built)

Usual in ticketing systems and likely to be asked for. Ordered by how much I expect a school to miss them.

| # | Use case | Note |
|---|---|---|
| M1 | **Attachments** on requests and comments (doctor's note, signed de-registration form) | Needs upload + an access-checked download script. The most likely first request. |
| M3 | **Requester withdraws a request / reopens a closed one** | Today: by commenting; the team moves the ticket. |
| M4 | **Both parents of a family see the ticket** | See decision D6. |
| M5 | **Tell teachers** (form tutor, class teachers) about an approved absence or unenrolment | Could be a second automation script. |
| M6 | **Due dates, reminders, auto-close** of tickets waiting for the requester (needs a cron/CLI script) | |
| M7 | **Dashboard widgets**: "my open requests" for parents, "unassigned tickets" for staff (Gibbon dashboard hooks) | |
| M8 | **Reports**: tickets per type/team/month, time to close; CSV export | Help Desk has statistics. |
| M9 | **Reply templates** (canned answers) for the team | Help Desk has them. |
| M10 | **Restricted transitions / approvals** (only a team lead can approve; allowed next stages) | See decision D7. |
| M11 | **Delete or anonymise tickets** for data-protection requests; retention period | See decision D15. |
| M12 | **Move a ticket to another type/team** when it was raised under the wrong service | |
| M13 | **Partial-day absence** (late arrival, leaving early, single lessons) | Today whole days only (A5). |
| M14 | **Automation for de-registration** (end enrolment, set status Left, end-date the account) and **finance** | Needs your exact rules (D11). |
| M15 | Satisfaction rating after closing; email-to-ticket; merge duplicate tickets | Lower priority. |

## 8. Test status (honest)

* **Live Gibbon v31.0.00** (PHP 8.3, MariaDB 10.11, Gibbon demo data), driven with a real browser (`tests/live/`):
  install through Manage Modules; every page as administrator; parent submits absence for two children -> team member
  adds internal note and comment, assigns, sets priority, approves -> 4 attendance rows written, ticket Done, parent
  sees comments but not internal notes; second identical ticket writes no duplicates; weekend-only absence -> automation
  fails, ticket stays New, internal log, warning; another parent cannot open the ticket or admin pages; new ticket
  type gets the default workflow; fields add/duplicate-name; stage add/delete; filter save (team-shared)/delete; type
  with tickets cannot be deleted; server-side required-field check; notification rows and per-ticket policy checked in
  the database.
* Notification centre (`tests/live/notifications.js`): entries listed on Gibbon's notification page, bell count,
  "Action & Archive" opens the ticket, empty-team ticket reaches the administrators. Email: sent through SMTP to a
  local sink (`tests/live/mail-on.sh`), sender, recipients, subject and ticket link checked.
* On behalf (`tests/live/onbehalf.js`): upgrade 1.0.00 -> 1.1.00 through Manage Modules (new action, permissions,
  new column); administrator sees all services, picks a parent, gets that parent's children, ticket has the parent as
  requester and the "created by ... on behalf of" line, parent is notified and sees the ticket; a teacher without the
  permission sees only their services and is refused the on-behalf address.
* Default assignee (`tests/live/assignee.js`): set on the team -> new ticket starts assigned and appears in the
  assignee's "Assigned to Me"; cleared -> unassigned; member removed from the team -> default cleared.
* Comment permissions (`tests/live/comments.js`, after upgrading 1.1.00 -> 1.2.00 through Manage Modules): teacher
  ("_my") gets tools on own comments only, edits own text (marked edited), makes own internal note visible (parent
  then sees it and is notified), is refused on the parent's comment; parent has no tools and no access; administrator
  ("_all") edits and deletes other people's comments, with traces in the internal history.
* **Not tested**: delivery through a real mail provider (TLS, authentication, spam filtering); Gibbon versions below v31;
  students as requesters; uninstall; other languages; very large data.
