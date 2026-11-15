<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Module\ServiceDesk\Domain\FieldGateway;
use Gibbon\Module\ServiceDesk\Domain\TicketTypeGateway;
use Gibbon\Module\ServiceDesk\Service\RequestFields;

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/request_new.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->stylesheets->add('serviceDesk', 'modules/ServiceDesk/css/module.css');

// Only the services offered to the user's role category can be requested.
$types = $container->get(TicketTypeGateway::class)->selectRequestableTypes($session->get('gibbonRoleIDCurrentCategory'))->fetchAll();
$types = array_combine(array_map('intval', array_column($types, 'gibbonServiceDeskTicketTypeID')), $types);

$gibbonServiceDeskTicketTypeID = intval($_GET['gibbonServiceDeskTicketTypeID'] ?? 0);

// STEP 1: choose a service
if (empty($gibbonServiceDeskTicketTypeID)) {
    $page->breadcrumbs->add(__m('New Request'));

    echo $page->fetchFromTemplate('catalog.twig.html', [
        'types' => $types,
        'url'   => $session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk/request_new.php&gibbonServiceDeskTicketTypeID=',
    ]);
    return;
}

// STEP 2: fill in the request form of the service
$type = $types[$gibbonServiceDeskTicketTypeID] ?? [];
if (empty($type)) {
    $page->breadcrumbs->add(__m('New Request'));
    $page->addError(__('The specified record cannot be found.'));
    return;
}

$page->breadcrumbs
    ->add(__m('New Request'), 'request_new.php')
    ->add(__m($type['name']));

$fields = $container->get(FieldGateway::class)->selectFieldsByType($gibbonServiceDeskTicketTypeID)->fetchAll();

$form = Form::create('request', $session->get('absoluteURL').'/modules/ServiceDesk/request_newProcess.php');
$form->setFactory(DatabaseFormFactory::create($pdo));
$form->setTitle(__m($type['name']));
$form->setDescription(htmlspecialchars(__m($type['description'])));

$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('gibbonServiceDeskTicketTypeID', $gibbonServiceDeskTicketTypeID);

$container->get(RequestFields::class)->addToForm($form, $fields, [
    'gibbonPersonID'     => $session->get('gibbonPersonID'),
    'roleCategory'       => $session->get('gibbonRoleIDCurrentCategory'),
    'gibbonSchoolYearID' => $session->get('gibbonSchoolYearID'),
]);

$row = $form->addRow();
    $row->addLabel('subject', __m('Subject'))->description(__m('Optional: a short title for your request.'));
    $row->addTextField('subject')->maxLength(120);

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit(__m('Send Request'));

echo $form->getOutput();
