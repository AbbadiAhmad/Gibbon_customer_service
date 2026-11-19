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
use Gibbon\Tables\DataTable;
use Gibbon\Module\ServiceDesk\Domain\TeamGateway;
use Gibbon\Module\ServiceDesk\Domain\FieldGateway;
use Gibbon\Module\ServiceDesk\Domain\TicketTypeGateway;
use Gibbon\Module\ServiceDesk\Domain\TypeNotificationGateway;

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/types_manage_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

// One page for add (no id) and edit.
$gibbonServiceDeskTicketTypeID = intval($_GET['gibbonServiceDeskTicketTypeID'] ?? 0) ?: '';
$ticketTypeGateway = $container->get(TicketTypeGateway::class);

$page->breadcrumbs
    ->add(__m('Manage Ticket Types'), 'types_manage.php')
    ->add(empty($gibbonServiceDeskTicketTypeID) ? __m('Add Ticket Type') : __m('Edit Ticket Type'));

$values = ['roleCategories' => 'Staff,Student,Parent,Other', 'shareWithFamily' => 'N', 'allowWithdraw' => 'Y', 'allowReopen' => 'Y', 'retentionDays' => '', 'active' => 'Y', 'sequenceNumber' => 0];
$teamIDs = [];
if (!empty($gibbonServiceDeskTicketTypeID)) {
    $values = $ticketTypeGateway->getByID($gibbonServiceDeskTicketTypeID);
    if (empty($values)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }
    $teamIDs = $ticketTypeGateway->selectTeamIDsByType($gibbonServiceDeskTicketTypeID);
}

$form = Form::create('ticketType', $session->get('absoluteURL').'/modules/ServiceDesk/types_manage_editProcess.php');
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('gibbonServiceDeskTicketTypeID', $gibbonServiceDeskTicketTypeID);

$row = $form->addRow();
    $row->addLabel('name', __('Name'))->description(__('Must be unique.'));
    $row->addTextField('name')->maxLength(60)->required();

$row = $form->addRow();
    $row->addLabel('description', __('Description'))->description(__m('Shown to people choosing a service.'));
    $row->addTextField('description')->maxLength(255);

$categories = ['Staff' => __('Staff'), 'Student' => __('Student'), 'Parent' => __('Parent'), 'Other' => __('Other')];
$row = $form->addRow();
    $row->addLabel('roleCategories', __m('Requested By'))->description(__m('Who can send this kind of request.'));
    $row->addCheckbox('roleCategories')->fromArray($categories)->checked(explode(',', $values['roleCategories']));

$teams = $container->get(TeamGateway::class)->selectTeamOptions()->fetchKeyPair();
$row = $form->addRow();
    $row->addLabel('teams', __m('Teams'))->description(__m('The teams whose members see and process these tickets.'));
    $row->addCheckbox('teams')->fromArray($teams)->checked($teamIDs);

$row = $form->addRow();
    $row->addLabel('shareWithFamily', __m('Share With Family'))->description(__m('The other adults of the requester\'s family (with data access) also see these tickets, comment on them, withdraw and reopen them. Leave off for private matters such as complaints.'));
    $row->addYesNo('shareWithFamily')->required();

$row = $form->addRow();
    $row->addLabel('allowWithdraw', __m('Requesters Can Withdraw'))->description(__m('The requester can withdraw the request themselves while it is open or in progress (it moves to the workflow\'s withdrawn stage). Off: only the team can close it. What an automation already did, such as a recorded absence, is not undone by withdrawing.'));
    $row->addYesNo('allowWithdraw')->required();

$row = $form->addRow();
    $row->addLabel('allowReopen', __m('Requesters Can Reopen'))->description(__m('The requester can reopen the request themselves for {days} days after it was closed. Off: they send a new request.', ['days' => \Gibbon\Module\ServiceDesk\Service\TicketAccess::REOPEN_DAYS]));
    $row->addYesNo('allowReopen')->required();

$row = $form->addRow();
    $row->addLabel('color', __m('Colour'))->description(__m('Marks the tickets of this type in the list and on the board.'));
    $row->addColor('color');

$row = $form->addRow();
    $row->addLabel('retentionDays', __m('Retention (days)'))->description(__m('Closed tickets of this type are deleted this many days after they were closed, by the scheduled script cli_deleteExpiredTickets.php (see the README). Empty keeps them for ever.'));
    $row->addNumber('retentionDays')->minimum(1)->maxLength(5);

$row = $form->addRow();
    $row->addLabel('sequenceNumber', __m('Order'))->description(__m('Position in the list of services.'));
    $row->addNumber('sequenceNumber')->maxLength(4)->required();

$row = $form->addRow();
    $row->addLabel('active', __('Active'));
    $row->addYesNo('active')->required();

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

// loadAllValuesFrom() also sets the checkbox group: it must get the list, not the stored comma-separated text
// (given the text it ticks nothing, and saving the form would then remove every role category).
$values['roleCategories'] = explode(',', $values['roleCategories']);
$form->loadAllValuesFrom($values);

echo $form->getOutput();

if (empty($gibbonServiceDeskTicketTypeID)) {
    echo Format::alert(__m('After saving you can build the request form. The new type starts with a copy of the default workflow.'), 'message');
    return;
}

// NOTIFIED STAFF: people assigned to this type are told about its tickets. They start with their own general channels
// and can change the four switches themselves in Notification Settings.
$typeNotificationGateway = $container->get(TypeNotificationGateway::class);
$notified = $typeNotificationGateway->selectByType($gibbonServiceDeskTicketTypeID)->fetchAll();
$notifiedIDs = array_map('intval', array_column($notified, 'gibbonPersonID'));
$eligible = [];
foreach ($container->get(TeamGateway::class)->selectNotifiableStaff($gibbonServiceDeskTicketTypeID)->fetchAll() as $person) {
    if (!in_array(intval($person['gibbonPersonID']), $notifiedIDs, true)) {
        $eligible[$person['gibbonPersonID']] = Format::name('', $person['preferredName'], $person['surname'], 'Staff', true, true);
    }
}
$current = [];
foreach ($notified as $person) {
    $yes = [];
    foreach (['newInApp' => __m('new: centre'), 'newEmail' => __m('new: email'), 'updateInApp' => __m('updates: centre'), 'updateEmail' => __m('updates: email')] as $flag => $label) {
        if ($person[$flag] == 'Y') $yes[] = $label;
    }
    $current[$person['gibbonPersonID']] = Format::name('', $person['preferredName'], $person['surname'], 'Staff', true, true).' ('.implode(', ', $yes).')';
}

$form = Form::create('typeNotified', $session->get('absoluteURL').'/modules/ServiceDesk/types_notifyProcess.php');
$form->setTitle(__m('Notified Staff'));
$form->setDescription(__m('Staff are only told about tickets of this type when they are assigned to the ticket, follow it, or are listed here. People you add here are told about new tickets and updates through the channels of their own notification settings; they can change this under Notification Settings. Candidates are the members of the teams of this type and the administrators.'));
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('gibbonServiceDeskTicketTypeID', $gibbonServiceDeskTicketTypeID);

$row = $form->addRow();
    $row->addLabel('add', __m('Notify'))->description(__m('Choose the people to add.'));
    $row->addSelect('add')->fromArray($eligible)->selectMultiple();

if (!empty($current)) {
    $row = $form->addRow();
        $row->addLabel('remove', __m('Remove'))->description(__m('Tick the people who should no longer be told about this type.'));
        $row->addCheckbox('remove')->fromArray($current);
}

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

echo $form->getOutput();

// REQUEST FORM: the fields people fill in
$fields = $container->get(FieldGateway::class)->selectFieldsByType($gibbonServiceDeskTicketTypeID)->toDataSet();

$table = DataTable::create('fields');
$table->setTitle(__m('Request Form'));
$table->setDescription(__m('The questions people answer when they send this request. A subject line is always offered. The field name is what automation scripts use to read the answer.'));

$table->addHeaderAction('add', __('Add'))
    ->setURL('/modules/ServiceDesk/types_field_edit.php')
    ->addParam('gibbonServiceDeskTicketTypeID', $gibbonServiceDeskTicketTypeID)
    ->displayLabel();

$table->addHeaderAction('workflow', __m('Workflow'))
    ->setIcon('run')
    ->setURL('/modules/ServiceDesk/workflow_manage.php')
    ->addParam('gibbonServiceDeskTicketTypeID', $gibbonServiceDeskTicketTypeID)
    ->displayLabel();

$table->addColumn('sequenceNumber', __m('Order'))->width('8%');
$table->addColumn('label', __m('Label'))->format(function ($field) {
    return htmlspecialchars(__m($field['label']));
});
$table->addColumn('name', __m('Field Name'));
$table->addColumn('type', __('Type'))->format(function ($field) {
    return __m($field['type']).($field['type'] == 'Select' ? '<br/>'.Format::small(htmlspecialchars($field['options'])) : '');
});
$table->addColumn('required', __m('Required'))->format(Format::using('yesNo', 'required'));

$table->addActionColumn()
    ->addParam('gibbonServiceDeskTicketTypeID', $gibbonServiceDeskTicketTypeID)
    ->addParam('gibbonServiceDeskFieldID')
    ->format(function ($field, $actions) {
        $actions->addAction('edit', __('Edit'))
            ->setURL('/modules/ServiceDesk/types_field_edit.php');
        $actions->addAction('delete', __('Delete'))
            ->setURL('/modules/ServiceDesk/types_field_delete.php');
    });

echo $table->render($fields);
