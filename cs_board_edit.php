<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;
use Gibbon\Module\CustomerService\Domain\BoardGateway;
use Gibbon\Module\CustomerService\Domain\SpaceGateway;
use Gibbon\Module\CustomerService\Service\BoardFilter;
use Gibbon\Module\CustomerService\Service\FilterForm;

require_once __DIR__ . '/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/CustomerService/cs_board_edit.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$csBoardID = $_GET['csBoardID'] ?? '';
$csSpaceID = $_GET['csSpaceID'] ?? '';
$isAdmin = csIsAdmin($guid, $connection2);
$boards = $container->get(BoardGateway::class);
$spaces = $container->get(SpaceGateway::class);

$values = ['name' => '', 'description' => '', 'filter' => null, 'active' => 'Y'];
$columnNames = ['New', 'In Progress', 'Waiting', 'Resolved', 'Closed'];

if (!empty($csBoardID)) {
    $values = $boards->getByID($csBoardID);
    if (empty($values)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }
    $csSpaceID = $values['csSpaceID'];
    $columnNames = array_column($boards->selectColumns($csBoardID), 'name');
}

$space = $spaces->getByID($csSpaceID);
if (empty($space)) {
    $page->addError(__('You have not specified one or more required parameters.'));
    return;
}
if (!$isAdmin && !$spaces->hasPermission(csPersonID($session), $csSpaceID, 'manage_boards')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs->add(__('Manage Spaces'), 'cs_space_manage.php');
$page->breadcrumbs->add($space['name'], 'cs_space_edit.php', ['csSpaceID' => $csSpaceID]);
$page->breadcrumbs->add(empty($csBoardID) ? __('Add Board') : __('Edit Board'));

$conditions = BoardFilter::decode($values['filter']);

$form = Form::create('csBoard', $session->get('absoluteURL').'/modules/CustomerService/cs_board_process.php');
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('csBoardID', $csBoardID);
$form->addHiddenValue('csSpaceID', $csSpaceID);

$row = $form->addRow();
    $row->addLabel('name', __('Name'));
    $row->addTextField('name')->maxLength(100)->required()->setValue($values['name']);

$row = $form->addRow();
    $row->addLabel('description', __('Description'));
    $row->addTextArea('description')->setRows(2)->setValue($values['description']);

$row = $form->addRow();
    $row->addLabel('active', __('Active'));
    $row->addYesNo('active')->required()->selected($values['active']);

$form->addRow()->addHeading('Filter', __('Filter'))
    ->append(__('The board shows the tickets that match ALL conditions below. Leave all rows empty to show every ticket. A custom field that a ticket type does not have is simply treated as empty - it never causes an error.').' '.FilterForm::help());

FilterForm::addRows($form, $container, $conditions);

$form->addRow()->addHeading('Columns', __('Columns'));
$row = $form->addRow();
    $row->addLabel('columns', __('Status Columns'))->description(__('One per line, left to right. A ticket moved into a column gets that status. New requests start with the status "New".'));
    $row->addTextArea('columns')->setRows(7)->required()->setValue(implode("\n", $columnNames));

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

echo $form->getOutput();
