<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/

use Gibbon\Forms\Form;
use Gibbon\Module\ServiceDesk\Domain\TicketGateway;
use Gibbon\Module\ServiceDesk\Service\TicketAccess;

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/tickets_requester.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

$gibbonServiceDeskTicketID = $_GET['gibbonServiceDeskTicketID'] ?? '';
$action = $_GET['action'] ?? '';
$access = $container->get(TicketAccess::class);

// "Not found" and "not allowed" give the same answer; so does a ticket that cannot be withdrawn / reopened now.
$ticket = $container->get(TicketGateway::class)->getTicketByID($gibbonServiceDeskTicketID);
$allowed = !empty($ticket) && (($action == 'withdraw' && $access->canWithdraw($ticket)) || ($action == 'reopen' && $access->canReopen($ticket)));

$page->breadcrumbs->add(__m('Tickets'), 'tickets.php');

if (!$allowed) {
    $page->addError(__('The specified record cannot be found.'));
    return;
}

$page->breadcrumbs
    ->add('#'.intval($gibbonServiceDeskTicketID), 'tickets_view.php', ['gibbonServiceDeskTicketID' => $gibbonServiceDeskTicketID])
    ->add($action == 'withdraw' ? __m('Withdraw Request') : __m('Reopen Request'));

$form = Form::create('requesterAction', $session->get('absoluteURL').'/modules/ServiceDesk/tickets_requesterProcess.php');
$form->setTitle($action == 'withdraw' ? __m('Withdraw Request') : __m('Reopen Request'));
$form->setDescription(htmlspecialchars('#'.intval($gibbonServiceDeskTicketID).' '.$ticket['subject']).'<br/>'.($action == 'withdraw'
    ? __m('The request is closed as withdrawn and the team is told. You can reopen it for {days} days.', ['days' => TicketAccess::REOPEN_DAYS])
    : __m('The request goes back to the start of its workflow and the team is told.')));
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('gibbonServiceDeskTicketID', $gibbonServiceDeskTicketID);
$form->addHiddenValue('action', $action);

$row = $form->addRow();
    $row->addLabel('note', __m('Note'))->description(__m('Optional: tell the team why. It is added as a comment.'));
    $row->addTextArea('note')->setRows(4);

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit($action == 'withdraw' ? __m('Withdraw Request') : __m('Reopen Request'));

echo $form->getOutput();
