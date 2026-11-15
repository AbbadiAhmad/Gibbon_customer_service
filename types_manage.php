<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/

use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Module\ServiceDesk\Domain\TicketTypeGateway;

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/types_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs->add(__m('Manage Ticket Types'));
$page->return->addReturns(['error4' => __m('Your request failed because this ticket type has tickets. Make it inactive instead.')]);

$ticketTypeGateway = $container->get(TicketTypeGateway::class);

$criteria = $ticketTypeGateway->newQueryCriteria(true)
    ->sortBy(['sequenceNumber', 'name'])
    ->fromPOST();

$types = $ticketTypeGateway->queryTicketTypes($criteria);

$table = DataTable::createPaginated('ticketTypes', $criteria);
$table->setDescription(__m('A ticket type is a service people can request. It has its own request form, the teams that handle it, and its own workflow.'));

$table->addHeaderAction('add', __('Add'))
    ->setURL('/modules/ServiceDesk/types_manage_edit.php')
    ->displayLabel();

$table->modifyRows(function ($type, $row) {
    return $type['active'] == 'N' ? $row->addClass('error') : $row;
});

$table->addColumn('sequenceNumber', __m('Order'))->width('8%');
$table->addColumn('name', __('Name'))
    ->format(function ($type) {
        return '<b>'.htmlspecialchars(__m($type['name'])).'</b><br/>'.Format::small(htmlspecialchars(__m($type['description'])));
    });
$table->addColumn('teams', __m('Teams'))
    ->notSortable()
    ->format(function ($type) {
        return !empty($type['teams']) ? htmlspecialchars($type['teams']) : Format::tag(__m('No team'), 'error');
    });
$table->addColumn('roleCategories', __m('Requested By'))
    ->notSortable()
    ->format(function ($type) {
        return implode(', ', array_map('__', array_filter(explode(',', $type['roleCategories']))));
    });
$table->addColumn('retentionDays', __m('Retention'))
    ->notSortable()
    ->format(function ($type) {
        return !empty($type['retentionDays']) ? __m('{days} days', ['days' => intval($type['retentionDays'])]) : '';
    });
$table->addColumn('active', __('Active'))->format(Format::using('yesNo', 'active'));

$table->addActionColumn()
    ->addParam('gibbonServiceDeskTicketTypeID')
    ->format(function ($type, $actions) {
        $actions->addAction('edit', __('Edit'))
            ->setURL('/modules/ServiceDesk/types_manage_edit.php');
        $actions->addAction('workflow', __m('Workflow'))
            ->setIcon('run')
            ->setURL('/modules/ServiceDesk/workflow_manage.php');
        $actions->addAction('delete', __('Delete'))
            ->setURL('/modules/ServiceDesk/types_manage_delete.php');
    });

echo $table->render($types);
