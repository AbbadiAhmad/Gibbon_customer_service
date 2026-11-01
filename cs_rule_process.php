<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Data\Validator;
use Gibbon\Module\administration\Automation\ActionForm;
use Gibbon\Module\administration\Automation\AutomationEngine;
use Gibbon\Module\administration\Domain\RuleGateway;
use Gibbon\Module\administration\Domain\TicketTypeGateway;
use Gibbon\Module\administration\Service\FilterForm;

$_POST['address'] = '/modules/administration/cs_rule_manage.php';

require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

csRequirePost($session);

$_POST = $container->get(Validator::class)->sanitize($_POST);
$csAutomationRuleID = $_POST['csAutomationRuleID'] ?? '';

if (!isActionAccessible($guid, $connection2, '/modules/administration/cs_rule_edit.php')) {
    csRedirect($session, 'cs_rule_manage.php', [], 'error0');
}

$gateway = $container->get(RuleGateway::class);
$editParams = empty($csAutomationRuleID) ? [] : ['csAutomationRuleID' => $csAutomationRuleID];

$types = $container->get(TicketTypeGateway::class)->selectOptions();
$type = (int)($_POST['csTicketTypeID'] ?? 0);
$trigger = $_POST['triggerKey'] ?? '';
$conditions = FilterForm::collect($_POST, 'cond');
[$actions, $problems] = ActionForm::collect($_POST, $container);

$data = [
    'name'           => trim($_POST['name'] ?? ''),
    'csTicketTypeID' => isset($types[$type]) ? $type : null,
    'triggerKey'     => $trigger,
    'conditions'     => empty($conditions) ? null : json_encode($conditions, JSON_UNESCAPED_UNICODE),
    'actions'        => json_encode($actions, JSON_UNESCAPED_UNICODE),
    'sequenceNumber' => (int)($_POST['sequenceNumber'] ?? 0),
    'active'         => ($_POST['active'] ?? 'Y') == 'N' ? 'N' : 'Y',
];

// A rule needs a name, a known trigger and at least one action with all its required settings.
if ($data['name'] === '' || !isset(AutomationEngine::triggers()[$trigger]) || empty($actions) || !empty($problems)) {
    csRedirect($session, 'cs_rule_edit.php', $editParams, 'error1');
}

if (empty($csAutomationRuleID)) {
    $ok = !empty($gateway->insert($data));
} else {
    $ok = $gateway->exists($csAutomationRuleID) && $gateway->update($csAutomationRuleID, $data);
}

csRedirect($session, $ok ? 'cs_rule_manage.php' : 'cs_rule_edit.php', $ok ? [] : $editParams, $ok ? 'success0' : 'error2');
