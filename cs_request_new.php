<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;
use Gibbon\Module\CustomerService\Domain\FieldGateway;
use Gibbon\Module\CustomerService\Domain\TicketTypeGateway;
use Gibbon\Module\CustomerService\Service\Attachments;
use Gibbon\Module\CustomerService\Service\CustomFields;
use Gibbon\Module\CustomerService\Service\FormConfig;

require_once __DIR__ . '/moduleFunctions.php';

csSanitizeRequest($container);

if (!csAccess($guid, $connection2, 'cs_request_new.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$csTicketTypeID = $_GET['csTicketTypeID'] ?? '';

// No request type chosen: this is the portal home.
if (empty($csTicketTypeID)) {
    require __DIR__ . '/cs_portal.php';
    return;
}

$type = $container->get(TicketTypeGateway::class)->getByID($csTicketTypeID);
$isAdmin = csIsAdmin($guid, $connection2);

// The request type must exist, be active and be open to the user's role category (admins can preview any).
if (empty($type) || ($type['active'] != 'Y' && !$isAdmin)
    || (!$isAdmin && !FormConfig::inAudience($type['audience'], csRoleCategory($session)))) {
    $page->addError(__('The specified record cannot be found.'));
    return;
}

$page->breadcrumbs->add(__('Customer Portal'), 'cs_portal.php');
$page->breadcrumbs->add($type['name']);

$form = Form::create('csRequest', $session->get('absoluteURL').'/modules/CustomerService/cs_request_newProcess.php');
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('csTicketTypeID', $csTicketTypeID);

$form->addRow()->addHeading($type['name'], ($type['icon'] ? $type['icon'].' ' : '').__($type['name']));
if (!empty($type['description'])) {
    $form->addRow()->addContent(htmlspecialchars($type['description']));
}

$fileUploader = new Gibbon\FileUploader($pdo, $session);
$customFields = $container->get(FieldGateway::class)->selectByType($csTicketTypeID);

// The form is exactly what the request type is configured to show (Manage Ticket Types > Request Form).
foreach (FormConfig::items($type['formConfig'], $customFields) as $item) {
    if ($item['kind'] == 'custom') {
        CustomFields::addToForm($form, [$item['field']]);
        continue;
    }

    $row = $form->addRow();
    $label = $row->addLabel($item['key'], $item['label']);
    if (!empty($item['help'])) {
        $label->description($item['help']);
    }

    switch ($item['key']) {
        case 'title':
            $input = $row->addTextField('title')->maxLength(200);
            break;
        case 'description':
            $input = $row->addTextArea('description')->setRows(6);
            break;
        case 'priority':
            $input = $row->addSelect('priority')->fromArray(['Low' => __('Low'), 'Medium' => __('Medium'), 'High' => __('High'), 'Urgent' => __('Urgent')])->selected('Medium');
            break;
        case 'dateStart':
        case 'dateDue':
            $input = $row->addDate($item['key']);
            break;
        case 'attachments':
            $input = $row->addFileUpload(Attachments::FIELD)->accepts($fileUploader->getFileExtensions())->uploadMultiple()->setMaxUpload(false);
            $label->description(trim($item['help'].' '.sprintf(__('Up to %1$s files.'), Attachments::MAX_FILES)));
            break;
    }
    if ($item['required']) {
        $input->required();
    }
}

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit(__('Submit Request'));

echo $form->getOutput();
