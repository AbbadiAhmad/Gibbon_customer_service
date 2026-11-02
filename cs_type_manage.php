<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Domain\DataSet;
use Gibbon\Tables\DataTable;
use Gibbon\Module\administration\Domain\TicketTypeGateway;

require_once __DIR__ . '/moduleFunctions.php';

csSanitizeRequest($container);

if (!isActionAccessible($guid, $connection2, '/modules/administration/cs_type_manage.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs->add(__('Manage Ticket Types'));

$types = $container->get(TicketTypeGateway::class)->selectAllWithCounts();

$table = DataTable::create('csTypes');
$table->setTitle(__('Ticket Types'));
$table->addHeaderAction('add', __('Add'))->setURL('/modules/administration/cs_type_edit.php')->displayLabel();

$table->addColumn('name', __('Name'));
$table->addColumn('description', __('Description'));
$table->addColumn('teamName', __('Default Team'));
$table->addColumn('fieldCount', __('Ticket Fields'));
$table->addColumn('formCount', __('Forms'));
$table->addColumn('active', __('Active'))->format(function ($t) {
    return $t['active'] == 'Y' ? __('Yes') : __('No');
});
$table->addActionColumn()
    ->addParam('csTicketTypeID')
    ->format(function ($type, $actions) {
        $actions->addAction('edit', __('Edit'))->setURL('/modules/administration/cs_type_edit.php');
        $actions->addAction('delete', __('Delete'))->setURL('/modules/administration/cs_delete.php')
            ->addParam('entity', 'type')->addParam('id', $type['csTicketTypeID']);
    });

echo $table->render(new DataSet($types));
