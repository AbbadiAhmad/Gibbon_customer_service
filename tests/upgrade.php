<?php
require __DIR__.'/bootstrap.php';
// upgrade.php - fresh install (cs_test, run tests/install.php first) vs "0.1.0 install + every CHANGEDB step" (cs_chain): the schemas must be identical.
function __($s){return $s;}
// Usage: php tests/upgrade.php   (databases cs_test and cs_chain must exist; cs_chain is emptied first)
$root = dirname(__DIR__);
$pdo = csTestPdo('cs_chain');
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) { $pdo->exec("DROP TABLE `$t`"); }   // start from an empty database
// the first release's manifest comes from the git history (the commit that added manifest.php)
$first = trim(shell_exec('cd '.escapeshellarg($root).' && git log --diff-filter=A --format=%H -- manifest.php | tail -1'));
$old = tempnam(sys_get_temp_dir(), 'csmanifest');
file_put_contents($old, shell_exec('cd '.escapeshellarg($root).' && git show '.escapeshellarg($first).':manifest.php'));
// the Gibbon tables the upgrade statements touch (minimal stand-ins)
$pdo->exec("CREATE TABLE gibbonModule (gibbonModuleID int auto_increment primary key, name varchar(50), entryURL varchar(255))");
$pdo->exec("CREATE TABLE gibbonRole (gibbonRoleID int auto_increment primary key, category varchar(20))");
$pdo->exec("CREATE TABLE gibbonAction (gibbonActionID int auto_increment primary key, gibbonModuleID int, name varchar(60), precedence int, category varchar(40), description text, URLList text, entryURL varchar(255), entrySidebar char(1), menuShow char(1), defaultPermissionAdmin char(1), defaultPermissionTeacher char(1), defaultPermissionStudent char(1), defaultPermissionParent char(1), defaultPermissionSupport char(1), categoryPermissionStaff char(1), categoryPermissionStudent char(1), categoryPermissionParent char(1), categoryPermissionOther char(1))");
$pdo->exec("CREATE TABLE gibbonPermission (gibbonPermissionID int auto_increment primary key, gibbonRoleID int, gibbonActionID int)");
$pdo->exec("INSERT INTO gibbonModule (name, entryURL) VALUES ('administration', 'cs_dashboard.php')");
$pdo->exec("INSERT INTO gibbonRole (category) VALUES ('Staff'), ('Student'), ('Parent')");
// the actions of 0.1.0 (the ones later steps copy or change) with permissions for every role
$pdo->exec("INSERT INTO gibbonAction (gibbonModuleID,name,precedence,category,URLList,entryURL,entrySidebar,menuShow,defaultPermissionAdmin,defaultPermissionTeacher,defaultPermissionStudent,defaultPermissionParent,defaultPermissionSupport,categoryPermissionStaff,categoryPermissionStudent,categoryPermissionParent,categoryPermissionOther) VALUES
 (1,'My Dashboard',0,'Requests','cs_dashboard.php','cs_dashboard.php','Y','Y','Y','Y','Y','Y','Y','Y','Y','Y','Y'),
 (1,'View Ticket',2,'Requests','cs_ticket_view.php, cs_ticket_process.php','cs_ticket_view.php','N','N','Y','Y','Y','Y','Y','Y','Y','Y','Y'),
 (1,'Manage Roles',12,'Configuration','cs_role_manage.php','cs_role_manage.php','Y','Y','Y','N','N','N','N','Y','N','N','N'),
 (1,'Manage Ticket Types',11,'Configuration','cs_type_manage.php','cs_type_manage.php','Y','Y','Y','N','N','N','N','Y','N','N','N'),
 (1,'Boards',3,'Service Desk','cs_board_view.php','cs_board_view.php','Y','Y','Y','Y','Y','Y','Y','Y','Y','Y','Y')");
$pdo->exec("INSERT INTO gibbonPermission (gibbonRoleID, gibbonActionID) SELECT r.gibbonRoleID, a.gibbonActionID FROM gibbonRole r CROSS JOIN gibbonAction a");

include $old;
foreach ($moduleTables as $sql) { $pdo->exec($sql); }

$sql = []; $count = 0;
include $root.'/CHANGEDB.php';
foreach ($sql as $step) {
    if ($step[0] === '0.0.00' || $step[0] === '0.1.0') continue;
    foreach (explode(';end', $step[1]) as $statement) {
        if (trim($statement) === '') continue;
        try { $pdo->exec($statement); } catch (Throwable $e) { echo "FAIL in ".$step[0].": ".$e->getMessage()."\n".substr(trim($statement),0,150)."\n"; }
    }
    echo "applied ".$step[0]."\n";
}

$columns = function (string $db) {
    $p = csTestPdo('information_schema');
    $st = $p->prepare("SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME LIKE 'gibbonCs%' ORDER BY TABLE_NAME, COLUMN_NAME");
    $st->execute([$db]);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_NUM) as [$t, $c, $type, $null]) { $out[$t][$c] = "$type/$null"; }
    return $out;
};
$fresh = $columns('cs_test'); $chain = $columns('cs_chain');
$diff = 0;
foreach (array_unique(array_merge(array_keys($fresh), array_keys($chain))) as $t) {
    if (!isset($chain[$t])) { echo "MISSING TABLE after upgrades: $t\n"; $diff++; continue; }
    if (!isset($fresh[$t])) { echo "EXTRA TABLE after upgrades: $t\n"; $diff++; continue; }
    foreach (array_unique(array_merge(array_keys($fresh[$t]), array_keys($chain[$t]))) as $c) {
        if (($fresh[$t][$c] ?? null) !== ($chain[$t][$c] ?? null)) { echo "DIFF $t.$c fresh=".($fresh[$t][$c] ?? '-')." upgraded=".($chain[$t][$c] ?? '-')."\n"; $diff++; }
    }
}
unlink($old);
echo count($fresh)." fresh tables, ".count($chain)." upgraded tables, $diff differences\n";
if ($diff) { echo "SCHEMA MISMATCH\n"; exit(1); }
// actions after the upgrades
foreach ($pdo->query("SELECT name, entryURL, (SELECT COUNT(*) FROM gibbonPermission p WHERE p.gibbonActionID=a.gibbonActionID) AS permissions FROM gibbonAction a ORDER BY gibbonActionID")->fetchAll(PDO::FETCH_NUM) as $r) { echo "  action: {$r[0]} -> {$r[1]} ({$r[2]} role permissions)\n"; }
echo "module entry: ".$pdo->query("SELECT entryURL FROM gibbonModule")->fetchColumn()."\n";
