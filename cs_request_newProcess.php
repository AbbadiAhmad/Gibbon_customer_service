<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Data\Validator;
use Gibbon\Module\CustomerService\Automation\AutomationEngine;
use Gibbon\Module\CustomerService\Domain\ActivityGateway;
use Gibbon\Module\CustomerService\Domain\AttachmentGateway;
use Gibbon\Module\CustomerService\Domain\FieldGateway;
use Gibbon\Module\CustomerService\Domain\TicketGateway;
use Gibbon\Module\CustomerService\Domain\TicketTypeGateway;
use Gibbon\Module\CustomerService\Service\Attachments;
use Gibbon\Module\CustomerService\Service\CustomFields;
use Gibbon\Module\CustomerService\Service\FormConfig;
use Gibbon\Module\CustomerService\Service\Notifier;

$_POST['address'] = '/modules/CustomerService/cs_request_new.php';

require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

csRequirePost($session);

$_POST = $container->get(Validator::class)->sanitize($_POST);
$csTicketTypeID = $_POST['csTicketTypeID'] ?? '';

if (!csAccess($guid, $connection2, 'cs_request_new.php')) {
    csRedirect($session, 'cs_portal.php', [], 'error0');
}

$type = $container->get(TicketTypeGateway::class)->getByID($csTicketTypeID);
$isAdmin = csIsAdmin($guid, $connection2);

// Same rules as the form: the type must exist, be active and be open to the user's role category.
if (empty($type) || ($type['active'] != 'Y' && !$isAdmin)) {
    csRedirect($session, 'cs_portal.php', [], 'error1');
}
if (!$isAdmin && !FormConfig::inAudience($type['audience'], csRoleCategory($session))) {
    csRedirect($session, 'cs_portal.php', [], 'error0');
}

$formParams = ['csTicketTypeID' => $csTicketTypeID];
$allFields = $container->get(FieldGateway::class)->selectByType($csTicketTypeID);
$items = FormConfig::items($type['formConfig'], $allFields);

// Only the fields the form showed are read - a hidden field can't be posted into a ticket - and required ones are enforced.
$shown = [];
$customShown = [];
foreach ($items as $item) {
    if ($item['kind'] == 'core') {
        $shown[$item['key']] = $item;
    } else {
        $customShown[] = $item['field'];
    }
}

$title = mb_substr(trim($_POST['title'] ?? ''), 0, 200);
$description = isset($shown['description']) ? trim($_POST['description'] ?? '') : '';
$priority = 'Medium';
if (isset($shown['priority']) && in_array($_POST['priority'] ?? '', ['Low', 'Medium', 'High', 'Urgent'], true)) {
    $priority = $_POST['priority'];
}
$dateStart = isset($shown['dateStart']) ? csParseDate($_POST['dateStart'] ?? '') : null;
$dateDue = isset($shown['dateDue']) ? csParseDate($_POST['dateDue'] ?? '') : null;

if ($title === '' || $dateStart === false || $dateDue === false || (!empty($dateStart) && !empty($dateDue) && $dateDue < $dateStart)) {
    csRedirect($session, 'cs_request_new.php', $formParams, 'error1');
}
foreach (['description' => $description, 'dateStart' => $dateStart, 'dateDue' => $dateDue] as $key => $value) {
    if (isset($shown[$key]) && $shown[$key]['required'] && empty($value)) {
        csRedirect($session, 'cs_request_new.php', $formParams, 'error1');
    }
}
if (isset($shown['attachments']) && $shown['attachments']['required'] && empty(Attachments::filesFromRequest())) {
    csRedirect($session, 'cs_request_new.php', $formParams, 'error1');
}

[$customData, $missing] = CustomFields::collect($customShown, $_POST);
if (!empty($missing)) {
    csRedirect($session, 'cs_request_new.php', $formParams, 'error1');
}

$person = csPersonID($session);
$csTicketID = $container->get(TicketGateway::class)->createTicket([
    'csTicketTypeID'         => $csTicketTypeID,
    'title'                  => $title,
    'description'            => $description,
    'status'                 => 'New',
    'priority'               => $priority,
    'dateStart'              => $dateStart ?: null,
    'dateDue'                => $dateDue ?: null,
    'gibbonPersonIDReporter' => $person,
    'csTeamID'               => $type['csTeamID'] ?: null,
    'customData'             => $customData,
]);

if (empty($csTicketID)) {
    csRedirect($session, 'cs_request_new.php', $formParams, 'error2');
}

$container->get(ActivityGateway::class)->log($csTicketID, $person, __('Request raised'));

$failedFiles = 0;
if (isset($shown['attachments'])) {
    $fileUploader = new Gibbon\FileUploader($pdo, $session);
    $failedFiles = Attachments::save($container->get(AttachmentGateway::class), $fileUploader, $csTicketID, null, $person);
}

(new Notifier($container))->ticketCreated($container->get(TicketGateway::class)->getTicket($csTicketID), $person);
(new AutomationEngine($container))->fire('ticket_created', $csTicketID);

// warning1: the request was saved, but at least one attachment was rejected (e.g. file type).
csRedirect($session, 'cs_ticket_view.php', ['csTicketID' => $csTicketID], $failedFiles > 0 ? 'warning1' : 'success0');
