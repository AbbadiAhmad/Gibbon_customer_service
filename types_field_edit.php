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
use Gibbon\Module\ServiceDesk\Domain\FieldGateway;
use Gibbon\Module\ServiceDesk\Domain\TicketTypeGateway;
use Gibbon\Module\ServiceDesk\Service\RequestFields;

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/types_field_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

// One page for add (no field id) and edit.
$gibbonServiceDeskTicketTypeID = intval($_GET['gibbonServiceDeskTicketTypeID'] ?? 0) ?: '';
$gibbonServiceDeskFieldID = intval($_GET['gibbonServiceDeskFieldID'] ?? 0) ?: '';

$type = $container->get(TicketTypeGateway::class)->getByID($gibbonServiceDeskTicketTypeID);
if (empty($type)) {
    $page->addError(__('The specified record cannot be found.'));
    return;
}

$page->breadcrumbs
    ->add(__m('Manage Ticket Types'), 'types_manage.php')
    ->add(__m($type['name']), 'types_manage_edit.php', ['gibbonServiceDeskTicketTypeID' => $gibbonServiceDeskTicketTypeID])
    ->add(empty($gibbonServiceDeskFieldID) ? __m('Add Field') : __m('Edit Field'));

$values = ['type' => 'Text', 'required' => 'N', 'sequenceNumber' => 0];
if (!empty($gibbonServiceDeskFieldID)) {
    $values = $container->get(FieldGateway::class)->getByID($gibbonServiceDeskFieldID);
    if (empty($values) || $values['gibbonServiceDeskTicketTypeID'] != $type['gibbonServiceDeskTicketTypeID']) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }
}

$form = Form::create('field', $session->get('absoluteURL').'/modules/ServiceDesk/types_field_editProcess.php');
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('gibbonServiceDeskTicketTypeID', $gibbonServiceDeskTicketTypeID);
$form->addHiddenValue('gibbonServiceDeskFieldID', $gibbonServiceDeskFieldID);

$row = $form->addRow();
    $row->addLabel('label', __m('Label'))->description(__m('The question as people see it.'));
    $row->addTextField('label')->maxLength(90)->required();

$row = $form->addRow();
    $row->addLabel('name', __m('Field Name'))->description(__m('Letters and digits, unique in this type. Automation scripts read the answer by this name (for example: students, dateStart, dateEnd, reason, classes). Leave empty to derive it from the label.'));
    $row->addTextField('name')->maxLength(40);

$row = $form->addRow();
    $row->addLabel('description', __('Description'))->description(__m('Optional help text under the label.'));
    $row->addTextField('description')->maxLength(255);

$types = [
    'Text'      => __m('Text'),
    'Long Text' => __m('Long Text'),
    'Date'      => __m('Date'),
    'Select'    => __m('Select (choose one option)'),
    'Yes/No'    => __m('Yes/No'),
    'Students'  => __m('Students (parents choose among their children)'),
    'Classes'   => __m('Classes (the classes of those students)'),
];
$row = $form->addRow();
    $row->addLabel('type', __('Type'));
    $row->addSelect('type')->fromArray($types)->required();

$form->toggleVisibilityByClass('fieldOptions')->onSelect('type')->when('Select');
$row = $form->addRow()->addClass('fieldOptions');
    $row->addLabel('options', __m('Options'))->description(__m('Comma separated.'));
    $row->addTextArea('options')->setRows(3);

$row = $form->addRow();
    $row->addLabel('required', __m('Required'));
    $row->addYesNo('required')->required();

$row = $form->addRow();
    $row->addLabel('sequenceNumber', __m('Order'));
    $row->addNumber('sequenceNumber')->maxLength(4)->required();

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

$form->loadAllValuesFrom($values);

echo $form->getOutput();
