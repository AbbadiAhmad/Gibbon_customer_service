<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;
use Gibbon\Module\CustomerService\Domain\RoleGateway;
use Gibbon\Module\CustomerService\Service\Permissions;

require_once __DIR__ . '/moduleFunctions.php';

csSanitizeRequest($container);

if (!isActionAccessible($guid, $connection2, '/modules/CustomerService/cs_role_edit.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$csRoleID = $_GET['csRoleID'] ?? '';
$gateway = $container->get(RoleGateway::class);
$values = ['name' => '', 'description' => ''];
$granted = [Permissions::VIEW_BOARD, Permissions::COMMENT];

if (!empty($csRoleID)) {
    $values = $gateway->getByID($csRoleID);
    if (empty($values)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }
    $granted = $gateway->selectPermissions($csRoleID);
}

$page->breadcrumbs->add(__('Manage Roles'), 'cs_role_manage.php');
$page->breadcrumbs->add(empty($csRoleID) ? __('Add Role') : __('Edit Role'));

$form = Form::create('csRole', $session->get('absoluteURL').'/modules/CustomerService/cs_role_process.php');
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('csRoleID', $csRoleID);

$row = $form->addRow();
    $row->addLabel('name', __('Name'));
    $row->addTextField('name')->maxLength(100)->required()->setValue($values['name']);

$row = $form->addRow();
    $row->addLabel('description', __('Description'));
    $row->addTextArea('description')->setRows(3)->setValue($values['description']);

$row = $form->addRow();
    $row->addLabel('permissions', __('Permissions'))->description(__('What a user with this role may do in a space.'));
    $row->addCheckbox('permissions')->fromArray(Permissions::catalog())->checked($granted);

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

echo $form->getOutput();
