<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Data\Validator;
use Gibbon\Module\administration\Automation\AutomationEngine;
use Gibbon\Module\administration\Domain\ActivityGateway;
use Gibbon\Module\administration\Domain\AttachmentGateway;
use Gibbon\Module\administration\Domain\FieldGateway;
use Gibbon\Module\administration\Domain\FormFieldGateway;
use Gibbon\Module\administration\Domain\FormGateway;
use Gibbon\Module\administration\Domain\TicketGateway;
use Gibbon\Module\administration\Domain\TicketTypeGateway;
use Gibbon\Module\administration\Service\Attachments;
use Gibbon\Module\administration\Service\FormAccess;
use Gibbon\Module\administration\Service\FormMapper;
use Gibbon\Module\administration\Service\Notifier;

$_POST['address'] = '/modules/administration/cs_request_new.php';

require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

csRequirePost($session);

$_POST = $container->get(Validator::class)->sanitize($_POST);
$csFormID = $_POST['csFormID'] ?? '';

if (!csAccess($guid, $connection2, 'cs_request_new.php')) {
    csRedirect($session, 'cs_portal.php', [], 'error0');
}

$form = $container->get(FormGateway::class)->getByID($csFormID);
$type = empty($form) ? [] : $container->get(TicketTypeGateway::class)->getByID($form['csTicketTypeID']);
$isAdmin = csIsAdmin($guid, $connection2);

// Same rules as opening the form: it must exist, be active and be open to this user. The visibility belongs to the form.
if (empty($form) || empty($type) || (!$isAdmin && ($form['active'] != 'Y' || $type['active'] != 'Y'))) {
    csRedirect($session, 'cs_portal.php', [], 'error1');
}
if (!$isAdmin && !FormAccess::allows($form, csRoleCategory($session), $session->get('gibbonRoleIDCurrent'))) {
    csRedirect($session, 'cs_portal.php', [], 'error0');
}

$formParams = ['csFormID' => $csFormID];
$questions = $container->get(FormFieldGateway::class)->selectByForm($csFormID);
$typeFields = $container->get(FieldGateway::class)->selectByType($form['csTicketTypeID']);

// Only the questions of the form are read, and each answer is checked against the ticket field it fills in.
$uploaded = [];
foreach ($questions as $q) {
    if ($q['inputType'] === 'file' && !empty(Attachments::filesFromRequest(FormMapper::inputName($q['fieldKey'])))) {
        $uploaded[] = $q['fieldKey'];
    }
}
$ticket = FormMapper::map($form, $questions, $typeFields, $_POST, $uploaded);
if (!empty($ticket['errors'])) {
    csFlash($session, sprintf(__('Please check: %1$s'), implode(', ', $ticket['errors'])));
    csRedirect($session, 'cs_request_new.php', $formParams, 'error1');
}

$person = csPersonID($session);
$csTicketID = $container->get(TicketGateway::class)->createTicket([
    'csTicketTypeID'         => $form['csTicketTypeID'],
    'csFormID'               => $form['csFormID'],
    'title'                  => $ticket['title'],
    'description'            => $ticket['description'],
    'status'                 => 'New',
    'priority'               => $ticket['priority'],
    'dateStart'              => $ticket['dateStart'] ?: null,
    'dateDue'                => $ticket['dateDue'] ?: null,
    'gibbonPersonIDReporter' => $person,
    'csTeamID'               => $type['csTeamID'] ?: null,
    'customData'             => $ticket['customData'],
]);

if (empty($csTicketID)) {
    csRedirect($session, 'cs_request_new.php', $formParams, 'error2');
}

$container->get(ActivityGateway::class)->log($csTicketID, $person, sprintf(__('Request raised with the form "%1$s"'), $form['name']));

$failedFiles = 0;
$fileUploader = new Gibbon\FileUploader($pdo, $session);
foreach ($questions as $q) {
    if ($q['inputType'] === 'file') {
        $failedFiles += Attachments::save($container->get(AttachmentGateway::class), $fileUploader, $csTicketID, null, $person, FormMapper::inputName($q['fieldKey']));
    }
}

(new Notifier($container))->ticketCreated($container->get(TicketGateway::class)->getTicket($csTicketID), $person);
(new AutomationEngine($container))->fire('ticket_created', $csTicketID);

// warning1: the request was saved, but at least one attachment was rejected (e.g. file type).
csRedirect($session, 'cs_ticket_view.php', ['csTicketID' => $csTicketID, 'submitted' => 1], $failedFiles > 0 ? 'warning1' : 'success0');
