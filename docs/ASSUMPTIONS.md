# Assumptions

Things taken as true without asking. If one is wrong, tell me and the affected part is named so it can be fixed.

## About the school and Gibbon
| # | Assumption | Affects |
|---|---|---|
| A1 | Gibbon v28 or newer, MySQL 5.7+/MariaDB 10.2+, PHP 8.1+. **Tested on Gibbon v31.0.00, PHP 8.3, MariaDB 10.11 only.** | `version.php` (`$coreVersion`) |
| A2 | Parents are linked to their children the normal Gibbon way: family adult with "data access" = Yes, child enrolled in the current school year with status Full. Only those children can be chosen. | `People::getSelectableStudents()` |
| A3 | A user acts in their *current* role category: a person who is both staff and parent sees the parent services after switching role. | `request_new.php` |
| A4 | The Attendance module is used, an active attendance code named exactly `Absent` exists, and the allowed reasons are those in School Admin > Attendance Settings (the default form offers Medical, Family, Education, Other). An unknown reason is stored as empty. | `RecordAbsence` |
| A5 | An absence is a whole-day absence. School days are decided by Gibbon (`isSchoolOpen`: term dates, weekdays, special days). Periods over 60 days are refused by the script. Past and same-day dates are allowed. | `RecordAbsence` |
| A6 | Unenrolling from a class means: the class enrolment becomes "Student - Left" with an unenrol date (Gibbon's own convention), not deleting it. | `UnenrolFromClasses` |
| A7 | Mail is sent with Gibbon's own mailer and settings (System Admin > Email Settings) and from the *organisation email* (System Settings); without a sender address no email is attempted. Mail problems never hold back the notification centre and are written to the PHP error log and Gibbon's log (title "Service Desk"). | `Notifier` |

## About how the module should behave
| # | Assumption | Affects |
|---|---|---|
| B1 | Simple beats complete: one workflow per ticket type, one list page, no boards, no custom roles, no SLA. | everything |
| B2 | The default services are: Absence Report, Course Unenrolment, School De-registration (parents only), Complaint, General Request (everyone). Default teams: *Administration* (all but complaints) and *Management* (complaints). **Teams start empty: an administrator must add members**, until then only administrators see tickets. | `manifest.php` |
| B3 | Complaints go to a separate team (Management) because they may concern office staff. | `manifest.php` |
| B4 | Students can raise Complaint and General Request for themselves; staff can raise them too (staff choose any student in a "Students" question). | `manifest.php` |
| B5 | For staff, a "Classes" question is a free text box (a staff member has no "own children" to list classes for). | `RequestFields` |
| B6 | Comments are plain text (no HTML, no attachments). | `tickets_view.php` |
| B7 | Priority (Low/Normal/High/Urgent) is set by the team, not by the requester, and is not shown to the requester. | `TicketService` |
| B8 | Stage names are free text per workflow; each stage has a status (Open / In Progress / Closed) that filters and colours use. New tickets start in the stage with the lowest order number. | `StageGateway` |
| B9 | Changing the default workflow affects only ticket types created afterwards (it is *copied*, as requested). | `StageGateway::copyDefaultWorkflow()` |
| B10 | Closed tickets can still be commented on; a comment does not reopen them (the team moves them back if needed). | `tickets_viewProcess.php` |
| B11 | A person's notification choice is copied to a ticket the moment they become involved (raise it, are assigned, comment). Changing the general setting later does not change existing tickets unless "Apply to my current tickets" is chosen. | `SubscriberGateway` |
| B12 | The module's own text is English. It ships a translation template (`i18n/ServiceDesk.pot`) and German (`de_DE`) and Arabic (`ar_SA`) translations, written by the assistant and **not yet reviewed by a native speaker** (formal address in German, Modern Standard Arabic). A language shows only when it is installed and chosen in Gibbon (System Admin > Language Settings); Gibbon turns the page right-to-left for Arabic itself. | `i18n/` |
| B13 | Parents are the only audience for the family sharing; the parents of a *student* requester do not see the student's tickets. | `GuardianGateway` |
| B14 | The "teachers" of a child are the tutors of the child's form group (tutor 1-3) and users with role *Teacher* in the child's classes; assistants are not told. | `TeacherFinder` |
| B15 | A teacher notice links to the notification list because teachers cannot open tickets they are not part of. | `TicketService::notifyTeachers` |
| B16 | Retention counts from the time the ticket was closed, in whole days. A reopened ticket is no longer closed, so its clock stops. | `TicketGateway::selectExpiredTickets` |
| B17 | Dashboard hooks are shown to every role that holds the action *Tickets*; the module registers its own class namespace inside the hook because the dashboard is not one of its pages. | `hook_dashboard_*.php` |
| B18 | Upgrading to 1.10.00 removes followers that are neither the requester, the assignee nor a co-adult of the requester: before, every comment by staff made them follower, which is the behaviour the owner called wrong. Staff who want a ticket can follow it again (per-ticket form) or set a type notification. | `CHANGEDB.php` 1.10.00 |
