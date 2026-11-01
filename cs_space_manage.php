<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Domain\DataSet;
use Gibbon\Tables\DataTable;
use Gibbon\Module\administration\Domain\SpaceGateway;

require_once __DIR__ . '/moduleFunctions.php';

csSanitizeRequest($container);

if (!isActionAccessible($guid, $connection2, '/modules/administration/cs_space_manage.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs->add(__('Manage Spaces'));

$isAdmin = csIsAdmin($guid, $connection2);
$spaces = $container->get(SpaceGateway::class)->selectAllWithCounts();

// Space admins see the spaces where their role lets them manage members or boards.
if (!$isAdmin) {
    $allowed = array_unique(array_merge(
        csManageableSpaceIDs($container, $session, false, 'manage_members'),
        csManageableSpaceIDs($container, $session, false, 'manage_boards')
    ));
    $spaces = array_filter($spaces, function ($space) use ($allowed) {
        return in_array((int)$space['csSpaceID'], $allowed, true);
    });
}

$table = DataTable::create('csSpaces');
$table->setTitle(__('Spaces'));
$table->addMetaData('blankSlate', __('There are no spaces you can manage.'));
if ($isAdmin) {
    $table->addHeaderAction('add', __('Add'))->setURL('/modules/administration/cs_space_edit.php')->displayLabel();
}

$table->addColumn('name', __('Name'));
$table->addColumn('description', __('Description'));
$table->addColumn('memberCount', __('Members'));
$table->addColumn('boardCount', __('Boards'));
$table->addColumn('active', __('Active'))->format(function ($s) {
    return $s['active'] == 'Y' ? __('Yes') : __('No');
});
$table->addActionColumn()
    ->addParam('csSpaceID')
    ->format(function ($space, $actions) use ($isAdmin) {
        $actions->addAction('edit', __('Edit'))->setURL('/modules/administration/cs_space_edit.php');
        if ($isAdmin) {
            $actions->addAction('delete', __('Delete'))->setURL('/modules/administration/cs_delete.php')
                ->addParam('entity', 'space')->addParam('id', $space['csSpaceID']);
        }
    });

echo $table->render(new DataSet(array_values($spaces)));
