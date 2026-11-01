<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Data\Validator;
use Gibbon\Module\CustomerService\Domain\TeamGateway;

$_POST['address'] = '/modules/CustomerService/cs_team_manage.php';

require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

csRequirePost($session);

$_POST = $container->get(Validator::class)->sanitize($_POST);
$csTeamID = $_POST['csTeamID'] ?? '';

if (!isActionAccessible($guid, $connection2, '/modules/CustomerService/cs_team_manage.php')) {
    csRedirect($session, 'cs_team_manage.php', [], 'error0');
}

$gateway = $container->get(TeamGateway::class);
$data = [
    'name'        => trim($_POST['name'] ?? ''),
    'description' => $_POST['description'] ?? '',
    'active'      => ($_POST['active'] ?? 'Y') == 'N' ? 'N' : 'Y',
];
$editParams = empty($csTeamID) ? [] : ['csTeamID' => $csTeamID];

if ($data['name'] === '') {
    csRedirect($session, 'cs_team_edit.php', $editParams, 'error1');
}
if (!$gateway->unique($data, ['name'], $csTeamID)) {
    csRedirect($session, 'cs_team_edit.php', $editParams, 'error7');
}

if (empty($csTeamID)) {
    $csTeamID = $gateway->insert($data);
    $ok = !empty($csTeamID);
} else {
    $ok = $gateway->exists($csTeamID) && $gateway->update($csTeamID, $data);
}
if (!$ok) {
    csRedirect($session, 'cs_team_edit.php', $editParams, 'error2');
}

$gateway->replaceMembers($csTeamID, (array)($_POST['members'] ?? []));
csRedirect($session, 'cs_team_manage.php', [], 'success0');
