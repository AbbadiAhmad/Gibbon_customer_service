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
use Gibbon\Module\ServiceDesk\Domain\StageGateway;
use Gibbon\Module\ServiceDesk\Domain\TicketTypeGateway;
use Gibbon\Module\ServiceDesk\Service\TicketFilters;
use Gibbon\Module\ServiceDesk\Automation\AutomationRegistry;

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/workflow_stage_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

// One page for add (no stage id) and edit. No ticket type = the default workflow.
$gibbonServiceDeskTicketTypeID = $_GET['gibbonServiceDeskTicketTypeID'] ?? '';
$gibbonServiceDeskStageID = $_GET['gibbonServiceDeskStageID'] ?? '';
$stageGateway = $container->get(StageGateway::class);

if (!empty($gibbonServiceDeskTicketTypeID) && !$container->get(TicketTypeGateway::class)->exists($gibbonServiceDeskTicketTypeID)) {
    $page->addError(__('The specified record cannot be found.'));
    return;
}

$page->breadcrumbs
    ->add(__m('Manage Workflows'), 'workflow_manage.php', ['gibbonServiceDeskTicketTypeID' => $gibbonServiceDeskTicketTypeID])
    ->add(empty($gibbonServiceDeskStageID) ? __m('Add Stage') : __m('Edit Stage'));

$values = ['category' => 'In Progress', 'sequenceNumber' => 0];
if (!empty($gibbonServiceDeskStageID)) {
    $values = $stageGateway->getByID($gibbonServiceDeskStageID);
    if (empty($values) || intval($values['gibbonServiceDeskTicketTypeID']) !== intval($gibbonServiceDeskTicketTypeID)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }
}

$form = Form::create('stage', $session->get('absoluteURL').'/modules/ServiceDesk/workflow_stage_editProcess.php');
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('gibbonServiceDeskTicketTypeID', $gibbonServiceDeskTicketTypeID);
$form->addHiddenValue('gibbonServiceDeskStageID', $gibbonServiceDeskStageID);

$row = $form->addRow();
    $row->addLabel('name', __('Name'))->description(__m('Unique in this workflow.'));
    $row->addTextField('name')->maxLength(40)->required();

$row = $form->addRow();
    $row->addLabel('category', __('Status'))->description(__m('What the stage means for filters and lists: waiting, being worked on, or finished.'));
    $row->addSelect('category')->fromArray(array_combine(TicketFilters::STATUSES, array_map('__m', TicketFilters::STATUSES)))->required();

$row = $form->addRow();
    $row->addLabel('sequenceNumber', __m('Order'))->description(__m('New tickets start in the stage with the lowest number.'));
    $row->addNumber('sequenceNumber')->maxLength(4)->required();

$form->addRow()->addHeading(__m('Automation'))->append(__m('A script that runs when a ticket enters this stage. If it fails, the ticket stays in the stage it came from and the reason is written on the ticket as an internal note.'));

$registry = $container->get(AutomationRegistry::class);
$row = $form->addRow();
    $row->addLabel('automation', __m('Automation on Entry'));
    $row->addSelect('automation')->fromArray($registry->options())->placeholder(__m('None'));

$otherStages = [];
foreach ($stageGateway->selectStagesByType($gibbonServiceDeskTicketTypeID)->fetchAll() as $stage) {
    if ($stage['gibbonServiceDeskStageID'] != $gibbonServiceDeskStageID) {
        $otherStages[$stage['name']] = __m($stage['name']);
    }
}
$row = $form->addRow();
    $row->addLabel('nextStage', __m('Then Move To'))->description(__m('Where the ticket goes when the automation succeeded. Empty: it stays in this stage.'));
    $row->addSelect('nextStage')->fromArray($otherStages)->placeholder(__m('Stay in this stage'));

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

$form->loadAllValuesFrom($values);

echo $form->getOutput();

// What each script does and which fields it reads.
$descriptions = '';
foreach ($registry->all() as $script) {
    $descriptions .= '<li><b>'.htmlspecialchars($script->getName()).'</b>: '.htmlspecialchars($script->getDescription()).'</li>';
}
if ($descriptions !== '') {
    echo '<h3>'.__m('Available Automation Scripts').'</h3><ul>'.$descriptions.'</ul>';
}
