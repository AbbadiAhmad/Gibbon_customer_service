<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Data\Validator;
use Gibbon\Module\administration\Domain\SavedFilterGateway;

$_POST['address'] = '/modules/administration/cs_ticket_search.php';

require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

csRequirePost($session);

$_POST = $container->get(Validator::class)->sanitize($_POST);
$action = $_POST['action'] ?? '';
$person = csPersonID($session);

if (!isActionAccessible($guid, $connection2, '/modules/administration/cs_ticket_search.php')) {
    csRedirect($session, 'cs_dashboard.php', [], 'error0');
}

$gateway = $container->get(SavedFilterGateway::class);

if ($action === 'save') {
    $params = [];
    foreach (CS_SEARCH_PARAMS as $key) {
        $value = trim((string)($_POST['f_'.$key] ?? ''));
        if ($value !== '') {
            $params[$key] = $value;
        }
    }
    $name = mb_substr(trim($_POST['name'] ?? ''), 0, 100);
    if ($name === '' || empty($params)) {
        csRedirect($session, 'cs_ticket_search.php', $params, 'error1');
    }
    $ok = !empty($gateway->insert(['gibbonPersonID' => $person, 'name' => $name, 'params' => json_encode($params, JSON_UNESCAPED_UNICODE)]));
    csRedirect($session, 'cs_ticket_search.php', $params, $ok ? 'success0' : 'error2');
}

if ($action === 'delete') {
    // Only the owner can delete a saved filter.
    $filter = $gateway->getOwned($_POST['csSavedFilterID'] ?? '', $person);
    if (empty($filter)) {
        csRedirect($session, 'cs_ticket_search.php', [], 'error0');
    }
    $gateway->delete($filter['csSavedFilterID']);
    csRedirect($session, 'cs_ticket_search.php', [], 'success0');
}

csRedirect($session, 'cs_ticket_search.php', [], 'error1');
