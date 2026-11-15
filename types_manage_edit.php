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

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/types_manage_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

// One page for add (no id) and edit.
$gibbonServiceDeskTicketTypeID = $_GET['gibbonServiceDeskTicketTypeID'] ?? '';
$ticketTypeGateway = $container->get(TicketTypeGateway::class);

$page->breadcrumbs
    ->add(__m('Manage Ticket Types'), 'types_manage.php')
    ->add(empty($gibbonServiceDeskTicketTypeID) ? __m('Add Ticket Type') : __m('Edit Ticket Type'));

$values = ['roleCategories' => 'Staff,Student,Parent,Other', 'active' => 'Y', 'sequenceNumber' => 0];
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
    $row->addLabel('color', __m('Colour'))->description(__m('Marks the tickets of this type in the list and on the board.'));
    $row->addColor('color');

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
