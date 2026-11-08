<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Data\Validator;
use Gibbon\Module\administration\Domain\FieldGateway;
use Gibbon\Module\administration\Domain\FormFieldGateway;
use Gibbon\Module\administration\Domain\FormGateway;
use Gibbon\Module\administration\Service\CustomFields;
use Gibbon\Module\administration\Service\FormMapper;

$_POST['address'] = '/modules/administration/cs_form_manage.php';

require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

csRequirePost($session);

$_POST = $container->get(Validator::class)->sanitize($_POST);
$csFormID = $_POST['csFormID'] ?? '';
$csFormFieldID = $_POST['csFormFieldID'] ?? '';

if (!csAccess($guid, $connection2, 'cs_formfield_edit.php', 'Manage Forms')) {
    csRedirect($session, 'cs_form_manage.php', [], 'error0');
}

$form = $container->get(FormGateway::class)->getByID($csFormID);
$questions = $container->get(FormFieldGateway::class);
if (empty($form)) {
    csRedirect($session, 'cs_form_manage.php', [], 'error1');
}

$editParams = ['csFormID' => $csFormID] + (empty($csFormFieldID) ? [] : ['csFormFieldID' => $csFormFieldID]);
$label = mb_substr(trim($_POST['label'] ?? ''), 0, 150);
$inputType = $_POST['inputType'] ?? '';

$data = [
    'csFormID'       => $csFormID,
    'label'          => $label,
    'helpText'       => mb_substr(trim($_POST['helpText'] ?? ''), 0, 255) ?: null,
    'inputType'      => $inputType,
    'options'        => trim($_POST['options'] ?? '') ?: null,
    'required'       => ($_POST['required'] ?? 'N') == 'Y' && $inputType !== 'info' ? 'Y' : 'N',
    'target'         => $inputType === 'info' ? '' : trim($_POST['target'] ?? ''),
    'sequenceNumber' => (int)($_POST['sequenceNumber'] ?? 0),
];
// A file upload fills in the attachments, whatever was chosen.
if ($inputType === 'file') {
    $data['target'] = 'attachments';
}

if ($label === '' || !isset(FormMapper::inputTypes()[$inputType])) {
    csRedirect($session, 'cs_formfield_edit.php', $editParams, 'error1');
}

// The mapping must be able to hold the answer (a date goes to a date field, choices to a field with those options...).
$typeFields = $container->get(FieldGateway::class)->selectByType($form['csTicketTypeID']);
$problem = FormMapper::validateMapping($data, $typeFields);
if ($problem !== '') {
    csFlash($session, $problem);
    csRedirect($session, 'cs_formfield_edit.php', $editParams, 'error1');
}

if (empty($csFormFieldID)) {
    $key = trim($_POST['fieldKey'] ?? '') ?: CustomFields::slug($label);
    if (!preg_match('/^[A-Za-z0-9_]{1,50}$/', $key) || $questions->keyExists($csFormID, $key)) {
        csFlash($session, __('The key is not valid or already used in this form.'));
        csRedirect($session, 'cs_formfield_edit.php', $editParams, 'error7');
    }
    $data['fieldKey'] = $key;
    $ok = !empty($questions->insert($data));
} else {
    $existing = $questions->getByID($csFormFieldID);
    $ok = !empty($existing) && $existing['csFormID'] == $csFormID && $questions->update($csFormFieldID, $data);
}

csRedirect($session, $ok ? 'cs_form_edit.php' : 'cs_formfield_edit.php', $ok ? ['csFormID' => $csFormID] : $editParams, $ok ? 'success0' : 'error2');
