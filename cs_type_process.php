<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Data\Validator;
use Gibbon\Module\CustomerService\Domain\TeamGateway;
use Gibbon\Module\CustomerService\Domain\TicketTypeGateway;
use Gibbon\Module\CustomerService\Service\FormConfig;
use Gibbon\Module\CustomerService\Service\Workflow;

$_POST['address'] = '/modules/CustomerService/cs_type_manage.php';

require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

csRequirePost($session);

$_POST = $container->get(Validator::class)->sanitize($_POST);
$csTicketTypeID = $_POST['csTicketTypeID'] ?? '';

if (!isActionAccessible($guid, $connection2, '/modules/CustomerService/cs_type_edit.php')) {
    csRedirect($session, 'cs_type_manage.php', [], 'error0');
}

$gateway = $container->get(TicketTypeGateway::class);
$teams = $container->get(TeamGateway::class)->selectOptions();
$team = (int)($_POST['csTeamID'] ?? 0);
$data = [
    'name'        => trim($_POST['name'] ?? ''),
    'description' => $_POST['description'] ?? '',
    'csTeamID'    => isset($teams[$team]) ? $team : null,
    'active'      => ($_POST['active'] ?? 'Y') == 'N' ? 'N' : 'Y',
    'groupName'   => mb_substr(trim($_POST['groupName'] ?? ''), 0, 60) ?: null,
    'icon'        => mb_substr(trim($_POST['icon'] ?? ''), 0, 8) ?: null,
    'audience'    => implode(',', array_intersect((array)($_POST['audience'] ?? []), array_keys(FormConfig::audienceOptions()))) ?: null,
    'workflow'    => Workflow::fromText($_POST['workflowTransitions'] ?? '', $_POST['workflowCommentRequired'] ?? ''),
];
$editParams = empty($csTicketTypeID) ? [] : ['csTicketTypeID' => $csTicketTypeID];

if ($data['name'] === '') {
    csRedirect($session, 'cs_type_edit.php', $editParams, 'error1');
}
if (!$gateway->unique($data, ['name'], $csTicketTypeID)) {
    csRedirect($session, 'cs_type_edit.php', $editParams, 'error7');
}

if (empty($csTicketTypeID)) {
    $csTicketTypeID = $gateway->insert($data);
    if (empty($csTicketTypeID)) {
        csRedirect($session, 'cs_type_edit.php', [], 'error2');
    }
    // Go straight to the form fields of the new type.
    csRedirect($session, 'cs_type_edit.php', ['csTicketTypeID' => $csTicketTypeID], 'success0');
}

if (!$gateway->exists($csTicketTypeID) || !$gateway->update($csTicketTypeID, $data)) {
    csRedirect($session, 'cs_type_edit.php', $editParams, 'error2');
}
csRedirect($session, 'cs_type_manage.php', [], 'success0');
