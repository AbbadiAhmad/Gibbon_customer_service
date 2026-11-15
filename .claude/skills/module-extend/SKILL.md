---
name: customerservice-extend-module
description: Checklist for adding a feature, page, setting or table to the administration Gibbon module - where code goes, the schema/upgrade/docs steps, naming, tolerance rules, how to verify without a live Gibbon. Use before any change to this repository.
---

# Extending administration

Read `docs/ARCHITECTURE.md` first (model, who can see what, file map). Also follow `gibbon-module-conventions` and `gibbon-access-control`.

## Where things go
| You are adding | Put it in |
|---|---|
| SQL / a query | the matching `src/Domain/*Gateway.php` (new table group -> new gateway) |
| Logic without UI (rules, parsing, formatting) | `src/Service/` as a static/pure class so it can be tested stand-alone |
| A form question type or a new mapping target | `FormMapper` (`inputTypes`, `validateMapping`, `map`) + the input switch in `cs_request_new.php` + tests in `tests/logic.php` |
| A page | `cs_<area>_<verb>.php` (`manage`, `edit` for add+edit, `view`) + `cs_<area>_process.php` for its POST |
| A record the shared delete page may remove | a `case` in `csDeleteTarget()` (set `action`, `allowed`, `back`, `delete`) |
| A ticket-page tool (sub-forms) | a section in `cs_ticket_view.php` + an `action` case in `cs_ticket_extrasProcess.php` (non-main) or `cs_ticket_process.php` (status/assign/comment) |
| Something that happens on a ticket event | `Notifier` method + `AutomationEngine::fire('trigger', $id)` at the same spot |
| A new filter field | `BoardFilter::specialFields()` + `special()`; it then works in boards, automation conditions and the filter forms |

## Change checklist (do all of it)
1. Schema change? Update `manifest.php` (tables, seed) **and** add a new `CHANGEDB.php` entry (never edit old ones); bump `$version` / `$moduleVersion` together.
2. New page/action? `manifest.php` `$actionRows` (+ every page and process in `URLList`) **and** the CHANGEDB `INSERT gibbonAction` + `gibbonPermission` pair.
3. Guards: `csAccess` + `csSanitizeRequest` on pages; `csRequirePost` + sanitize + `csAccess` on processes; record-level checks (see `gibbon-access-control`).
4. Tables start with `gibbon` (`gibbonCs...`); columns keep the `cs...ID` style; keep the word-boundary rename in mind if you ever mass-replace names.
5. Tolerance: board filters/rules must never error on a custom field that a type lacks - empty value semantics only. Unknown action classes, unknown triggers or
   deleted records are skipped, not fatal. Notifications and automation failures are caught and logged, they never break the user's request.
6. User text through `__()`; escape on output.
7. Update `README.md` (feature description), `CHANGELOG.txt`, and `docs/ARCHITECTURE.md` if the model, a decision or the file map changed.
8. Verify and report honestly: `php -l` all files, then `php tests/install.php`, `php tests/upgrade.php`, `php tests/logic.php` against a throw-away MariaDB
   (`tests/db-start.sh` starts one on port 3399 and creates the databases), then `php tests/migrate.php` (upgrade with data). Add a check to `tests/logic.php`
   for every new query/rule. State that no live Gibbon run happened unless one did.
9. A live Gibbon run that reports an error is the best test there is: fix the cause, then add its pattern to `tests/static.php` (and a check to `tests/logic.php` if it is logic) so it cannot come back. Run `php tests/static.php` with the others.
10. Commit on the working branch with a message that says what and why; do not open a pull request unless asked.

## Conventions worth keeping
* Status names are free text matching board columns; `New` is the entry status; reopen goes back to `New`. Every status has a category in `gibbonCsStatus`
  (To Do / In Progress / Done) and "finished" means category Done - use `Workflow::isDone()` / `Workflow::DONE_SQL`, never a list of names. A board column editor
  creates statuses; a new status must be given a category.
* Default data lives only in `src/Install/DefaultData.php`; keep names consistent between boards, workflows, types and forms (a form's question targets must name real ticket fields); no emoji (utf8 connection).
* When a change removes or reshapes columns, the CHANGEDB step must MIGRATE the data (see 0.8.0), and `tests/migrate.php` must load a snapshot of the previous release (`tests/fixtures/install-<version>.sql`, made with `mariadb-dump` of `tests/install.php`) and assert the result. Take that snapshot BEFORE changing the schema.
* **Forms are not ticket types.** A ticket type = what a ticket is (team, workflow, ticket fields, internal flag); a form = what people fill in (questions, mapping to ticket fields, visibility, link name). Never put form or portal settings on the type, and never read form data from the type. Every question has exactly one `target`; see `docs/ARCHITECTURE.md`.
* Admin = holds *Manage Ticket Types*. Space admins act through space roles, not through that action.
* One status-change path (`$changeStatus` in `cs_ticket_process.php`) - extend it instead of writing a second one; automation uses `AutomationContext::changeStatus`.
* Events written by people or automation to the activity log: public only if the requester should see them; configuration-ish events are `Internal`.
* Dates in the database are `Y-m-d`; show with `Format::date`; parse with `csParseDate`.
