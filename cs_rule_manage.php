<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Domain\DataSet;
use Gibbon\Tables\DataTable;
use Gibbon\Module\CustomerService\Automation\ActionRegistry;
use Gibbon\Module\CustomerService\Automation\AutomationEngine;
use Gibbon\Module\CustomerService\Domain\RuleGateway;
use Gibbon\Module\CustomerService\Service\BoardFilter;

require_once __DIR__ . '/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/CustomerService/cs_rule_manage.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs->add(__('Manage Automation'));

echo '<p class="text-gray-600">'.__('A rule runs when its trigger happens to a ticket, if the ticket matches all conditions. Then it runs its actions in order. Rules of a trigger run in the order given below.').'</p>';

$rules = $container->get(RuleGateway::class)->selectAllWithType();
$triggers = AutomationEngine::triggers();

$table = DataTable::create('csRules');
$table->setTitle(__('Automation Rules'));
$table->addMetaData('blankSlate', __('There are no rules yet.'));
$table->addHeaderAction('add', __('Add'))->setURL('/modules/CustomerService/cs_rule_edit.php')->displayLabel();

$table->addColumn('name', __('Name'));
$table->addColumn('triggerKey', __('When'))->format(function ($r) use ($triggers) {
    return htmlspecialchars($triggers[$r['triggerKey']] ?? $r['triggerKey']);
});
$table->addColumn('typeName', __('Ticket Type'))->format(function ($r) {
    return htmlspecialchars($r['typeName'] ?? __('All types'));
});
$table->addColumn('conditions', __('If'))->format(function ($r) {
    return htmlspecialchars(BoardFilter::describe($r['conditions']));
});
$table->addColumn('actions', __('Then'))->format(function ($r) use ($container) {
    $lines = [];
    foreach ((array)json_decode((string)$r['actions'], true) as $definition) {
        $action = ActionRegistry::get((string)($definition['type'] ?? ''));
        $lines[] = $action ? htmlspecialchars($action->describe((array)($definition['params'] ?? []), $container)) : '<i>'.htmlspecialchars((string)($definition['type'] ?? '?')).' ('.__('unavailable').')</i>';
    }
    return implode('<br/>', $lines);
});
$table->addColumn('active', __('Active'))->format(function ($r) {
    return $r['active'] == 'Y' ? __('Yes') : __('No');
});
$table->addActionColumn()
    ->addParam('csAutomationRuleID')
    ->format(function ($rule, $actions) {
        $actions->addAction('edit', __('Edit'))->setURL('/modules/CustomerService/cs_rule_edit.php');
        $actions->addAction('delete', __('Delete'))->setURL('/modules/CustomerService/cs_delete.php')
            ->addParam('entity', 'rule')->addParam('id', $rule['csAutomationRuleID']);
    });

echo $table->render(new DataSet($rules));
