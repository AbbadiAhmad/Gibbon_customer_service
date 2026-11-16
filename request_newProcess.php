<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/

use Gibbon\Data\Validator;
use Gibbon\Module\ServiceDesk\Domain\FieldGateway;
use Gibbon\Module\ServiceDesk\Domain\TicketGateway;
use Gibbon\Module\ServiceDesk\Domain\TicketTypeGateway;
use Gibbon\Module\ServiceDesk\Service\People;
use Gibbon\Module\ServiceDesk\Service\TicketAccess;
use Gibbon\Module\ServiceDesk\Service\RequestFields;
use Gibbon\Module\ServiceDesk\Service\TicketService;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$gibbonServiceDeskTicketTypeID = intval($_POST['gibbonServiceDeskTicketTypeID'] ?? 0);
$gibbonPersonIDOnBehalf = intval($_POST['gibbonPersonIDOnBehalf'] ?? 0);
$URLBase = $session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk/request_new.php'.(!empty($gibbonPersonIDOnBehalf) ? '&gibbonPersonIDOnBehalf='.$gibbonPersonIDOnBehalf : '');
$URL = $URLBase.'&gibbonServiceDeskTicketTypeID='.$gibbonServiceDeskTicketTypeID;

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/request_new.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

$access = $container->get(TicketAccess::class);
$ticketTypeGateway = $container->get(TicketTypeGateway::class);
$actorID = $session->get('gibbonPersonID');
$canCreateOnBehalf = $access->canCreateOnBehalf();

// WHO IS IT FOR? Another person only with the permission "New Request_onBehalf", and only an active user.
$requester = [
    'gibbonPersonID'     => $actorID,
    'roleCategory'       => $session->get('gibbonRoleIDCurrentCategory'),
    'gibbonSchoolYearID' => $session->get('gibbonSchoolYearID'),
];
if (!empty($gibbonPersonIDOnBehalf) && $gibbonPersonIDOnBehalf != intval($actorID)) {
    if (!$canCreateOnBehalf) {
        header("Location: {$URL}&return=error0");
        exit;
    }
    $onBehalf = $container->get(People::class)->getPerson($gibbonPersonIDOnBehalf);
    if (empty($onBehalf)) {
        header("Location: {$URL}&return=error1");
        exit;
    }
    $requester['gibbonPersonID'] = $onBehalf['gibbonPersonID'];
    $requester['roleCategory'] = $onBehalf['roleCategory'];
}

// The type must be one the user may request: re-checked here, not only on the form.
$types = $canCreateOnBehalf
    ? $ticketTypeGateway->selectActiveTypes()->fetchAll()
    : $ticketTypeGateway->selectRequestableTypes($session->get('gibbonRoleIDCurrentCategory'))->fetchAll();
$types = array_combine(array_map('intval', array_column($types, 'gibbonServiceDeskTicketTypeID')), $types);
$type = $types[$gibbonServiceDeskTicketTypeID] ?? [];

if (empty($type)) {
    header("Location: {$URL}&return=error1");
    exit;
}

// Only the fields of the stored form are read, and every answer is validated against what the form offers the
// requester (for example a parent's own children).
$fields = $container->get(FieldGateway::class)->selectFieldsByType($gibbonServiceDeskTicketTypeID)->fetchAll();
list($answers, $valid) = $container->get(RequestFields::class)->collect($fields, $_POST, $requester);

if (!$valid) {
    header("Location: {$URL}&return=error1");
    exit;
}

// A person's own requests are limited per hour (a script or a stuck browser must not flood the teams). Requests made
// on behalf of somebody else by staff with that permission are not limited.
$ticketService = $container->get(TicketService::class);
if (intval($requester['gibbonPersonID']) === intval($actorID) && $ticketService->countRecentRequests($actorID) >= TicketService::MAX_REQUESTS_PER_HOUR) {
    header("Location: {$URLBase}&return=error20");
    exit;
}

$subject = trim($_POST['subject'] ?? '');
if ($subject === '') {
    $names = RequestFields::firstPeopleText($answers);
    $subject = $type['name'].($names !== '' ? ': '.$names : '');
}

$gibbonServiceDeskTicketID = $ticketService->create($type, $requester['gibbonPersonID'], $session->get('gibbonSchoolYearID'), $subject, $answers, $actorID);

if (empty($gibbonServiceDeskTicketID)) {
    header("Location: {$URL}&return=error2");
    exit;
}

// Someone who created a ticket for another person may not be allowed to open it (not the requester, not in the team).
$ticket = $container->get(TicketGateway::class)->getTicketByID($gibbonServiceDeskTicketID);
if (!$access->canView($ticket)) {
    header("Location: {$URLBase}&return=success0");
    exit;
}

$URL = $session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk/tickets_view.php&gibbonServiceDeskTicketID='.$gibbonServiceDeskTicketID;
header("Location: {$URL}&return=success0");
