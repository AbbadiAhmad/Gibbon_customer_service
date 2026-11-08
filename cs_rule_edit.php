<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;
use Gibbon\Module\administration\Automation\ActionForm;
use Gibbon\Module\administration\Automation\AutomationEngine;
use Gibbon\Module\administration\Domain\RuleGateway;
use Gibbon\Module\administration\Domain\TicketTypeGateway;
use Gibbon\Module\administration\Service\BoardFilter;
use Gibbon\Module\administration\Service\FilterForm;

require_once __DIR__ . '/moduleFunctions.php';

csSanitizeRequest($container);

if (!isActionAccessible($guid, $connection2, '/modules/administration/cs_rule_edit.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$csAutomationRuleID = $_GET['csAutomationRuleID'] ?? '';
$gateway = $container->get(RuleGateway::class);
$values = ['name' => '', 'csTicketTypeID' => '', 'triggerKey' => 'ticket_created', 'conditions' => null, 'actions' => '[]', 'sequenceNumber' => $gateway->nextSequence(), 'active' => 'Y'];

if (!empty($csAutomationRuleID)) {
    $values = $gateway->getByID($csAutomationRuleID);
    if (empty($values)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }
}

$page->breadcrumbs->add(__('Manage Automation'), 'cs_rule_manage.php');
$page->breadcrumbs->add(empty($csAutomationRuleID) ? __('Add Rule') : __('Edit Rule'));

$form = Form::create('csRule', $session->get('absoluteURL').'/modules/administration/cs_rule_process.php');
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('csAutomationRuleID', $csAutomationRuleID);

$row = $form->addRow();
    $row->addLabel('name', __('Name'));
    $row->addTextField('name')->maxLength(100)->required()->setValue($values['name']);

$form->addRow()->addHeading('When', __('When'));
$row = $form->addRow();
    $row->addLabel('triggerKey', __('Trigger'));
    $row->addSelect('triggerKey')->fromArray(AutomationEngine::triggers())->required()->selected($values['triggerKey']);

$row = $form->addRow();
    $row->addLabel('csTicketTypeID', __('Ticket Type'))->description(__('Leave empty to apply to every ticket type.'));
    $row->addSelect('csTicketTypeID')->fromArray($container->get(TicketTypeGateway::class)->selectOptions())->selected($values['csTicketTypeID'])->placeholder(__('All types'));

$form->addRow()->addHeading('If', __('If'))
    ->append(__('The ticket must match ALL conditions (checked when the trigger happens). Leave all rows empty to always run.').' '.FilterForm::help());
FilterForm::addRows($form, $container, BoardFilter::decode($values['conditions']), 'cond');

$form->addRow()->addHeading('Then', __('Then'))->append(__('Actions run in order. Pick an action, then fill in its settings.'));
ActionForm::addSlots($form, $container, (array)json_decode((string)$values['actions'], true));

$form->addRow()->addHeading('Order', __('Order'));
$row = $form->addRow();
    $row->addLabel('sequenceNumber', __('Order'))->description(__('Lower numbers run first.'));
    $row->addNumber('sequenceNumber')->required()->minimum(0)->maximum(9999)->setValue($values['sequenceNumber']);

$row = $form->addRow();
    $row->addLabel('active', __('Active'));
    $row->addYesNo('active')->required()->selected($values['active']);

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

echo $form->getOutput();
