<?php
/**
 * static.php - source checks that need neither a database nor Gibbon. Usage: php tests/static.php
 *  1. every PHP file parses,
 *  2. calls that failed in a LIVE Gibbon are not used any more (add new ones here when a live run finds one).
 */
$root = dirname(__DIR__);
$fail = 0;

$files = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->getExtension() === 'php' && strpos($file->getPathname(), '/.git/') === false && strpos($file->getPathname(), '/tests/') === false) {
        $files[] = $file->getPathname();
    }
}

foreach ($files as $file) {
    exec('php -l '.escapeshellarg($file).' 2>&1', $out, $code);
    if ($code !== 0) { $fail++; echo "SYNTAX: $file\n"; }
}

$forbidden = [
    '/->addSelectUsers\(/' => 'Gibbon\'s addSelectUsers() came back as an error placeholder in a live Gibbon ("Undefined Method: Trying to call selected on Layout\Element"): use PersonGateway::selectOptions() with addSelectPerson() / addSelect()',
];
foreach ($files as $file) {
    $code = file_get_contents($file);
    foreach ($forbidden as $pattern => $why) {
        if (preg_match($pattern, $code)) { $fail++; echo "FORBIDDEN in ".substr($file, strlen($root) + 1).": $why\n"; }
    }
}

echo count($files)." files checked, $fail problems\n";
exit($fail ? 1 : 0);
