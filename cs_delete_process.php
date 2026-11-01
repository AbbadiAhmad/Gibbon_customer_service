<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

csRequirePost($session);

$entity = $_POST['entity'] ?? '';
$id = $_POST['id'] ?? '';

if (!isActionAccessible($guid, $connection2, '/modules/CustomerService/cs_delete.php')) {
    csRedirect($session, 'cs_dashboard.php', [], 'error0');
}

$target = csDeleteTarget($container, $session, csIsAdmin($guid, $connection2), $entity, $id);
if ($target === null || empty($id)) {
    csRedirect($session, 'cs_dashboard.php', [], 'error1');
}

[$backPage, $backParams] = $target['back'];
if (!$target['allowed'] || !csAccess($guid, $connection2, 'cs_delete.php', $target['action'])) {
    csRedirect($session, $backPage, $backParams, 'error0');
}

// A type that still has tickets cannot be deleted (deactivate it instead): reported as an error.
$deleted = ($target['delete'])();
csRedirect($session, $backPage, $backParams, $deleted ? 'success0' : 'error2');
