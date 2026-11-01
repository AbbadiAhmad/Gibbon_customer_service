<?php
/*
 * Shared by the database tests. They need a throw-away MariaDB/MySQL (never a real Gibbon database):
 *   host 127.0.0.1, port 3399, user root, no password - override with CS_TEST_HOST / CS_TEST_PORT / CS_TEST_USER / CS_TEST_PASS.
 * Databases used: cs_test (fresh install), cs_chain (0.1.0 + every upgrade step).
 */
function csTestDsn(string $db): string
{
    return 'mysql:host='.(getenv('CS_TEST_HOST') ?: '127.0.0.1').';port='.(getenv('CS_TEST_PORT') ?: '3399').';dbname='.$db.';charset=utf8mb4';
}

function csTestPdo(string $db): PDO
{
    return new PDO(csTestDsn($db), getenv('CS_TEST_USER') ?: 'root', getenv('CS_TEST_PASS') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
}
