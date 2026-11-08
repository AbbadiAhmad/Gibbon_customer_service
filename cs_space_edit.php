<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Domain\DataSet;
use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Module\administration\Domain\BoardGateway;
use Gibbon\Module\administration\Domain\PersonGateway;
use Gibbon\Module\administration\Domain\RoleGateway;
use Gibbon\Module\administration\Domain\SpaceGateway;
use Gibbon\Module\administration\Service\BoardFilter;

require_once __DIR__ . '/moduleFunctions.php';

csSanitizeRequest($container);

if (!isActionAccessible($guid, $connection2, '/modules/administration/cs_space_edit.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$csSpaceID = $_GET['csSpaceID'] ?? '';
$isAdmin = csIsAdmin($guid, $connection2);
$spaces = $container->get(SpaceGateway::class);
$values = ['name' => '', 'description' => '', 'active' => 'Y'];

$canMembers = $isAdmin;
$canBoards = $isAdmin;

if (!empty($csSpaceID)) {
    $values = $spaces->getByID($csSpaceID);
    if (empty($values)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }
    $person = csPersonID($session);
    $canMembers = $isAdmin || $spaces->hasPermission($person, $csSpaceID, 'manage_members');
    $canBoards = $isAdmin || $spaces->hasPermission($person, $csSpaceID, 'manage_boards');
    if (!$canMembers && !$canBoards) {
        $page->addError(__('You do not have access to this action.'));
        return;
    }
} elseif (!$isAdmin) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs->add(__('Manage Spaces'), 'cs_space_manage.php');
$page->breadcrumbs->add(empty($csSpaceID) ? __('Add Space') : $values['name']);

// ---- Space details (global admin only) ------------------------------------
if ($isAdmin) {
    $form = Form::create('csSpace', $session->get('absoluteURL').'/modules/administration/cs_space_process.php');
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('action', 'save');
    $form->addHiddenValue('csSpaceID', $csSpaceID);

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
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}

if (empty($csSpaceID)) {
    return; // Members and boards can be added once the space exists.
}

// ---- Members -----------------------------------------------------------------
if ($canMembers) {
    $members = $spaces->selectMembers($csSpaceID);

    $table = DataTable::create('csMembers');
    $table->setTitle(__('Members'));
    $table->addMetaData('blankSlate', __('This space has no members yet.'));
    $table->addColumn('name', __('Name'))->format(function ($m) {
        return htmlspecialchars(csPersonName($m['preferredName'], $m['surname'])).' <span class="text-gray-500">('.htmlspecialchars($m['username']).')</span>';
    });
    $table->addColumn('roleName', __('Role'));
    $table->addActionColumn()->format(function ($m, $actions) {
        $actions->addAction('delete', __('Remove'))->setURL('/modules/administration/cs_delete.php')
            ->addParam('entity', 'member')->addParam('id', $m['csSpaceMemberID']);
    });
    echo $table->render(new DataSet($members));

    $form = Form::create('csAddMember', $session->get('absoluteURL').'/modules/administration/cs_space_process.php');
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('action', 'addMember');
    $form->addHiddenValue('csSpaceID', $csSpaceID);
    $form->setTitle(__('Add Members'));

    $row = $form->addRow();
        $row->addLabel('users', __('Users'))->description(__('Hold Ctrl/Cmd to select several users.'));
        $row->addSelect('users')->fromArray($container->get(PersonGateway::class)->selectOptions())->selectMultiple()->required();

    $row = $form->addRow();
        $row->addLabel('roles', __('Roles'))->description(__('Every selected user gets every selected role.'));
        $row->addSelect('roles')->fromArray($container->get(RoleGateway::class)->selectOptions())->selectMultiple()->required();

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Add'));
    echo $form->getOutput();
}

// ---- Boards ------------------------------------------------------------------
if ($canBoards) {
    $boards = $container->get(BoardGateway::class)->selectBySpace($csSpaceID);

    $table = DataTable::create('csBoards');
    $table->setTitle(__('Boards'));
    $table->addMetaData('blankSlate', __('This space has no boards yet.'));
    $table->addHeaderAction('add', __('Add'))->setURL('/modules/administration/cs_board_edit.php')
        ->addParam('csSpaceID', $csSpaceID)->displayLabel();
    $table->addColumn('name', __('Name'));
    $table->addColumn('filter', __('Filter'))->format(function ($b) {
        return htmlspecialchars(BoardFilter::describe($b['filter']));
    });
    $table->addColumn('active', __('Active'))->format(function ($b) {
        return $b['active'] == 'Y' ? __('Yes') : __('No');
    });
    $table->addActionColumn()
        ->addParam('csBoardID')
        ->addParam('csSpaceID', $csSpaceID)
        ->format(function ($b, $actions) {
            $actions->addAction('view', __('View'))->setURL('/modules/administration/cs_board_view.php');
            $actions->addAction('edit', __('Edit'))->setURL('/modules/administration/cs_board_edit.php');
            $actions->addAction('delete', __('Delete'))->setURL('/modules/administration/cs_delete.php')
                ->addParam('entity', 'board')->addParam('id', $b['csBoardID']);
        });
    echo $table->render(new DataSet($boards));
}
