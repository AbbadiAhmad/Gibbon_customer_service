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
use Gibbon\Services\Format;
use Gibbon\Module\ServiceDesk\Domain\TeamGateway;
use Gibbon\Module\ServiceDesk\Domain\StageGateway;
use Gibbon\Module\ServiceDesk\Domain\TicketGateway;
use Gibbon\Module\ServiceDesk\Domain\CommentGateway;
use Gibbon\Module\ServiceDesk\Domain\SubscriberGateway;
use Gibbon\Module\ServiceDesk\Service\TicketAccess;
use Gibbon\Module\ServiceDesk\Service\TicketService;
use Gibbon\Module\ServiceDesk\Service\RequestFields;

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/tickets_view.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs->add(__m('Tickets'), 'tickets.php');
$page->stylesheets->add('serviceDesk', 'modules/ServiceDesk/css/module.css');

$gibbonServiceDeskTicketID = $_GET['gibbonServiceDeskTicketID'] ?? '';
$gibbonPersonID = $session->get('gibbonPersonID');
$access = $container->get(TicketAccess::class);

// "Not found" and "not allowed" give the same answer.
$ticket = $container->get(TicketGateway::class)->getTicketByID($gibbonServiceDeskTicketID);
if (empty($ticket) || !$access->canView($ticket)) {
    $page->addError(__('The specified record cannot be found.'));
    return;
}

$canManage = $access->canManage($ticket);
$page->breadcrumbs->add('#'.intval($gibbonServiceDeskTicketID));

// TICKET: details, answers of the request form, history (internal entries only for the team)
$comments = $container->get(CommentGateway::class)->selectCommentsByTicket($gibbonServiceDeskTicketID, $canManage)->fetchAll();

echo $page->fetchFromTemplate('ticket.twig.html', [
    'ticket'    => $ticket,
    'number'    => intval($gibbonServiceDeskTicketID),
    'requester' => Format::name('', $ticket['requesterPreferredName'], $ticket['requesterSurname'], 'Student', false),
    'assignee'  => !empty($ticket['gibbonPersonIDAssignee']) ? Format::name('', $ticket['assigneePreferredName'], $ticket['assigneeSurname'], 'Student', false) : '',
    'fields'    => RequestFields::describe($ticket['fields']),
    'comments'  => $comments,
    'canManage' => $canManage,
]);

$processURL = $session->get('absoluteURL').'/modules/ServiceDesk/tickets_viewProcess.php';

// COMMENT
$form = Form::create('comment', $processURL);
$form->setTitle(__m('Add Comment'));
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('gibbonServiceDeskTicketID', $gibbonServiceDeskTicketID);
$form->addHiddenValue('action', 'comment');

$row = $form->addRow();
    $row->addTextArea('comment')->setRows(4)->required()->setClass('w-full');

if ($canManage) {
    $row = $form->addRow();
        $row->addLabel('type', __m('Visibility'))->description(__m('Internal notes are only seen by the team.'));
        $row->addSelect('type')->fromArray(['Comment' => __m('Comment (visible to the requester)'), 'Internal Note' => __m('Internal note')]);
}

$row = $form->addRow();
    $row->addSubmit(__m('Add Comment'));

echo $form->getOutput();

// MANAGE: stage, assignee, priority (team members and administrators)
if ($canManage) {
    $stages = $container->get(StageGateway::class)->selectStagesByType($ticket['gibbonServiceDeskTicketTypeID'])->fetchAll();
    $stageOptions = [];
    foreach ($stages as $stage) {
        $stageOptions[$stage['gibbonServiceDeskStageID']] = __m($stage['name']).(!empty($stage['automation']) ? ' *' : '');
    }

    $people = [];
    foreach ($container->get(TeamGateway::class)->selectMembersByTicketType($ticket['gibbonServiceDeskTicketTypeID'])->fetchAll() as $person) {
        $people[$person['gibbonPersonID']] = Format::name('', $person['preferredName'], $person['surname'], 'Staff', true, true);
    }
    if (!empty($ticket['gibbonPersonIDAssignee']) && !isset($people[$ticket['gibbonPersonIDAssignee']])) {
        $people[$ticket['gibbonPersonIDAssignee']] = Format::name('', $ticket['assigneePreferredName'], $ticket['assigneeSurname'], 'Staff', true, true);
    }

    $form = Form::create('manage', $processURL);
    $form->setTitle(__m('Manage Ticket'));
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('gibbonServiceDeskTicketID', $gibbonServiceDeskTicketID);
    $form->addHiddenValue('action', 'manage');

    $row = $form->addRow();
        $row->addLabel('gibbonServiceDeskStageID', __m('Stage'))->description(__m('Stages marked * run an automation when the ticket enters them.'));
        $row->addSelect('gibbonServiceDeskStageID')->fromArray($stageOptions)->selected($ticket['gibbonServiceDeskStageID'])->required();

    $row = $form->addRow();
        $row->addLabel('gibbonPersonIDAssignee', __m('Assignee'))->description(__m('Members of the teams that handle this ticket type.'));
        $row->addSelect('gibbonPersonIDAssignee')->fromArray($people)->placeholder(__m('Unassigned'))->selected($ticket['gibbonPersonIDAssignee'] ?? '');

    $row = $form->addRow();
        $row->addLabel('priority', __m('Priority'));
        $row->addSelect('priority')->fromArray(array_combine(TicketService::PRIORITIES, array_map('__m', TicketService::PRIORITIES)))->selected($ticket['priority'])->required();

    $row = $form->addRow();
        $row->addSubmit(__m('Update Ticket'));

    echo $form->getOutput();
}

// NOTIFICATIONS for this ticket: the user's copy of their notification policy, or the chance to start following
$subscriberGateway = $container->get(SubscriberGateway::class);
$subscription = $subscriberGateway->getSubscription($gibbonServiceDeskTicketID, $gibbonPersonID);

$form = Form::create('notify', $processURL);
$form->setTitle(__m('My Notifications for this Ticket'));
$form->setDescription(empty($subscription)
    ? __m('You are not following this ticket. Choose a channel to be told about its updates.')
    : __m('This only changes this ticket. Your general choice is in Notification Settings.'));
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('gibbonServiceDeskTicketID', $gibbonServiceDeskTicketID);
$form->addHiddenValue('action', 'notify');

$row = $form->addRow();
    $row->addLabel('notifyInApp', __m('Notification Centre'));
    $row->addYesNo('notifyInApp')->selected($subscription['notifyInApp'] ?? 'N');

$row = $form->addRow();
    $row->addLabel('notifyEmail', __('Email'));
    $row->addYesNo('notifyEmail')->selected($subscription['notifyEmail'] ?? 'N');

$row = $form->addRow();
    $row->addSubmit();

echo $form->getOutput();
