# administration - Gibbon module

Service desk (requests, tickets, boards, automation) for Gibbon. Module folder/name `administration`, namespace
`Gibbon\Module\administration`, tables `gibbonCs*`.

Read first: `docs/ARCHITECTURE.md` (what exists, status, decisions) and `README.md` (features).

Rules for changing the code live in project skills - use them:

* `gibbon-module-conventions` - how a Gibbon module is built (manifest, CHANGEDB, process scripts, forms, gotchas)
* `gibbon-access-control` - the permission / security rules every entry point must follow
* `customerservice-extend-module` - checklist for adding a feature or changing the schema here
* `customerservice-automation-actions` - adding automation actions and triggers

Honest status (see ARCHITECTURE.md "Status"): the SQL, upgrades and the module's own logic are tested against a real MariaDB
(`tests/`, run them after every schema or query change); nothing that needs Gibbon itself (pages, forms, CSRF, permissions) has run in a
live Gibbon yet. Do not claim more than what was checked - say which of `php -l`, `tests/*` or a live Gibbon run was used.

Work on the branch you are given; `main` holds the released state.
