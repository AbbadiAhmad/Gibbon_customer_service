<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;
use Gibbon\Module\administration\Domain\FieldGateway;
use Gibbon\Module\administration\Domain\FormFieldGateway;
use Gibbon\Module\administration\Domain\FormGateway;
use Gibbon\Module\administration\Service\FormMapper;

require_once __DIR__ . '/moduleFunctions.php';

csSanitizeRequest($container);

if (!csAccess($guid, $connection2, 'cs_formfield_edit.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$csFormID = $_GET['csFormID'] ?? '';
$csFormFieldID = $_GET['csFormFieldID'] ?? '';
$form = $container->get(FormGateway::class)->getByID($csFormID);
$questions = $container->get(FormFieldGateway::class);

if (empty($form)) {
    $page->addError(__('You have not specified one or more required parameters.'));
    return;
}

$values = ['label' => '', 'fieldKey' => '', 'helpText' => '', 'inputType' => 'varchar', 'options' => '', 'required' => 'N', 'target' => 'description', 'sequenceNumber' => $questions->nextSequence($csFormID)];
if (!empty($csFormFieldID)) {
    $values = $questions->getByID($csFormFieldID);
    if (empty($values) || $values['csFormID'] != $csFormID) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }
}

$page->breadcrumbs->add(__('Manage Forms'), 'cs_form_manage.php');
$page->breadcrumbs->add($form['name'], 'cs_form_edit.php', ['csFormID' => $csFormID]);
$page->breadcrumbs->add(empty($csFormFieldID) ? __('Add Question') : __('Edit Question'));
echo csFlashOutput($session);

$typeFields = $container->get(FieldGateway::class)->selectByType($form['csTicketTypeID']);

$f = Form::create('csFormField', $session->get('absoluteURL').'/modules/administration/cs_formfield_process.php');
$f->addHiddenValue('address', $session->get('address'));
$f->addHiddenValue('csFormID', $csFormID);
$f->addHiddenValue('csFormFieldID', $csFormFieldID);

$row = $f->addRow();
    $row->addLabel('label', __('Question'))->description(__('The text people see.'));
    $row->addTextField('label')->maxLength(150)->required()->setValue($values['label']);

$row = $f->addRow();
    $row->addLabel('helpText', __('Help Text'));
    $row->addTextField('helpText')->maxLength(255)->setValue($values['helpText']);

$row = $f->addRow();
    $row->addLabel('inputType', __('Input'));
    $row->addSelect('inputType')->fromArray(FormMapper::inputTypes())->required()->selected($values['inputType']);

$row = $f->addRow();
    $row->addLabel('options', __('Options'))->description(__('Short text: the maximum length. Dropdown, radio buttons, checkboxes: the choices, separated by commas. Information text: the text to show.'));
    $row->addTextArea('options')->setRows(2)->setValue($values['options']);

$row = $f->addRow();
    $row->addLabel('required', __('Required'));
    $row->addYesNo('required')->required()->selected($values['required']);

$f->addRow()->addHeading('Mapping', __('What This Question Fills In'))
    ->append(__('Every question fills in one field of the ticket. Several questions can fill in the same field: for the description each answer becomes a line "Question: answer" (choices and checkboxes are written as text); for the title the answers are joined; for another text field of the ticket type they are put one under the other. A file upload fills in Attachments. Information text fills in nothing.'));
$row = $f->addRow();
    $row->addLabel('target', __('Fills In'));
    $row->addSelect('target')->fromArray(FormMapper::targetOptions($typeFields))->selected($values['target'])->placeholder(__('Nothing (information text)'));

$row = $f->addRow();
    $row->addLabel('fieldKey', __('Key'))->description(__('Letters, numbers and underscore; unique in this form. Used in the title template as {key}. Cannot be changed later. Leave empty to make it from the question.'));
    $key = $row->addTextField('fieldKey')->maxLength(50)->setValue($values['fieldKey'])
        ->addValidation('Validate.Format', 'pattern: /^[A-Za-z0-9_]*$/, failureMessage: "'.__('Letters, numbers and underscore only').'"');
    if (!empty($csFormFieldID)) {
        $key->readonly();
    }

$row = $f->addRow();
    $row->addLabel('sequenceNumber', __('Order'))->description(__('Lower numbers first. The questions are numbered 10, 20, 30... so you can slip one in between.'));
    $row->addNumber('sequenceNumber')->required()->minimum(0)->maximum(9999)->setValue($values['sequenceNumber']);

$row = $f->addRow();
    $row->addFooter();
    $row->addSubmit();

echo $f->getOutput();
