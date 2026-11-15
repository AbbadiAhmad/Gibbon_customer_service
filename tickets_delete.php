<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/

use Gibbon\Forms\Prefab\DeleteForm;
use Gibbon\Services\Format;
use Gibbon\Module\ServiceDesk\Domain\TicketGateway;
use Gibbon\Module\ServiceDesk\Service\TicketAccess;

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/tickets_delete.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs->add(__m('Tickets'), 'tickets.php');

// The permission is not enough: the person must also be able to see the ticket. Otherwise "not found".
$gibbonServiceDeskTicketID = $_GET['gibbonServiceDeskTicketID'] ?? '';
$ticket = $container->get(TicketGateway::class)->getTicketByID($gibbonServiceDeskTicketID);

if (empty($ticket) || !$container->get(TicketAccess::class)->canDelete($ticket)) {
    $page->addError(__('The specified record cannot be found.'));
    return;
}

$page->breadcrumbs
    ->add('#'.intval($gibbonServiceDeskTicketID), 'tickets_view.php', ['gibbonServiceDeskTicketID' => $gibbonServiceDeskTicketID])
    ->add(__m('Delete Ticket'));

echo '<h3>#'.intval($gibbonServiceDeskTicketID).' '.htmlspecialchars($ticket['subject']).'</h3>';
echo Format::alert(__m('The ticket, its comments, its notification settings and the notifications written about it are deleted for good. This cannot be undone. Changes the workflow made elsewhere in Gibbon (attendance, enrolment) stay as they are. A line in the system log records that the ticket was deleted, and by whom - not what it said.'), 'warning');

$form = DeleteForm::createForm($session->get('absoluteURL').'/modules/ServiceDesk/tickets_deleteProcess.php');
$form->addHiddenValue('gibbonServiceDeskTicketID', $gibbonServiceDeskTicketID);
echo $form->getOutput();
