# ServiceDesk - Gibbon module

Request / ticket module for Gibbon. Folder and module name `ServiceDesk`, namespace `Gibbon\Module\ServiceDesk`,
tables `gibbonServiceDesk*`. The repository root *is* the module folder.

Read first: `docs/ARCHITECTURE.md` (use cases, model, access rules, file map, missing use cases, test status),
`docs/DECISIONS.md` (open questions - add a row for every new question), `docs/ASSUMPTIONS.md` (add a row for every
new assumption).

Rules and verified knowledge are in project skills - use them before changing code:

* `gibbon-module-conventions` - how a Gibbon module is built, with the pitfalls found in live runs
* `gibbon-access-control` - the permission / security rules every entry point follows
* `module-extend` - where things go in this module and the checklist for a change
* `gibbon-live-testing` - how to build a real Gibbon here and test the module in a browser

Principles the owner asked for: keep it simple; Gibbon best practice (gateways, services, Twig views, `Form` /
`DataTable`, action permissions, module-level translation with `__m()`); document questions and assumptions; say
honestly what was tested and how.

Work on the branch you are given. Do not open a pull request unless asked.
