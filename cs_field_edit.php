<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;
use Gibbon\Module\CustomerService\Domain\FieldGateway;
use Gibbon\Module\CustomerService\Domain\TicketTypeGateway;
use Gibbon\Module\CustomerService\Service\CustomFields;

require_once __DIR__ . '/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/CustomerService/cs_field_edit.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$csTicketTypeID = $_GET['csTicketTypeID'] ?? '';
$csFieldID = $_GET['csFieldID'] ?? '';
$type = $container->get(TicketTypeGateway::class)->getByID($csTicketTypeID);
$gateway = $container->get(FieldGateway::class);

if (empty($type)) {
    $page->addError(__('You have not specified one or more required parameters.'));
    return;
}

$values = ['label' => '', 'fieldKey' => '', 'fieldType' => 'varchar', 'options' => '', 'required' => 'N', 'description' => '', 'sequenceNumber' => $gateway->nextSequence($csTicketTypeID)];
if (!empty($csFieldID)) {
    $values = $gateway->getByID($csFieldID);
    if (empty($values) || $values['csTicketTypeID'] != $csTicketTypeID) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }
}

$page->breadcrumbs->add(__('Manage Ticket Types'), 'cs_type_manage.php');
$page->breadcrumbs->add($type['name'], 'cs_type_edit.php', ['csTicketTypeID' => $csTicketTypeID]);
$page->breadcrumbs->add(empty($csFieldID) ? __('Add Field') : __('Edit Field'));

$form = Form::create('csField', $session->get('absoluteURL').'/modules/CustomerService/cs_field_process.php');
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('csTicketTypeID', $csTicketTypeID);
$form->addHiddenValue('csFieldID', $csFieldID);

$row = $form->addRow();
    $row->addLabel('label', __('Label'));
    $row->addTextField('label')->maxLength(100)->required()->setValue($values['label']);

$row = $form->addRow();
    $row->addLabel('fieldKey', __('Key'))->description(__('Letters, numbers and underscore. Fields with the same key on different ticket types are treated as the same field by board filters. Cannot be changed later. Leave empty to generate it from the label.'));
    $key = $row->addTextField('fieldKey')->maxLength(50)->setValue($values['fieldKey'])
        ->addValidation('Validate.Format', 'pattern: /^[A-Za-z0-9_]*$/, failureMessage: "'.__('Letters, numbers and underscore only').'"');
    if (!empty($csFieldID)) {
        $key->readonly();
    }

$row = $form->addRow();
    $row->addLabel('fieldType', __('Type'));
    $row->addSelect('fieldType')->fromArray(CustomFields::typeLabels())->required()->selected($values['fieldType']);

$row = $form->addRow();
    $row->addLabel('options', __('Options'))->description(__('Short text: maximum length. Dropdown, checkboxes, radio: comma separated list of choices.'));
    $row->addTextArea('options')->setRows(2)->setValue($values['options']);

$row = $form->addRow();
    $row->addLabel('description', __('Help Text'));
    $row->addTextField('description')->maxLength(255)->setValue($values['description']);

$row = $form->addRow();
    $row->addLabel('required', __('Required'));
    $row->addYesNo('required')->required()->selected($values['required']);

$row = $form->addRow();
    $row->addLabel('sequenceNumber', __('Order'));
    $row->addNumber('sequenceNumber')->required()->minimum(0)->maximum(9999)->setValue($values['sequenceNumber']);

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

echo $form->getOutput();
