<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Data\Validator;
use Gibbon\Module\CustomerService\Domain\ActivityGateway;
use Gibbon\Module\CustomerService\Domain\FieldGateway;
use Gibbon\Module\CustomerService\Domain\TicketGateway;
use Gibbon\Module\CustomerService\Domain\TicketTypeGateway;
use Gibbon\Module\CustomerService\Service\CustomFields;

$_POST['address'] = '/modules/CustomerService/cs_request_new.php';

require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);
$csTicketTypeID = $_POST['csTicketTypeID'] ?? '';

if (!isActionAccessible($guid, $connection2, '/modules/CustomerService/cs_request_new.php')) {
    csRedirect($session, 'cs_request_new.php', [], 'error0');
}

$type = $container->get(TicketTypeGateway::class)->getByID($csTicketTypeID);
$title = trim($_POST['title'] ?? '');
$priority = in_array($_POST['priority'] ?? '', ['Low', 'Medium', 'High', 'Urgent'], true) ? $_POST['priority'] : 'Medium';

if (empty($type) || $type['active'] != 'Y' || $title === '') {
    csRedirect($session, 'cs_request_new.php', ['csTicketTypeID' => $csTicketTypeID], 'error1');
}

$fields = $container->get(FieldGateway::class)->selectByType($csTicketTypeID);
[$customData, $missing] = CustomFields::collect($fields, $_POST);
if (!empty($missing)) {
    csRedirect($session, 'cs_request_new.php', ['csTicketTypeID' => $csTicketTypeID], 'error1');
}

$person = csPersonID($session);
$csTicketID = $container->get(TicketGateway::class)->createTicket([
    'csTicketTypeID'         => $csTicketTypeID,
    'title'                  => mb_substr($title, 0, 200),
    'description'            => $_POST['description'] ?? '',
    'status'                 => 'New',
    'priority'               => $priority,
    'gibbonPersonIDReporter' => $person,
    'csTeamID'               => $type['csTeamID'] ?: null,
    'customData'             => $customData,
]);

if (empty($csTicketID)) {
    csRedirect($session, 'cs_request_new.php', ['csTicketTypeID' => $csTicketTypeID], 'error2');
}

$container->get(ActivityGateway::class)->log($csTicketID, $person, __('Request raised'));

csRedirect($session, 'cs_ticket_view.php', ['csTicketID' => $csTicketID], 'success0');
