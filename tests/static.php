<?php
/**
 * static.php - source checks that need neither a database nor Gibbon. Usage: php tests/static.php
 *  1. every PHP file parses,
 *  2. patterns that failed in a LIVE Gibbon are not used any more (add a pattern whenever a live run finds one),
 *  3. every page and process script is listed in an action's URLList in manifest.php,
 *  4. version.php and manifest.php carry the same version, and CHANGEDB.php has an entry for it.
 */
$root = dirname(__DIR__);
$fail = 0;

$files = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
    $path = $file->getPathname();
    if ($file->getExtension() === 'php' && strpos($path, '/.git/') === false && strpos($path, '/tests/') === false) {
        $files[] = $path;
    }
}

foreach ($files as $file) {
    exec('php -l '.escapeshellarg($file).' 2>&1', $out, $code);
    if ($code !== 0) { $fail++; echo "SYNTAX: $file\n"; }
}

$forbidden = [
    '/->where\([^;]*CAST\(/s' => 'CAST(x AS UNSIGNED) inside a query-builder where() breaks (the builder reads AS as an alias; live error "Unknown data type UNSIGNED"). Use column+0.',
    '/__construct\([^)]*ContainerInterface/' => 'Gibbon\'s container cannot inject Psr ContainerInterface (live error "Alias ... is not being managed by the container"). Use global $container.',
    '/<form[^>]*method=.post/i' => 'Raw <form> posts fail Gibbon\'s CSRF check: build them with Gibbon\Forms\Form.',
];
foreach ($files as $file) {
    $code = file_get_contents($file);
    foreach ($forbidden as $pattern => $why) {
        if (preg_match($pattern, $code)) { $fail++; echo "FORBIDDEN in ".substr($file, strlen($root) + 1).": $why\n"; }
    }
}

// Every entry point must belong to an action, or nobody (or the wrong people) can reach it.
$moduleTables = $actionRows = [];
require $root.'/manifest.php';
$listed = [];
foreach ($actionRows as $action) {
    $listed = array_merge($listed, array_map('trim', explode(',', $action['URLList'])));
}
foreach (glob($root.'/*.php') as $file) {
    $name = basename($file);
    if (in_array($name, ['manifest.php', 'version.php', 'CHANGEDB.php'])) continue;
    // Command line scripts and dashboard hooks are not pages. A CLI script must stop unless run from the command line,
    // before it loads anything; a hook file must be named by a hook in manifest.php.
    if (strpos($name, 'cli_') === 0) {
        if (!preg_match('/^<\?php.*?if \(PHP_SAPI !== .cli.\) \{\s*exit/s', file_get_contents($file))) { $fail++; echo "CLI SCRIPT WITHOUT A COMMAND LINE CHECK FIRST: $name\n"; }
        continue;
    }
    if (strpos($name, 'hook_') === 0) {
        if (strpos(file_get_contents($root.'/manifest.php'), $name) === false) { $fail++; echo "HOOK FILE NOT USED BY A HOOK IN manifest.php: $name\n"; }
        continue;
    }
    if (!in_array($name, $listed)) { $fail++; echo "NOT IN ANY ACTION URLList: $name\n"; }
}
foreach ($listed as $name) {
    if (!is_file($root.'/'.$name)) { $fail++; echo "URLList names a missing file: $name\n"; }
}

require $root.'/version.php';
$sql = [];
require $root.'/CHANGEDB.php';
if ($moduleVersion !== $version) { $fail++; echo "version.php ($moduleVersion) and manifest.php ($version) differ\n"; }
if (end($sql)[0] !== $version) { $fail++; echo "CHANGEDB.php has no entry for $version\n"; }

echo count($files)." files checked, $fail problems\n";
exit($fail ? 1 : 0);
