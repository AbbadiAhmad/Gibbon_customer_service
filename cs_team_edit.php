<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;
use Gibbon\Module\CustomerService\Domain\TeamGateway;

require_once __DIR__ . '/moduleFunctions.php';

csSanitizeRequest($container);

if (!isActionAccessible($guid, $connection2, '/modules/CustomerService/cs_team_edit.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$csTeamID = $_GET['csTeamID'] ?? '';
$gateway = $container->get(TeamGateway::class);
$values = ['name' => '', 'description' => '', 'active' => 'Y'];
$members = [];

if (!empty($csTeamID)) {
    $values = $gateway->getByID($csTeamID);
    if (empty($values)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }
    $members = $gateway->selectMemberIDs($csTeamID);
}

$page->breadcrumbs->add(__('Manage Teams'), 'cs_team_manage.php');
$page->breadcrumbs->add(empty($csTeamID) ? __('Add Team') : __('Edit Team'));

$form = Form::create('csTeam', $session->get('absoluteURL').'/modules/CustomerService/cs_team_process.php');
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('csTeamID', $csTeamID);

$row = $form->addRow();
    $row->addLabel('name', __('Name'));
    $row->addTextField('name')->maxLength(100)->required()->setValue($values['name']);

$row = $form->addRow();
    $row->addLabel('description', __('Description'));
    $row->addTextArea('description')->setRows(3)->setValue($values['description']);

$row = $form->addRow();
    $row->addLabel('active', __('Active'));
    $row->addYesNo('active')->required()->selected($values['active']);

$row = $form->addRow();
    $row->addLabel('members', __('Members'))->description(__('Hold Ctrl/Cmd to select several users.'));
    $row->addSelectUsers('members', $session->get('gibbonSchoolYearID'))->selectMultiple()->selected($members);

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

echo $form->getOutput();
