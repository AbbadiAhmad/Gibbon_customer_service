# Service Desk - a request and ticket module for Gibbon

Parents, students and staff choose a **service** (absence report, course unenrolment, de-registration, complaint, ...),
fill in its form, and the request arrives as a **ticket** with the **team** responsible for it. The team works the
ticket through a **workflow**; stages can run an **automation** (for example: on *Approved*, mark the children absent
in Attendance, check it, comment on the ticket and close it). Everyone chooses how they are told about updates.

Inspired by the functions of [GibbonEdu/module-helpdesk](https://github.com/GibbonEdu/module-helpdesk); written from scratch.

| Read | For |
|---|---|
| [`docs/DECISIONS.md`](docs/DECISIONS.md) | Questions that came up and the answers chosen - **several are open for you to confirm** |
| [`docs/ASSUMPTIONS.md`](docs/ASSUMPTIONS.md) | What was assumed about the school, Gibbon and the wanted behaviour |
| [`docs/AUDIT.md`](docs/AUDIT.md) | Speed with 200 000 tickets, security probes, what retention removes and leaves - measured, with findings and fixes |
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | Use cases -> where they are built, data model, access rules, automation interface, file map, **missing use cases**, test status |
| [`.claude/skills/`](.claude/skills) and [`CLAUDE.md`](CLAUDE.md) | Rules and verified knowledge for changing the code later |

## Install

The module is self-contained: **no Gibbon core file is changed**, also not for the box on the home page.

1. Copy (or clone) this repository to `<gibbon>/modules/ServiceDesk` (the folder name must be exactly `ServiceDesk`).
2. Gibbon > **System Admin > Manage Modules** > install *ServiceDesk*.
3. **ServiceDesk > Manage Teams**: add staff to the teams *Administration* and *Management*. Until a team has members,
   only administrators see its tickets.
4. Review **User Admin > Manage Permissions** if roles other than Administrator should configure the module.
5. Log out and in again (Gibbon caches the menu per session).

Notifications: nobody is told about their own action, so try it with two users (e.g. a parent and a team member).
*Notification Settings > Send Me a Test Notification* tries both channels for you and says what happened, including
the mail server's error message - the quickest way to check a new installation.
Email needs Gibbon's email settings and the organisation email (System Settings) to be filled in.

**Updating**: copy the new code over the folder, then System Admin > Manage Modules > *Update* on ServiceDesk, then
**System Admin > Cache Manager > clear the template cache** (Gibbon keeps compiled page templates; without this an
updated page can keep showing its old layout), then log in again (permissions and menu are read at login).

Requires Gibbon v28+ (tested on v31), PHP 8.1+, MySQL 5.7+ / MariaDB 10.2+.

## What people can do

**Everyone (parents, students, staff)**
* *New Request*: pick a service offered to their role, fill in its form. Parents pick among their own children; the
  unenrolment form lists the classes of those children.
* *Tickets*: follow their requests, read the team's comments, reply.
* *Notification Settings*: notification centre and/or email. The choice is copied to each ticket they are involved in
  and can be changed per ticket (bottom of the ticket page).
* *Notification Settings* for staff (administrators, team members): staff are told about nothing by default except
  tickets assigned to them. Per ticket type they can switch on "new tickets" and "updates", each by notification centre
  and/or email. Administrators can also assign people to a ticket type (*Manage Ticket Types > Notified Staff*).
* Filter the ticket list (type, status, stage, assignee, priority, text) and save filters under a name.

**Requesters** (and, for shared services, the other adult of the family)
* *Withdraw this request* while it is open, *Reopen this request* for 30 days after it was closed - on the ticket page.
  Each ticket type has a switch for both (*Requesters Can Withdraw*, *Requesters Can Reopen*).
* For absences, unenrolments and de-registrations the other parent of the family sees the ticket too, comments on it and
  is told about updates (setting *Share With Family* per ticket type; with data access only).
* On the home page a box in the sidebar (below the Message Wall) shows the two most recently updated open requests;
  it is not shown when there is none. Team members see the tickets waiting for them there.
* Tickets that changed since you last opened them are marked **Updated** and bold - in the list, on the board and in
  that box. The list shows updated tickets first; click a column heading to sort.
* The search field finds tickets by number, subject, details, children, classes, dates, requester, assignee and comments.

**Staff with *New Request_onBehalf*** (default: Administrator, Support Staff)
* On *New Request* they see every active service and an *On Behalf Of* picker. The person chosen becomes the
  requester (the form shows that person's children and classes); the ticket history records who created it.

**Comment permissions** (User Admin > Manage Permissions)
* *Edit Comments_my* / *_all*: change the text of, or delete, own / any comment.
* *Change Comment Visibility_my* / *_all*: switch own / any comment between visible to the requester and internal
  (only for people who work on the ticket).
* Default: Administrator "_all"; Teacher and Support Staff "_my". Changes by others are noted in the team's history.

**Team members** (staff added to a team)
* Move a ticket to another ticket type when it was filed under the wrong service.
* See the tickets of the ticket types given to their teams; comment, write internal notes (never shown to the
  requester), assign, set priority, move between stages.
* Use the shared filters made for everyone or for their team (e.g. *Absence Intake*).
* Switch the Tickets page between **List** and **Board**; the page remembers the last choice per person. With a
  filter on one ticket type the board shows that type's workflow stages and tickets are moved by dragging them to
  another column; otherwise it shows Open / In Progress / Closed as an overview. Cards carry the colour of their
  ticket type; closed tickets stay on the board for 30 days.

**Administrators** (role with *Manage Ticket Types*)
* *Manage Ticket Types*: services, their colour, who may request them, which teams handle them, and the request form (fields of type
  Text, Long Text, Date, Select, Yes/No, Students, Classes).
* *Delete Tickets* (permission, default Administrator): delete a ticket for good from its page (data protection). Set a
  **retention** in days on a ticket type and schedule the script that deletes expired closed tickets, e.g. every night:
  `php <gibbon>/modules/ServiceDesk/cli_deleteExpiredTickets.php` (`--dry-run` shows what it would delete; it works in
  batches and also removes left-overs of deleted tickets and people). **No retention is set by default**: without it
  nothing is ever deleted.
* *Manage Workflows*: the default workflow (copied to every new ticket type) and the workflow of each type: stages
  (flag *Withdrawn Stage*; automations are a tick list - e.g. *Record absence in Attendance* and *Tell the teachers* on
  the same stage - and run together: if one fails, none takes effect),
  their status (Open / In Progress / Closed), an automation on entry and the stage to move to afterwards.
* *Manage Teams*: teams, members, and an optional *Default Assignee* per team: new tickets of the team's ticket
  types are then assigned to that member straight away (empty = tickets start unassigned).
* Save a filter as shared and choose its audience: staff working on tickets, one team, or requesters.
* See and manage every ticket.

## Installed defaults

| Service | Requested by | Team | Workflow |
|---|---|---|---|
| Absence Report | Parents | Administration | New -> **Approved** (automations: record absence + tell the teachers, then Done) / In Progress / Done / Rejected / Withdrawn |
| Course Unenrolment | Parents | Administration | New, In Progress, **Approved** (automation: tell the teachers), Done, Rejected, Withdrawn |
| School De-registration | Parents | Administration | New, In Progress, Done, Rejected |
| Complaint | Everyone | Management | New, In Progress, Done, Rejected |
| General Request | Everyone | Administration | New, In Progress, Done, Rejected |

Shared filters for staff working on tickets (administrators and team members): My Requests, Open Tickets, Assigned
to Me, Unassigned, Absence Intake (team Administration). For requesters (parents, students, staff in no team): Open,
Closed.

## Writing an automation script

Create `src/Automation/Scripts/MyScript.php`; it appears in the stage form automatically.

```php
namespace Gibbon\Module\ServiceDesk\Automation\Scripts;

use Gibbon\Module\ServiceDesk\Automation\{AutomationScript, AutomationContext, AutomationResult};

class MyScript implements AutomationScript
{
    public function getName(): string        { return 'My script'; }
    public function getDescription(): string { return 'What it does and which form fields it reads.'; }

    public function run(AutomationContext $context): AutomationResult
    {
        $students = $context->field('students', []);          // answers of the request form, by field name
        $context->db()->update("UPDATE ...", [...]);          // part of the transaction
        $ok = $context->db()->selectOne("SELECT COUNT(*) ...", [...]);   // verify in the database
        return $ok
            ? AutomationResult::success('Written on the ticket as a comment.')
            : AutomationResult::failure('Rolled back; written on the ticket as an internal note.');
    }
}
```

### Settings of a script

A script that needs configuring (which attendance code to write, which class to use...) implements `HasSettings` as
well and declares what it offers; the stage form shows it under the script, the values are checked, stored with the
stage and given back through `$context->setting('name')`. No other code is needed.

```php
class RecordAbsence implements AutomationScript, HasSettings
{
    public function getSettings(): array
    {
        return ['attendanceCode' => [
            'label' => __m('Attendance code'), 'type' => 'select', 'required' => false,
            'options' => fn() => $this->absenceCodes(),     // a function: the list is read from the database each time
        ]];
    }
    // run(): $context->setting('attendanceCode')
}
```
Types: `select`, `text`, `number` (min, max), `yesno`; plus `required`, `default`, `description`. A stored value that
is no longer valid stops the stage instead of falling back to something else. Example in `tests/live/fixtures/`.

The full contract is in `src/Automation/AutomationScript.php`; `RecordAbsence.php` is the worked example and
`TellTeachers.php` shows a script that uses the module's services (`$context->container()->get(...)`) and returns a
team-only result line (`AutomationResult::success($message, true)`). A stage can tick several scripts. *Record absence in Attendance* has a setting for the attendance code (an excused or
unexcused variant of Absent): to let the team choose when approving, make two approval stages with different codes.
The parent's absence reason comes from School Admin > Attendance Settings.

## Translating

All module text goes through Gibbon's module translation domain (`__m()`). `i18n/ServiceDesk.pot` is the template
(rebuild with `php tests/make-pot.php`); put translations in `i18n/<locale>/LC_MESSAGES/ServiceDesk.po` and compile with `msgfmt ServiceDesk.po -o ServiceDesk.mo` (Gibbon reads the `.mo`; commit both). German (`de_DE`) and Arabic (`ar_SA`) are included. After adding text: `php tests/make-pot.php`, `msgmerge -U` each `.po`, translate the new entries, recompile. The default ticket types, fields, stages and filters created by `manifest.php` are listed by hand in `tests/make-pot.php` (`$seedStrings`); keep both in step.

## Server notes

* Keep PHP's `display_errors` off in production (as for Gibbon itself).
* The folders `src`, `templates`, `tests`, `docs`, `i18n` and `.claude` need no web access: on Apache the included
  `.htaccess` files deny it; on nginx add a rule, or leave `tests`, `docs` and `.claude` off the server.
* A person can send 20 requests per hour for themselves; a page of the ticket list holds at most 500 tickets.

## Tests

* `php tests/static.php` - syntax, known-bad patterns, every page listed in an action, versions in step.
* `tests/live/` - builds a real Gibbon with demo data and drives the module in a browser (see `tests/live/setup.sh`);
  `tests/live/security.js` are the forged-request probes.
* `tests/perf/` - 200 000 tickets and a timing of every page (`seed.sql`, `measure.js`).
