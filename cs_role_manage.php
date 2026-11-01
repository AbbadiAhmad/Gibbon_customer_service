<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Domain\DataSet;
use Gibbon\Tables\DataTable;
use Gibbon\Module\administration\Domain\RoleGateway;
use Gibbon\Module\administration\Service\Permissions;

require_once __DIR__ . '/moduleFunctions.php';

csSanitizeRequest($container);

if (!isActionAccessible($guid, $connection2, '/modules/administration/cs_role_manage.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs->add(__('Manage Roles'));

$roles = $container->get(RoleGateway::class)->selectAllWithPermissions();
$catalog = Permissions::catalog();

$table = DataTable::create('csRoles');
$table->setTitle(__('Roles'));
$table->addHeaderAction('add', __('Add'))->setURL('/modules/administration/cs_role_edit.php')->displayLabel();

$table->addColumn('name', __('Name'));
$table->addColumn('description', __('Description'));
$table->addColumn('permissions', __('Permissions'))->format(function ($role) use ($catalog) {
    $labels = array_map(function ($p) use ($catalog) {
        return $catalog[$p] ?? $p;
    }, $role['permissions']);
    return empty($labels) ? '<i>'.__('None').'</i>' : implode('<br/>', array_map('htmlspecialchars', $labels));
});
$table->addActionColumn()
    ->addParam('csRoleID')
    ->format(function ($role, $actions) {
        $actions->addAction('edit', __('Edit'))->setURL('/modules/administration/cs_role_edit.php');
        $actions->addAction('delete', __('Delete'))->setURL('/modules/administration/cs_delete.php')
            ->addParam('entity', 'role')->addParam('id', $role['csRoleID']);
    });

echo $table->render(new DataSet($roles));
