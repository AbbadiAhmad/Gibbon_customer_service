<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Data\Validator;
use Gibbon\Module\CustomerService\Domain\RoleGateway;
use Gibbon\Module\CustomerService\Service\Permissions;

$_POST['address'] = '/modules/CustomerService/cs_role_manage.php';

require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

csRequirePost($session);

$_POST = $container->get(Validator::class)->sanitize($_POST);
$csRoleID = $_POST['csRoleID'] ?? '';

if (!isActionAccessible($guid, $connection2, '/modules/CustomerService/cs_role_edit.php')) {
    csRedirect($session, 'cs_role_manage.php', [], 'error0');
}

$gateway = $container->get(RoleGateway::class);
$data = ['name' => trim($_POST['name'] ?? ''), 'description' => $_POST['description'] ?? ''];
$permissions = array_values(array_filter((array)($_POST['permissions'] ?? []), [Permissions::class, 'isValid']));
$editParams = empty($csRoleID) ? [] : ['csRoleID' => $csRoleID];

if ($data['name'] === '') {
    csRedirect($session, 'cs_role_edit.php', $editParams, 'error1');
}
if (!$gateway->unique($data, ['name'], $csRoleID)) {
    csRedirect($session, 'cs_role_edit.php', $editParams, 'error7');
}

if (empty($csRoleID)) {
    $csRoleID = $gateway->insert($data);
    $ok = !empty($csRoleID);
} else {
    $ok = $gateway->exists($csRoleID) && $gateway->update($csRoleID, $data);
}
if (!$ok) {
    csRedirect($session, 'cs_role_edit.php', $editParams, 'error2');
}

$gateway->replacePermissions($csRoleID, $permissions);
csRedirect($session, 'cs_role_manage.php', [], 'success0');
