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
| Board with the workflow as columns and drag and drop; colours; last view remembered per user (added in 1.3.00) | List / Board switch on the Tickets page; one type in the filter -> stage columns with drag and drop, otherwise status columns; colour per ticket type | `tickets.php` (board section), `templates/board.twig.html`, `js/board.js`, `tickets_boardProcess.php`, `ViewPreferenceGateway` |
| Requester withdraws or reopens a request (1.5.00) | Link on the ticket -> confirmation page with an optional note; the withdrawn stage / first stage | `tickets_requester.php` (+Process), `TicketService::withdraw / reopen`, `TicketAccess::canWithdraw / canReopen` |
| Both parents of a family see the ticket (1.5.00) | Type setting *Share With Family*; the other adults of the family (data access) are requester side | `GuardianGateway`, `TicketAccess::isFamilyMember`, scope in `TicketGateway::queryTickets`, `Notifier` |
| Open tickets on the home page (1.5.00 as dashboard tabs; a sidebar box since 1.7.00) | One hook file for the Parental, Staff and Student dashboards; it fills Gibbon's sidebar slot and hides its own tab | `hook_dashboard.php`, `DashboardWidget` |
| A ticket with news is flagged; the list can be sorted (1.7.00) | "Updated" mark from the time each person last opened the ticket; default order updated first | `SeenGateway`, column `unread` in `TicketGateway::queryTickets`, `tickets.php` |
| Teachers are told when an absence / unenrolment is approved, notification centre only (1.5.00; an automation script since 1.6.00) | Automation script *Tell the teachers*, ticked on a stage | `src/Automation/Scripts/TellTeachers.php`, `TeacherFinder`, `Notifier::notifyCentre` |
| Ticket type decides whether requesters may withdraw / reopen (1.6.00) | Switches on the ticket type | `types_manage_edit.php`, `TicketAccess::canWithdraw / canReopen` |
| Move a ticket to another type (1.5.00) | "Move to Another Ticket Type" form on the ticket | `TicketService::changeType` |
| Delete tickets; retention per type (1.5.00) | Action *Delete Tickets* + confirmation page; *Retention (days)* on the type + cron script | `tickets_delete.php` (+Process), `cli_deleteExpiredTickets.php`, `TicketService::deleteTicket` |
| Search in title, description, assignee, reporter, children (1.5.00) | The search field of the filter form | `TicketGateway` filter rule *search*, `Ticket.searchText`, `RequestFields::searchText` |
| Settings of an automation, chosen on the stage (e.g. the attendance code of "Record absence"; excused / unexcused) (1.9.00) | Rows under the ticked script on the stage form, built from the script's own declaration | `HasSettings`, `AutomationSettings`, `Stage.automationSettings`, `AutomationContext::setting()`, `RecordAbsence` |
| The parent's absence reason comes from School Admin (1.9.00) | Request form field type *Attendance Reason* | `RequestFields`, `People::getAttendanceReasons` |
| User saves filters (type, status, assignee) | Filter form on the ticket list + *Save as Filter* | `tickets.php`, `tickets_filterProcess.php`, `TicketFilters` |
| Admin creates default filters, e.g. absence tickets for a team | Administrators save a filter for an audience - staff working on tickets, one team, or requesters (1.4.00) | same, `FilterGateway::selectFiltersByPerson` |

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

ViewPreference (per person: List | Board)
TicketSeen (per person per ticket: when last opened); Ticket.timestampModifiedPublic = last change a requester can read (1.7.00)
TicketType.shareWithFamily, TicketType.retentionDays, Stage.withdrawn, Ticket.searchText (1.5.00)
TicketType.allowWithdraw, TicketType.allowReopen; Stage.automation = comma-separated script names (1.6.00)
TypeNotification (person x ticket type: newInApp, newEmail, updateInApp, updateEmail; none = not told)
NotificationPreference (per person)         Filter (person NULL = shared, with audience Agents | Requesters;
                                                    team = agents of that team only)
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
| Family member | another adult of the requester's family with data access, for a type with *Share With Family* | the requester's rights: see, comment, withdraw, reopen |
| Holder of *Delete Tickets* | role holds that action **and** can see the ticket | permanently delete it |
| Holder of *Edit Comments_my / _all* | role holds that action | change the text of, or delete, own / any comment on tickets they can see (internal notes only when they work on the ticket; never log lines) |
| Holder of *Change Comment Visibility_my / _all* | role holds that action **and** works on the ticket | switch own / any comment between public and internal |
| Holder of *New Request_onBehalf* | role holds that action | see every active service; send a request for any active user. Gives no access to tickets by itself |

Enforced in three places: the list query (`TicketGateway::queryTickets` adds the scope to the SQL, accepts only its
own sort columns and at most 500 tickets per page), the ticket page
and the process script (`canView` / `canManage`; "not found" and "not allowed" answer the same). Internal notes are
filtered in the query (`CommentGateway`), not in the template. Process scripts re-check everything the form limited:
the type is requestable by the role, students are the user's own children, the stage belongs to the ticket's type, the
assignee is in a team of the type, the team/type/stage ids in admin forms match the stored records.

## 4. Automation

`TicketService::changeStage()` is the only way a ticket changes stage:

1. begin transaction; move the ticket into the stage,
2. run the stage's scripts in their listed order: `run(AutomationContext)`; a failure result or exception of any of
   them -> roll back everything, write an internal log line, return false (ticket stays where it was),
3. commit; write "Moved to X" (public log) and notify followers; write each script's message as a log line (public,
   or internal when the script says so),
4. if the stage has `nextStage`: repeat for that stage (at most 5 chained stages).

Scripts: `src/Automation/Scripts/*.php`, discovered by `AutomationRegistry` (no registration) and created through
Gibbon's container, so a script may ask for services in its constructor. A script with settings also implements
`HasSettings`; before each run `AutomationSettings::resolve()` checks the stored values again and a value that is no
longer valid stops the stage (nothing is changed). Contract and rules in
`AutomationScript.php`. A stage whose script file was removed shows "Missing script" and behaves as a plain stage.

## 5. Notifications

`Notifier::notify($ticket, $text, $actorID, $internal, $event)` (`$event` = `new` for a created or moved-in ticket,
`update` for everything else). Since 1.10.00 **staff are told only about what they chose or are assigned to**:

| Who | Told when |
|---|---|
| Requester, and the other adults of a shared type | every public event (their own policy, copied to the ticket) |
| Assignee (also a team's default assignee) | every event on the ticket, by their general policy |
| Anyone with a row in `gibbonServiceDeskTicketSubscriber` | by that row: the per-ticket choice always wins (also to mute) |
| Everyone named by the type's *New Tickets Without Default Assignee* (`newTicketRecipients`: Nobody (default) / Team / Administrators) | a new (or moved-in) ticket that has no assignee |
| Staff with a row in `gibbonServiceDeskTypeNotification` for the ticket's type | new tickets / updates, notification centre / email, as switched on in the row |

*Staff* = members of the teams of the type and the Service Desk administrators; a type setting of someone who left
the team is ignored. Internal events (internal notes, internal log) reach only staff and the assignee. Commenting does
not make staff followers (`addComment(..., $follow)`; requesters follow by commenting). A type setting is made by the
person (Notification Settings, only for the types they handle) or by an administrator (*Notified Staff* in Manage Ticket
Types; the person starts with their general channels). The list of ticket types warns when nobody is told about new
tickets (no default assignee, no notified staff). Each recipient gets the channels of their row: notification centre
(Gibbon `gibbonNotification`) and/or email (Gibbon `Mailer`). All notification-centre entries are written before any
email is sent, and the mailer is only created when an email is really sent, so mail trouble cannot affect the centre.
Failures are caught and logged (PHP error log + Gibbon log, title "Service Desk"); they never break the request.
**Nobody is notified about their own action** - testing with a single user shows no notifications.

## 6. File map

```
manifest.php, CHANGEDB.php, version.php     install, upgrade, version (keep the three in step)
request_new.php (+Process)                  catalog and request form
tickets.php, tickets_filterProcess.php      list and board, filters
tickets_boardProcess.php, js/board.js       a card dropped on a stage column (fills and sends a Gibbon form)
tickets_view.php (+Process)                 ticket: comment | manage | notify
tickets_comment_edit.php (+Process)         comment text and/or visibility (comment permissions)
tickets_requester.php (+Process)            withdraw / reopen by the requester side
tickets_delete.php (+Process)               delete a ticket (action Delete Tickets)
cli_deleteExpiredTickets.php                retention: run from cron (CLI only)
hook_dashboard.php                          home page: sidebar box "my open tickets" (parents, staff, students)
tickets_comment_delete.php (+Process)       delete a comment
notifications.php (+Process)                personal notification policy + staff: per ticket type (new / updates x centre / email)
types_notifyProcess.php                     administrator adds / removes notified staff of a ticket type
types_manage*.php, types_field_*.php        ticket types and their form fields      (admin)
workflow_manage.php, workflow_stage_*.php   default and per-type workflows          (admin)
teams_manage*.php, teams_memberProcess.php  teams and members                        (admin)
src/Domain/*Gateway.php                     all SQL
src/Service/TicketService.php               create, comment, assign, priority, changeStage (+automation)
src/Service/TicketAccess.php                who may see / manage
src/Service/RequestFields.php               form fields: build, validate, describe
src/Service/People.php                      children of a parent, classes, staff (Gibbon tables)
src/Service/TeacherFinder.php               form tutors and class teachers of the children on a ticket
src/Service/DashboardWidget.php             the sidebar box on the home page
src/Domain/SeenGateway.php                  when a person last opened a ticket ("Updated" mark)
src/Domain/GuardianGateway.php              the other adults of a family
src/Service/Notifier.php, TicketFilters.php notifications; filter criteria
src/Automation/                             interface, settings (HasSettings, AutomationSettings), context, result, registry, Scripts/
templates/*.twig.html                       catalog, saved-filter bar, ticket view
css/module.css                              module classes (serviceDesk-*)
i18n/ServiceDesk.pot                        translation template
i18n/de_DE, i18n/ar_SA                     German and Arabic (.po source + compiled .mo)
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
* Board (`tests/live/board.js`, after upgrading 1.2.00 -> 1.3.00 through Manage Modules): switch; view remembered on
  the next visit and after a new login; status columns for mixed types with nothing movable; stage columns for one
  type; a real drag and drop to another stage; a drop on a stage with automation asks for confirmation, records the
  attendance and the card ends in Done; a parent gets no board; type colour saved; page does not overflow.
* Filter audience (`tests/live/audience.js`, after upgrading 1.3.00 -> 1.4.00 through Manage Modules): parent and a
  staff member in no team see All / Open / Closed; a team member sees the agent filters and the team's filter; the
  administrator sees all with the requesters' ones marked; a filter shared with requesters reaches the parent and not
  the team member.
* 1.10.00 notification rules (`tests/live/notifyrules.js`; upgrade 1.9.00 -> 1.10.00 through Manage Modules and a fresh
  install, then all 20 live scripts: 230 checks, no failure): by default neither the administrator nor a team member is
  told about a new ticket or an update, also not after commenting (and commenting creates no follower row); the assignee
  is told; a member who switches on "new: centre" / "updates: email" in Notification Settings gets exactly that (the
  email read from the SMTP sink); internal notes reach staff only; an administrator adds a person under Notified Staff
  (a person outside the teams is not offered, a forged post is ignored); the per-ticket row mutes a type setting; a
  forged type id in the settings form is refused; a non-staff user has no type section. The follower clean-up of the
  upgrade step was run against a seeded row (removed; the requester's row stays). Not tested in a browser: the German and
  Arabic texts of the new pages, the warning column's wording with several teams.
* Reporter notifications (`tests/live/reporter.js`): the requester gets a notification for a public comment, an
  assignment and a stage change made by a team member; none for an internal note or for their own comment; the team
  member is told about the requester's comment; the self-test reports both channels.
* 1.5.00 (`family.js`, `teachers.js`, `lifecycle.js`, `search.js`, `widgets.js`; upgrade 1.4.01 -> 1.5.00 through Manage
  Modules and a fresh install): the co-parent sees, comments on, withdraws and reopens a shared absence, is told about
  the team's comment, and sees nothing of the complaint; a parent of another family sees neither; reopening is refused
  after 30 days; teachers (form tutors + class teachers, computed independently in SQL) get a notice without the
  reason and no email; a unenrolment tells only the tutor and the teacher of the chosen class; moving a ticket,
  administrator told when the new type's team is empty; delete refused for team member and requester, allowed for the
  administrator, nothing left in four tables, log line without content; the retention script (dry run, real run,
  second run, other types untouched, refuses a web request); 19 searches incl. internal notes not found and old
  tickets found; widgets on both dashboards for five different users.
* 1.6.00 (`options.js`; upgrade 1.5.00 -> 1.6.00 and a fresh install): the old teacher switch converted to the script;
  stage form saved twice keeps the ticked scripts; both scripts succeed (attendance + notices); a weekend absence
  fails and leaves **no** teacher notice, no attendance row, ticket in New; unticking the teacher script; no script
  ticked clears "then move to"; the two ticket type switches hide the links and make the pages refuse.
* 1.7.00 (`widgets.js`, `updated.js`; upgrade 1.6.00 -> 1.7.00 and a fresh install): sidebar box below the Message
  Wall, once for a parent with two children, none without open tickets, for team member, teacher and student; the
  "Service Desk" tab is hidden and the other dashboard tabs still work; the "Updated" mark for new tickets, a
  parent's comment, a team answer, not for internal notes (requester) nor own changes nor board moves; sorting by
  clicking headings. **No Gibbon core file is changed.**
* 1.8.00: speed with 200 000 tickets, 46 forged-request probes and retention at scale - see `docs/AUDIT.md`.
* 1.9.00 (`settings.js`, 43 checks; upgrade 1.8.00 -> 1.9.00): the code dropdown offers only valid codes, is hidden unless the
  script is ticked, the choice is stored by id and shown in the workflow list; excused code recorded with reason;
  default "Absent" without a setting; a different record of the day is a clear failure; a deactivated code stops the stage
  safely (reactivated: works); a renamed code keeps working; 9 forged values refused; settings of unticked or unknown
  scripts ignored; copy with a new ticket type; reasons from School Admin (new reason offered at once, "Pending" and
  forged reasons refused); a test-only script with a select, text, number and yes/no setting works with no other code,
  and all its bad values are refused.
* **Not tested**: the retention script from a real cron; drag and drop on touch screens (HTML drag and drop is a mouse feature; on a phone open the ticket
  and use Manage Ticket); delivery through a real mail provider (TLS, authentication, spam filtering); Gibbon versions below v31;
  students as requesters; uninstall; other languages; very large data.
