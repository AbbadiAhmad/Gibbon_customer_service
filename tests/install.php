<?php
require __DIR__.'/bootstrap.php';
// install.php - executes manifest.php (tables + default data) on an empty database, like the Gibbon module installer does.
// Usage: php tests/install.php [database=cs_test]   (the database must exist)
$db = $argv[1] ?? 'cs_test';
$pdo = csTestPdo($db);
$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
foreach ($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) as $t) { $pdo->exec("DROP TABLE `$t`"); }
function __($s){return $s;}
include dirname(__DIR__).'/manifest.php';
$ok = 0; $bad = 0;
foreach ($moduleTables as $i => $sql) {
    try { $pdo->exec($sql); $ok++; } catch (Throwable $e) { $bad++; echo "FAIL #$i: ".$e->getMessage()."\n".substr(trim($sql),0,120)."\n"; }
}
if ($bad) { exit(1); }
echo "$ok statements ok, $bad failed; tables: ".count($pdo->query("SHOW TABLES")->fetchAll())."\n";
