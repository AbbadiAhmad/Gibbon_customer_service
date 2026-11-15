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
use Gibbon\Module\ServiceDesk\Domain\TeamGateway;

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/teams_manage.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs->add(__m('Manage Teams'));

$teamGateway = $container->get(TeamGateway::class);

$criteria = $teamGateway->newQueryCriteria(true)
    ->sortBy(['name'])
    ->fromPOST();

$teams = $teamGateway->queryTeams($criteria);

$table = DataTable::createPaginated('teams', $criteria);
$table->setDescription(__m('The members of a team see and process the tickets of the ticket types given to the team (see Manage Ticket Types).'));

$table->addHeaderAction('add', __('Add'))
    ->setURL('/modules/ServiceDesk/teams_manage_edit.php')
    ->displayLabel();

$table->modifyRows(function ($team, $row) {
    return $team['active'] == 'N' ? $row->addClass('error') : $row;
});

$table->addColumn('name', __('Name'))
    ->format(function ($team) {
        return '<b>'.htmlspecialchars($team['name']).'</b><br/>'.Format::small(htmlspecialchars($team['description']));
    });
$table->addColumn('memberCount', __m('Members'))->notSortable();
$table->addColumn('defaultAssignee', __m('Default Assignee'))
    ->notSortable()
    ->format(function ($team) {
        return !empty($team['gibbonPersonIDDefaultAssignee']) ? Format::name('', $team['assigneePreferredName'], $team['assigneeSurname'], 'Staff', true, true) : '';
    });
$table->addColumn('ticketTypes', __m('Ticket Types'))
    ->notSortable()
    ->format(function ($team) {
        return htmlspecialchars((string) $team['ticketTypes']);
    });
$table->addColumn('active', __('Active'))->format(Format::using('yesNo', 'active'));

$table->addActionColumn()
    ->addParam('gibbonServiceDeskTeamID')
    ->format(function ($team, $actions) {
        $actions->addAction('edit', __('Edit'))
            ->setURL('/modules/ServiceDesk/teams_manage_edit.php');
        $actions->addAction('delete', __('Delete'))
            ->setURL('/modules/ServiceDesk/teams_manage_delete.php');
    });

echo $table->render($teams);
