# CustomerService - Gibbon module

Service desk (requests, tickets, boards, automation) for Gibbon. Module folder/name `CustomerService`, namespace
`Gibbon\Module\CustomerService`, tables `gibbonCs*`.

Read first: `docs/ARCHITECTURE.md` (what exists, status, decisions) and `README.md` (features).

Rules for changing the code live in project skills - use them:

* `gibbon-module-conventions` - how a Gibbon module is built (manifest, CHANGEDB, process scripts, forms, gotchas)
* `gibbon-access-control` - the permission / security rules every entry point must follow
* `customerservice-extend-module` - checklist for adding a feature or changing the schema here
* `customerservice-automation-actions` - adding automation actions and triggers

Honest status: nothing has been run in a live Gibbon yet (see ARCHITECTURE.md "Status"). Do not claim runtime verification;
say what was checked (`php -l`, stand-alone logic scripts).

Work on the branch you are given; `main` holds the released state.
