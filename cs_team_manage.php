<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Domain\DataSet;
use Gibbon\Tables\DataTable;
use Gibbon\Module\administration\Domain\TeamGateway;

require_once __DIR__ . '/moduleFunctions.php';

csSanitizeRequest($container);

if (!isActionAccessible($guid, $connection2, '/modules/administration/cs_team_manage.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs->add(__('Manage Teams'));

$teams = $container->get(TeamGateway::class)->selectAllWithCounts();

$table = DataTable::create('csTeams');
$table->setTitle(__('Teams'));
$table->addHeaderAction('add', __('Add'))->setURL('/modules/administration/cs_team_edit.php')->displayLabel();

$table->addColumn('name', __('Name'));
$table->addColumn('description', __('Description'));
$table->addColumn('memberCount', __('Members'));
$table->addColumn('active', __('Active'))->format(function ($t) {
    return $t['active'] == 'Y' ? __('Yes') : __('No');
});
$table->addActionColumn()
    ->addParam('csTeamID')
    ->format(function ($team, $actions) {
        $actions->addAction('edit', __('Edit'))->setURL('/modules/administration/cs_team_edit.php');
        $actions->addAction('delete', __('Delete'))->setURL('/modules/administration/cs_delete.php')
            ->addParam('entity', 'team')->addParam('id', $team['csTeamID']);
    });

echo $table->render(new DataSet($teams));
