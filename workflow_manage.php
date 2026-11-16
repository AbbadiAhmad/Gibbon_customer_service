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
use Gibbon\Module\ServiceDesk\Domain\StageGateway;
use Gibbon\Module\ServiceDesk\Domain\TicketTypeGateway;
use Gibbon\Module\ServiceDesk\Automation\AutomationRegistry;

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/workflow_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs->add(__m('Manage Workflows'));
$page->return->addReturns([
    'error4' => __m('Your request failed because tickets are in this stage. Move them to another stage first.'),
    'error5' => __m('Your request failed because a workflow needs at least one stage.'),
]);

// No ticket type = the default workflow.
$gibbonServiceDeskTicketTypeID = intval($_GET['gibbonServiceDeskTicketTypeID'] ?? 0) ?: '';
$types = [];
foreach ($container->get(TicketTypeGateway::class)->selectTypeOptions()->fetchKeyPair() as $id => $typeName) {
    $types[intval($id)] = __m($typeName);
}
if (!empty($gibbonServiceDeskTicketTypeID) && !isset($types[$gibbonServiceDeskTicketTypeID])) {
    $page->addError(__('The specified record cannot be found.'));
    return;
}

// CHOOSE WORKFLOW
$form = Form::createSearch();
$form->setClass('noIntBorder w-full');
$form->addHiddenValue('q', '/modules/ServiceDesk/workflow_manage.php');

$row = $form->addRow();
    $row->addLabel('gibbonServiceDeskTicketTypeID', __m('Workflow'));
    $row->addSelect('gibbonServiceDeskTicketTypeID')
        ->fromArray(['' => __m('Default workflow (copied to new ticket types)')])
        ->fromArray($types)
        ->selected($gibbonServiceDeskTicketTypeID);

$row = $form->addRow();
    $row->addSearchSubmit($session);

echo $form->getOutput();

// STAGES
$automations = $container->get(AutomationRegistry::class)->options();
$stages = $container->get(StageGateway::class)->selectStagesByType($gibbonServiceDeskTicketTypeID)->toDataSet();

$table = DataTable::create('stages');
$table->setTitle(empty($gibbonServiceDeskTicketTypeID) ? __m('Default Workflow') : __m('Workflow: {type}', ['type' => $types[$gibbonServiceDeskTicketTypeID]]));
$table->setDescription(empty($gibbonServiceDeskTicketTypeID)
    ? __m('Every new ticket type gets a copy of these stages. Changing them here does not change existing ticket types.')
    : __m('The stages of this ticket type. New tickets start in the first stage; team members move them between stages.'));

$table->addHeaderAction('add', __('Add'))
    ->setURL('/modules/ServiceDesk/workflow_stage_edit.php')
    ->addParam('gibbonServiceDeskTicketTypeID', $gibbonServiceDeskTicketTypeID)
    ->displayLabel();

$table->addColumn('sequenceNumber', __m('Order'))->width('8%');
$table->addColumn('name', __('Name'))->format(function ($stage) {
    return htmlspecialchars(__m($stage['name']));
});
$table->addColumn('category', __('Status'))->translatable();
$table->addColumn('flags', __m('Special'))->notSortable()->format(function ($stage) {
    return $stage['withdrawn'] == 'Y' ? Format::small(__m('receives withdrawn requests')) : '';
});
$table->addColumn('automation', __m('Automation on Entry'))->format(function ($stage) use ($automations) {
    if (empty($stage['automation'])) return '';

    $names = [];
    foreach (array_filter(explode(',', $stage['automation'])) as $key) {
        $names[] = isset($automations[$key])
            ? htmlspecialchars($automations[$key])
            : Format::tag(__m('Missing script: {script}', ['script' => $key]), 'error');
    }
    $output = implode('<br/>', $names);

    return $output.(!empty($stage['nextStage']) ? '<br/>'.Format::small(__m('then move to {stage}', ['stage' => htmlspecialchars(__m($stage['nextStage']))])) : '');
});

$table->addActionColumn()
    ->addParam('gibbonServiceDeskTicketTypeID', $gibbonServiceDeskTicketTypeID)
    ->addParam('gibbonServiceDeskStageID')
    ->format(function ($stage, $actions) {
        $actions->addAction('edit', __('Edit'))
            ->setURL('/modules/ServiceDesk/workflow_stage_edit.php');
        $actions->addAction('delete', __('Delete'))
            ->setURL('/modules/ServiceDesk/workflow_stage_delete.php');
    });

echo $table->render($stages);
