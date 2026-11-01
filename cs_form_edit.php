<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Module\CustomerService\Domain\FieldGateway;
use Gibbon\Module\CustomerService\Domain\TicketTypeGateway;
use Gibbon\Module\CustomerService\Service\CustomFields;
use Gibbon\Module\CustomerService\Service\FormConfig;

require_once __DIR__ . '/moduleFunctions.php';

csSanitizeRequest($container);

if (!csAccess($guid, $connection2, 'cs_form_edit.php', 'Manage Ticket Types')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$csTicketTypeID = $_GET['csTicketTypeID'] ?? '';
$type = $container->get(TicketTypeGateway::class)->getByID($csTicketTypeID);
if (empty($type)) {
    $page->addError(__('The specified record cannot be found.'));
    return;
}

$page->breadcrumbs->add(__('Manage Ticket Types'), 'cs_type_manage.php');
$page->breadcrumbs->add($type['name'], 'cs_type_edit.php', ['csTicketTypeID' => $csTicketTypeID]);
$page->breadcrumbs->add(__('Request Form'));

$formUrl = csURL($session, 'cs_request_new.php', ['csTicketTypeID' => $csTicketTypeID]);
echo '<p>'.__('This is the form requesters fill in on the customer portal. Choose what they see, what is required and in which order.').' '
    .Format::link($formUrl, __('Open the form as a requester sees it')).'</p>';
echo '<p class="text-gray-600">'.__('Direct link to this form').': <code>'.htmlspecialchars($formUrl).'</code></p>';

$yesNo = ['Y' => __('Yes'), 'N' => __('No')];
$core = FormConfig::core($type['formConfig']);
$customFields = $container->get(FieldGateway::class)->selectByType($csTicketTypeID);
$typeLabels = CustomFields::typeLabels();

$form = Form::create('csFormEditor', $session->get('absoluteURL').'/modules/CustomerService/cs_form_process.php');
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('csTicketTypeID', $csTicketTypeID);

// ---- Basic fields --------------------------------------------------------------------
$form->addRow()->addHeading('Basic', __('Basic Fields'))->append(__('Every request type has these. The summary is always shown and required.'));
foreach ($core as $key => $c) {
    $locked = in_array($key, FormConfig::LOCKED, true);

    $row = $form->addRow();
    $row->addLabel('fmLabel_'.$key, FormConfig::defaults()[$key]['label'])->description($locked ? __('Always shown and required.') : '');
    $col = $row->addColumn()->addClass('flex flex-wrap gap-2');
    $col->addSelect('fmShow_'.$key)->fromArray([ 'Y' => __('Shown'), 'N' => __('Hidden')])->selected($c['show'] ? 'Y' : 'N')->setClass('w-24'.($locked ? ' hidden' : ''));
    $col->addSelect('fmRequired_'.$key)->fromArray(['Y' => __('Required'), 'N' => __('Optional')])->selected($c['required'] ? 'Y' : 'N')->setClass('w-28'.($locked ? ' hidden' : ''));
    $col->addTextField('fmLabel_'.$key)->maxLength(255)->setValue($c['label'])->setClass('flex-1')->placeholder(__('Label'));
    $col->addTextField('fmHelp_'.$key)->maxLength(255)->setValue($c['help'])->setClass('flex-1')->placeholder(__('Help text'));
    $col->addNumber('fmOrder_'.$key)->minimum(0)->maximum(9999)->setValue($c['order'])->setClass('w-20');
}

// ---- Custom fields -----------------------------------------------------------------
$form->addRow()->addHeading('Custom', __('Custom Fields'))->append(__('Fields shown here are on the portal form. A field set to "Hidden" is only filled in by the agents on the ticket page. Labels, help text and options are edited on the field itself.'));
if (empty($customFields)) {
    $form->addRow()->addContent('<i>'.__('This type has no custom fields yet.').'</i>');
}
foreach ($customFields as $field) {
    $row = $form->addRow();
    $row->addLabel('fmShow_f'.$field['csFieldID'], $field['label'])->description($typeLabels[$field['fieldType']] ?? $field['fieldType']);
    $col = $row->addColumn()->addClass('flex flex-wrap gap-2');
    $col->addSelect('fmShow_f'.$field['csFieldID'])->fromArray(['Y' => __('Shown'), 'N' => __('Hidden')])->selected($field['showOnForm'])->setClass('w-24');
    $col->addSelect('fmRequired_f'.$field['csFieldID'])->fromArray(['Y' => __('Required'), 'N' => __('Optional')])->selected($field['required'])->setClass('w-28');
    $col->addContent(Format::link(csURL($session, 'cs_field_edit.php', ['csTicketTypeID' => $csTicketTypeID, 'csFieldID' => $field['csFieldID']]), __('Edit field')))->setClass('flex-1');
    $col->addNumber('fmOrder_f'.$field['csFieldID'])->minimum(0)->maximum(9999)->setValue($field['sequenceNumber'])->setClass('w-20');
}

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

echo $form->getOutput();
