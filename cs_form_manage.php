<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Domain\DataSet;
use Gibbon\Tables\DataTable;
use Gibbon\Module\administration\Domain\FormGateway;
use Gibbon\Module\administration\Service\FormAccess;

require_once __DIR__ . '/moduleFunctions.php';

csSanitizeRequest($container);

if (!csAccess($guid, $connection2, 'cs_form_manage.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs->add(__('Manage Forms'));

echo '<p class="text-gray-600">'.__('A form is what people fill in to raise a ticket. It has its own questions; every question fills in one field of the ticket. The ticket type behind it decides the team, the workflow and the extra ticket fields. Who can use a form is set on the form.').'</p>';

$forms = $container->get(FormGateway::class);
$roleNames = $forms->selectRoleOptions();

$table = DataTable::create('csForms');
$table->setTitle(__('Forms'));
$table->addMetaData('blankSlate', __('There are no forms yet.'));
$table->addHeaderAction('add', __('Add'))->setURL('/modules/administration/cs_form_edit.php')->displayLabel();

$table->addColumn('name', __('Name'))->format(function ($f) {
    return ($f['icon'] ? htmlspecialchars($f['icon']).' ' : '').'<b>'.htmlspecialchars($f['name']).'</b>'.($f['groupName'] ? '<br/><span class="text-gray-600 text-xs">'.htmlspecialchars($f['groupName']).'</span>' : '');
});
$table->addColumn('typeName', __('Creates tickets of type'));
$table->addColumn('audience', __('Who can use it'))->format(function ($f) use ($roleNames) {
    return htmlspecialchars(FormAccess::summary($f, $roleNames));
});
$table->addColumn('fieldCount', __('Questions'));
$table->addColumn('active', __('Active'))->format(function ($f) {
    return $f['active'] == 'Y' ? __('Yes') : __('No');
});
$table->addActionColumn()
    ->addParam('csFormID')
    ->format(function ($form, $actions) {
        $actions->addAction('edit', __('Edit'))->setURL('/modules/administration/cs_form_edit.php');
        $actions->addAction('view', __('Open the form'))->setURL('/modules/administration/cs_request_new.php');
        $actions->addAction('delete', __('Delete'))->setURL('/modules/administration/cs_delete.php')
            ->addParam('entity', 'form')->addParam('id', $form['csFormID']);
    });

echo $table->render(new DataSet($forms->selectAllWithCounts()));
