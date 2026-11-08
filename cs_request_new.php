<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;
use Gibbon\Module\administration\Domain\FormFieldGateway;
use Gibbon\Module\administration\Domain\FormGateway;
use Gibbon\Module\administration\Service\CustomFields;
use Gibbon\Module\administration\Service\FormAccess;
use Gibbon\Module\administration\Service\FormMapper;

require_once __DIR__ . '/moduleFunctions.php';

csSanitizeRequest($container);

if (!csAccess($guid, $connection2, 'cs_request_new.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

// A form is opened by its number or by the readable link name (?form=absence). No form chosen: show the portal.
$formsGateway = $container->get(FormGateway::class);
$form = $formsGateway->getByIdOrSlug($_GET['csFormID'] ?? '', $_GET['form'] ?? '');
if (empty($form) && empty($_GET['csFormID']) && empty($_GET['form'])) {
    require __DIR__ . '/cs_portal.php';
    return;
}

// The form must exist, be active (with an active ticket type) and be open to the user (administrators can preview any form).
$isAdmin = csIsAdmin($guid, $connection2);
$type = empty($form) ? [] : $container->get(\Gibbon\Module\administration\Domain\TicketTypeGateway::class)->getByID($form['csTicketTypeID']);
if (empty($form) || empty($type)
    || (!$isAdmin && ($form['active'] != 'Y' || $type['active'] != 'Y' || !FormAccess::allows($form, csRoleCategory($session), $session->get('gibbonRoleIDCurrent'))))) {
    $page->addError(__('The specified record cannot be found.'));
    return;
}

$page->breadcrumbs->add(__('Customer Portal'), 'cs_portal.php');
$page->breadcrumbs->add($form['name']);
echo csFlashOutput($session);

$f = Form::create('csRequest', $session->get('absoluteURL').'/modules/administration/cs_request_newProcess.php');
$f->addHiddenValue('address', $session->get('address'));
$f->addHiddenValue('csFormID', $form['csFormID']);

$f->addRow()->addHeading($form['name'], ($form['icon'] ? $form['icon'].' ' : '').$form['name']);
if (!empty($form['description'])) {
    $f->addRow()->addContent(htmlspecialchars($form['description']));
}

$fileUploader = new Gibbon\FileUploader($pdo, $session);

foreach ($container->get(FormFieldGateway::class)->selectByForm($form['csFormID']) as $q) {
    $name = FormMapper::inputName($q['fieldKey']);
    $options = CustomFields::optionList($q['options']);

    if ($q['inputType'] === 'info') {
        $f->addRow()->addContent('<b>'.htmlspecialchars($q['label']).'</b>'.(empty($q['options']) ? '' : '<br/>'.nl2br(htmlspecialchars($q['options']))));
        continue;
    }

    $row = $f->addRow();
    $label = $row->addLabel($name, $q['label']);
    if (!empty($q['helpText'])) {
        $label->description($q['helpText']);
    }

    switch ($q['inputType']) {
        case 'text':
            $input = $row->addTextArea($name)->setRows(4);
            break;
        case 'number':
            $input = $row->addNumber($name)->decimalPlaces(2);
            break;
        case 'date':
            $input = $row->addDate($name);
            break;
        case 'select':
            $input = $row->addSelect($name)->fromArray($options)->placeholder();
            break;
        case 'radio':
            $input = $row->addRadio($name)->fromArray($options);
            break;
        case 'checkboxes':
            $input = $row->addCheckbox($name)->fromArray($options);
            break;
        case 'yesno':
            $input = $row->addYesNo($name)->placeholder();
            break;
        case 'file':
            $input = $row->addFileUpload($name)->accepts($fileUploader->getFileExtensions())->uploadMultiple()->setMaxUpload(false);
            break;
        default:
            $length = (int)$q['options'];
            $input = $row->addTextField($name)->maxLength($length > 0 ? $length : 255);
    }
    if ($q['required'] == 'Y') {
        $input->required();
    }
}

$row = $f->addRow();
    $row->addFooter();
    $row->addSubmit(__('Submit'));

echo $f->getOutput();
