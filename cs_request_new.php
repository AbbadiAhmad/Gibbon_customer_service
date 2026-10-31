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
use Gibbon\Module\CustomerService\Service\Attachments;
use Gibbon\Module\CustomerService\Service\CustomFields;

require_once __DIR__ . '/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/CustomerService/cs_request_new.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs->add(__('Raise a Request'));

$typeGateway = $container->get(TicketTypeGateway::class);
$csTicketTypeID = $_GET['csTicketTypeID'] ?? '';

// Step 1: choose the type of request
if (empty($csTicketTypeID)) {
    $types = $typeGateway->selectActive();
    if (empty($types)) {
        $page->addMessage(__('No request types have been set up yet.'));
        return;
    }

    echo '<h2>'.__('What do you need help with?').'</h2>';
    echo '<ul class="list-disc pl-6 leading-loose">';
    foreach ($types as $type) {
        echo '<li>'.Format::link(csURL($session, 'cs_request_new.php', ['csTicketTypeID' => $type['csTicketTypeID']]), $type['name']);
        if (!empty($type['description'])) {
            echo ' - <span class="text-gray-600">'.htmlspecialchars($type['description']).'</span>';
        }
        echo '</li>';
    }
    echo '</ul>';
    return;
}

// Step 2: the form of that type
$type = $typeGateway->getByID($csTicketTypeID);
if (empty($type) || $type['active'] != 'Y') {
    $page->addError(__('The specified record cannot be found.'));
    return;
}

$page->breadcrumbs->add($type['name']);

$form = Form::create('csRequest', $session->get('absoluteURL').'/modules/CustomerService/cs_request_newProcess.php');
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('csTicketTypeID', $csTicketTypeID);

$form->addRow()->addHeading($type['name'], __($type['name']));
if (!empty($type['description'])) {
    $form->addRow()->addContent(htmlspecialchars($type['description']));
}

$row = $form->addRow();
    $row->addLabel('title', __('Title'));
    $row->addTextField('title')->maxLength(200)->required();

$row = $form->addRow();
    $row->addLabel('description', __('Description'));
    $row->addTextArea('description')->setRows(6);

$row = $form->addRow();
    $row->addLabel('priority', __('Priority'));
    $row->addSelect('priority')->fromArray(['Low' => __('Low'), 'Medium' => __('Medium'), 'High' => __('High'), 'Urgent' => __('Urgent')])->selected('Medium')->required();

$row = $form->addRow();
    $row->addLabel('dateStart', __('Start Date'))->description(__('Optional. When the work should begin.'));
    $row->addDate('dateStart');

$row = $form->addRow();
    $row->addLabel('dateDue', __('Due Date'))->description(__('Optional. When you need it done.'));
    $row->addDate('dateDue');

$fields = $container->get(FieldGateway::class)->selectByType($csTicketTypeID);
CustomFields::addToForm($form, $fields);

$fileUploader = new Gibbon\FileUploader($pdo, $session);
$row = $form->addRow();
    $row->addLabel('attachments', __('Attachments'))->description(sprintf(__('Optional. Up to %1$s files, e.g. screenshots or documents.'), Attachments::MAX_FILES));
    $row->addFileUpload(Attachments::FIELD)->accepts($fileUploader->getFileExtensions())->uploadMultiple()->setMaxUpload(false);

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit(__('Submit Request'));

echo $form->getOutput();
