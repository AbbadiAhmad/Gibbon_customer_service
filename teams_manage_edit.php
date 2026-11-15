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
use Gibbon\Module\ServiceDesk\Service\People;

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/teams_manage_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

// One page for add (no id) and edit.
$gibbonServiceDeskTeamID = $_GET['gibbonServiceDeskTeamID'] ?? '';
$teamGateway = $container->get(TeamGateway::class);

$page->breadcrumbs
    ->add(__m('Manage Teams'), 'teams_manage.php')
    ->add(empty($gibbonServiceDeskTeamID) ? __m('Add Team') : __m('Edit Team'));

$values = ['active' => 'Y'];
if (!empty($gibbonServiceDeskTeamID)) {
    $values = $teamGateway->getByID($gibbonServiceDeskTeamID);
    if (empty($values)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }
}

$form = Form::create('team', $session->get('absoluteURL').'/modules/ServiceDesk/teams_manage_editProcess.php');
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('gibbonServiceDeskTeamID', $gibbonServiceDeskTeamID);

$row = $form->addRow();
    $row->addLabel('name', __('Name'))->description(__('Must be unique.'));
    $row->addTextField('name')->maxLength(60)->required();

$row = $form->addRow();
    $row->addLabel('description', __('Description'));
    $row->addTextField('description')->maxLength(255);

$row = $form->addRow();
    $row->addLabel('active', __('Active'))->description(__m('Members of an inactive team no longer see its tickets.'));
    $row->addYesNo('active')->required();

// DEFAULT ASSIGNEE: optional, one of the team's members (so it can only be chosen once the team has members)
if (!empty($gibbonServiceDeskTeamID)) {
    $memberOptions = [];
    foreach ($teamGateway->selectMembersByTeam($gibbonServiceDeskTeamID)->fetchAll() as $person) {
        $memberOptions[$person['gibbonPersonID']] = Format::name('', $person['preferredName'], $person['surname'], 'Staff', true, true);
    }
    $row = $form->addRow();
        $row->addLabel('gibbonPersonIDDefaultAssignee', __m('Default Assignee'))->description(__m('New tickets of this team\'s ticket types are assigned to this member straight away. Leave empty and tickets start unassigned.'));
        $row->addSelect('gibbonPersonIDDefaultAssignee')->fromArray($memberOptions)->placeholder(__m('Nobody (tickets start unassigned)'));
}

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

$form->loadAllValuesFrom($values);

echo $form->getOutput();

if (empty($gibbonServiceDeskTeamID)) {
    echo Format::alert(__m('After saving you can add the members of the team.'), 'message');
    return;
}

// MEMBERS
$members = $teamGateway->selectMembersByTeam($gibbonServiceDeskTeamID)->fetchAll();

$table = DataTable::create('members');
$table->setTitle(__m('Members'));

$table->addColumn('name', __('Name'))->format(function ($person) {
    return Format::name($person['title'], $person['preferredName'], $person['surname'], 'Staff', true, true);
});
$table->addColumn('email', __('Email'));
$table->addColumn('remove', '')->width('15%')->format(function ($person) use ($session) {
    // A small POST form per row: removing a member is a change, so it carries the CSRF token.
    $form = Form::createBlank('memberRemove'.intval($person['gibbonServiceDeskTeamMemberID']), $session->get('absoluteURL').'/modules/ServiceDesk/teams_memberProcess.php');
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('action', 'remove');
    $form->addHiddenValue('gibbonServiceDeskTeamID', $person['gibbonServiceDeskTeamID']);
    $form->addHiddenValue('gibbonServiceDeskTeamMemberID', $person['gibbonServiceDeskTeamMemberID']);
    $form->addRow()->addSubmit(__m('Remove'));

    return $form->getOutput();
});

echo $table->render($members);

// ADD MEMBERS: staff who are not in the team yet
$staff = array_diff_key($container->get(People::class)->getStaffOptions(), array_column($members, null, 'gibbonPersonID'));

$form = Form::create('memberAdd', $session->get('absoluteURL').'/modules/ServiceDesk/teams_memberProcess.php');
$form->setTitle(__m('Add Members'));
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('action', 'add');
$form->addHiddenValue('gibbonServiceDeskTeamID', $gibbonServiceDeskTeamID);

$row = $form->addRow();
    $row->addLabel('people', __('Staff'))->description(__('Use Control, Command and/or Shift to select multiple.'));
    $row->addSelect('people')->fromArray($staff)->selectMultiple()->setSize(8)->required();

$row = $form->addRow();
    $row->addSubmit(__('Add'));

echo $form->getOutput();
