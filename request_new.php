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
use Gibbon\Services\Format;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Module\ServiceDesk\Domain\FieldGateway;
use Gibbon\Module\ServiceDesk\Domain\TicketTypeGateway;
use Gibbon\Module\ServiceDesk\Service\People;
use Gibbon\Module\ServiceDesk\Service\TicketAccess;
use Gibbon\Module\ServiceDesk\Service\RequestFields;

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/request_new.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->stylesheets->add('serviceDesk', 'modules/ServiceDesk/css/module.css');

$ticketTypeGateway = $container->get(TicketTypeGateway::class);
$people = $container->get(People::class);
$moduleURL = $session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk/request_new.php';

// WHO IS THE REQUEST FOR? Normally the user. Holders of "New Request_onBehalf" may choose another person.
$canCreateOnBehalf = $container->get(TicketAccess::class)->canCreateOnBehalf();
$requester = [
    'gibbonPersonID'     => $session->get('gibbonPersonID'),
    'roleCategory'       => $session->get('gibbonRoleIDCurrentCategory'),
    'gibbonSchoolYearID' => $session->get('gibbonSchoolYearID'),
];
$onBehalf = [];
$gibbonPersonIDOnBehalf = $canCreateOnBehalf ? intval($_GET['gibbonPersonIDOnBehalf'] ?? 0) : 0;

if (!empty($gibbonPersonIDOnBehalf) && $gibbonPersonIDOnBehalf != intval($session->get('gibbonPersonID'))) {
    $onBehalf = $people->getPerson($gibbonPersonIDOnBehalf);
    if (empty($onBehalf)) {
        $page->breadcrumbs->add(__m('New Request'));
        $page->addError(__('The specified record cannot be found.'));
        return;
    }
    // The form is built for that person: a parent's own children, their classes.
    $requester['gibbonPersonID'] = $onBehalf['gibbonPersonID'];
    $requester['roleCategory'] = $onBehalf['roleCategory'];
}
$onBehalfParam = !empty($onBehalf) ? '&gibbonPersonIDOnBehalf='.$gibbonPersonIDOnBehalf : '';

// WHICH SERVICES? Those offered to the user's role category; every active one with the on-behalf permission.
$types = $canCreateOnBehalf
    ? $ticketTypeGateway->selectActiveTypes()->fetchAll()
    : $ticketTypeGateway->selectRequestableTypes($session->get('gibbonRoleIDCurrentCategory'))->fetchAll();
$types = array_combine(array_map('intval', array_column($types, 'gibbonServiceDeskTicketTypeID')), $types);

$gibbonServiceDeskTicketTypeID = intval($_GET['gibbonServiceDeskTicketTypeID'] ?? 0);

// STEP 1: choose a service (and, with the permission, who it is for)
if (empty($gibbonServiceDeskTicketTypeID)) {
    $page->breadcrumbs->add(__m('New Request'));

    if ($canCreateOnBehalf) {
        $form = Form::createSearch();
        $form->setClass('noIntBorder w-full');
        $form->addHiddenValue('q', '/modules/ServiceDesk/request_new.php');

        $row = $form->addRow();
            $row->addLabel('gibbonPersonIDOnBehalf', __m('On Behalf Of'))->description(__m('Leave empty to send a request for yourself. The person chosen becomes the requester of the ticket.'));
            $row->addSelectPerson('gibbonPersonIDOnBehalf')->fromArray($people->getPeopleOptions())->placeholder()->selected($gibbonPersonIDOnBehalf ?: '');

        $row = $form->addRow();
            $row->addSearchSubmit($session);

        echo $form->getOutput();
    }

    if (!empty($onBehalf)) {
        echo Format::alert(__m('You are creating a request on behalf of {name}.', ['name' => '<b>'.htmlspecialchars($onBehalf['name']).'</b>']), 'message');
    }

    echo $page->fetchFromTemplate('catalog.twig.html', [
        'types' => $types,
        'url'   => $moduleURL.$onBehalfParam.'&gibbonServiceDeskTicketTypeID=',
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
    ->add(__m('New Request'), 'request_new.php', !empty($onBehalf) ? ['gibbonPersonIDOnBehalf' => $gibbonPersonIDOnBehalf] : [])
    ->add(__m($type['name']));

if (!empty($onBehalf)) {
    echo Format::alert(__m('You are creating a request on behalf of {name}.', ['name' => '<b>'.htmlspecialchars($onBehalf['name']).'</b>']), 'message');
}

$fields = $container->get(FieldGateway::class)->selectFieldsByType($gibbonServiceDeskTicketTypeID)->fetchAll();

$form = Form::create('request', $session->get('absoluteURL').'/modules/ServiceDesk/request_newProcess.php');
$form->setFactory(DatabaseFormFactory::create($pdo));
$form->setTitle(__m($type['name']));
$form->setDescription(htmlspecialchars(__m($type['description'])));

$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('gibbonServiceDeskTicketTypeID', $gibbonServiceDeskTicketTypeID);
$form->addHiddenValue('gibbonPersonIDOnBehalf', !empty($onBehalf) ? $gibbonPersonIDOnBehalf : '');

$container->get(RequestFields::class)->addToForm($form, $fields, $requester);

$row = $form->addRow();
    $row->addLabel('subject', __m('Subject'))->description(__m('Optional: a short title for your request.'));
    $row->addTextField('subject')->maxLength(120);

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit(__m('Send Request'));

echo $form->getOutput();
