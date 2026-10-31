<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Data\Validator;
use Gibbon\Module\CustomerService\Domain\RoleGateway;
use Gibbon\Module\CustomerService\Domain\SpaceGateway;

$_POST['address'] = '/modules/CustomerService/cs_space_manage.php';

require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);
$action = $_POST['action'] ?? '';
$csSpaceID = $_POST['csSpaceID'] ?? '';

if (!isActionAccessible($guid, $connection2, '/modules/CustomerService/cs_space_edit.php')) {
    csRedirect($session, 'cs_space_manage.php', [], 'error0');
}

$isAdmin = csIsAdmin($guid, $connection2);
$spaces = $container->get(SpaceGateway::class);
$editParams = empty($csSpaceID) ? [] : ['csSpaceID' => $csSpaceID];

if ($action === 'save') {
    if (!$isAdmin) {
        csRedirect($session, 'cs_space_manage.php', [], 'error0');
    }
    $data = [
        'name'        => trim($_POST['name'] ?? ''),
        'description' => $_POST['description'] ?? '',
        'active'      => ($_POST['active'] ?? 'Y') == 'N' ? 'N' : 'Y',
    ];
    if ($data['name'] === '') {
        csRedirect($session, 'cs_space_edit.php', $editParams, 'error1');
    }
    if (!$spaces->unique($data, ['name'], $csSpaceID)) {
        csRedirect($session, 'cs_space_edit.php', $editParams, 'error7');
    }

    if (empty($csSpaceID)) {
        $csSpaceID = $spaces->insert($data);
        if (empty($csSpaceID)) {
            csRedirect($session, 'cs_space_edit.php', [], 'error2');
        }
        // Continue on the new space to add members and boards.
        csRedirect($session, 'cs_space_edit.php', ['csSpaceID' => $csSpaceID], 'success0');
    }
    $ok = $spaces->exists($csSpaceID) && $spaces->update($csSpaceID, $data);
    csRedirect($session, 'cs_space_edit.php', $editParams, $ok ? 'success0' : 'error2');
}

if ($action === 'addMember') {
    if (empty($csSpaceID) || !$spaces->exists($csSpaceID)) {
        csRedirect($session, 'cs_space_manage.php', [], 'error1');
    }
    if (!$isAdmin && !$spaces->hasPermission(csPersonID($session), $csSpaceID, 'manage_members')) {
        csRedirect($session, 'cs_space_manage.php', [], 'error0');
    }

    $users = array_filter((array)($_POST['users'] ?? []), 'is_numeric');
    $validRoles = $container->get(RoleGateway::class)->selectOptions();
    $roles = array_filter((array)($_POST['roles'] ?? []), function ($id) use ($validRoles) {
        return isset($validRoles[(int)$id]);
    });
    if (empty($users) || empty($roles)) {
        csRedirect($session, 'cs_space_edit.php', $editParams, 'error1');
    }

    foreach ($users as $user) {
        foreach ($roles as $role) {
            $spaces->addMember($csSpaceID, (int)$user, (int)$role);
        }
    }
    csRedirect($session, 'cs_space_edit.php', $editParams, 'success0');
}

csRedirect($session, 'cs_space_manage.php', [], 'error1');
