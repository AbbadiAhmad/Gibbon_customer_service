---
name: gibbon-module-conventions
description: How a GibbonEdu module is built and the pitfalls found while building administration - manifest.php, CHANGEDB.php upgrades, gateways, process scripts, CSRF, forms, tables, hooks, downloads. Use when creating or changing any Gibbon module page, process script, table, action or upgrade step.
---

# Gibbon module conventions

Verified against the Gibbon v31 core source and the Sepa module. When unsure, read the core file (clone `GibbonEdu/core`) - do not guess an API.

## Module shape
* Folder name = `$name` in `manifest.php` = namespace `Gibbon\Module\<Name>`. Classes live in `src/` and are autoloaded by namespace
  (`src/Domain/XGateway.php` -> `Gibbon\Module\<Name>\Domain\XGateway`). No spaces in the module name.
* Pages and process scripts are flat files in the module root. `moduleFunctions.php` holds shared functions (this module `require_once`s it at the top of every page and process script).
* `version.php` (`$moduleVersion`), `manifest.php` (`$version`) and the newest `CHANGEDB.php` entry must always be the same number.

## Database
* **Table names start with `gibbon`** (project rule): `gibbonCsTicket`. Primary/foreign key columns here keep the short `cs` prefix.
* `manifest.php`: every entry of `$moduleTables[]` is executed separately -> put each `CREATE TABLE` and each seed `INSERT` in its own
  entry. Seed with explicit ids when other seed rows reference them.
* `CHANGEDB.php` is the upgrade path: one `$sql[$count]` per version, statements separated by `;end` (never use `;end` elsewhere,
  last statement ends with `;` only). **Never edit an old entry** (installs run them in order, with the names of that time); add a new
  version for every schema/action change *and* mirror it in `manifest.php` for fresh installs.
* Adding an action in an upgrade = `INSERT INTO gibbonAction ... SELECT` copying an existing action's flags, then
  `INSERT INTO gibbonPermission` copying that action's permission rows (see 0.2.0 / 0.3.0 / 0.4.0 entries). Changing a module's
  landing page = `UPDATE gibbonModule SET entryURL`.
* Gateways: `extends QueryableGateway` + `use TableAware` with `$tableName`, `$primaryKey`, `$searchableColumns`. Custom SQL via
  `$this->db()->select|selectOne|insert|update|delete($sql, $bindings)`; `selectOne` returns the first column when there is one.
  **PDO named placeholders must be unique in a statement** (don't reuse `:q` twice). Paginated tables: `newQuery()` + `runQuery($query, $criteria)`.

## Actions (manifest `$actionRows`)
* One entry per menu item; `URLList` lists every page that belongs to it (process scripts included); `entryURL` is the landing page;
  `menuShow` N hides it from the menu (detail/edit pages). Defaults: `defaultPermission{Admin,Teacher,Student,Parent,Support}` and
  `categoryPermission{Staff,Student,Parent,Other}` (both must allow it).
* Install iterates arrays by index: use `$actionRows[] =`, `$hooks[] =`, `$moduleTables[] =`.
* Dashboard hooks: `$hooks[]` = `INSERT INTO gibbonHook (name, type, options, gibbonModuleID) ...` with `serialize(['sourceModuleName','sourceModuleAction','sourceModuleInclude'])`;
  types `Staff Dashboard`, `Student Dashboard`, `Parental Dashboard`. The include file **returns** the HTML string.

## Pages
* Standard shape: `isActionAccessible(...)` guard -> `$page->breadcrumbs->add(...)` -> build `Form::create()` / `DataTable` -> `echo $form->getOutput()`.
* `DataTable::create($id)->render(new DataSet($rows))`; paginated: `createPaginated($id, $criteria)` and render the gateway's result.
* Forms: `Form::create($id, $action)` adds CSRF token + nonce. **Never write raw `<form>` posts** (they fail the CSRF check); for tiny
  inline buttons use `Form::createBlank()` + `addSubmit()`. A form action ending `addProcess`/`editProcess` switches on quick-save and
  the meta sidebar - avoid those endings unless wanted. `Form::createSearch()` adds a hidden `q` (the route): never name a field `q`.
* Checkbox groups: `addCheckbox($name)->fromArray()->checked()` posts `name[]`; use unique names when several forms share a page.
  Show/hide rows: `$form->toggleVisibilityByClass($class)->onSelect($field)->when($value)`.
* Dates: `Format::dateConvert()` **never fails** (returns the input) - validate with `checkdate` (`csParseDate()` here).
* Role category of the user: `$session->get('gibbonRoleIDCurrentCategory')`. Person: `$session->get('gibbonPersonID')`.
* Module CSS: `$page->stylesheets->add('id', 'modules/<Name>/css/module.css')` on the pages that need it.

## Process scripts
```php
$_POST['address'] = '/modules/<Name>/<page that owns the action>.php';   // BEFORE gibbon.php
require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';
// POST only, then sanitise, then isActionAccessible, then act, then header('Location: ...&return=...') + exit
```
* gibbon.php validates CSRF + nonce **only** when `PHP_SELF` contains `Process.php` (case-insensitive: `x_process.php` counts) and the POST has
  more than one field. Name every process script `...Process.php` or `..._process.php`.
* Return codes shown by Gibbon: `success0`, `warning1`, `error0` no access, `error1` invalid input, `error2` database, `error3` invalid,
  `error7` not unique. Redirect with `&return=`.
* Downloads/exports: link `export.php?q=/modules/<Name>/file.php` (no page layout, session checked); stream there after your own check.
* Uploads: `new Gibbon\FileUploader($pdo, $session)` -> `uploadFromPost($file)` returns a path below `uploads/` (publicly reachable folder:
  always serve through an access-checked script, never expose the raw path).
* Notifications: `$container->get(NotificationSender::class)->addNotification($personID, $text, $moduleName, '/index.php?q=...')`, then `sendNotifications()`.
* CLI/cron scripts: refuse unless `PHP_SAPI === 'cli'` *before* requiring `gibbon.php`.

## Character set
Gibbon connects to MySQL with `charset=utf8` (3 bytes per character): seed data and defaults must not contain 4-byte characters (emoji). Use BMP symbols.

## Default data
Installed defaults are described once, in `src/Install/DefaultData.php`, and turned into INSERTs by `manifest.php` (explicit ids so rows can reference each other).
Names used across defaults (statuses, teams, types) must stay in sync with the boards/workflows that mention them. Upgrades do not insert defaults.

## Verifying
1. `php -l` every file.
2. **Database tests** (need a throw-away MariaDB/MySQL, see `tests/bootstrap.php`): `php tests/install.php` (manifest tables + defaults execute),
   `php tests/upgrade.php` (first release + every CHANGEDB step == fresh install schema), `php tests/logic.php` (real gateways/filters/workflow/automation on the
   default data, Gibbon base classes stubbed). Run them after any schema, query, default-data or CHANGEDB change and add checks for new behaviour.
3. Pages and process scripts need a live Gibbon; without one, say so. Never claim a page "works" from 1 and 2 alone.
