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
use Gibbon\Module\ServiceDesk\Domain\TeamGateway;
use Gibbon\Module\ServiceDesk\Domain\SubscriberGateway;
use Gibbon\Module\ServiceDesk\Domain\TicketTypeGateway;
use Gibbon\Module\ServiceDesk\Domain\TypeNotificationGateway;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$gibbonServiceDeskTicketTypeID = intval($_POST['gibbonServiceDeskTicketTypeID'] ?? 0);
$URL = $session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk/types_manage_edit.php&gibbonServiceDeskTicketTypeID='.$gibbonServiceDeskTicketTypeID;

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/types_manage_edit.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

if (empty($container->get(TicketTypeGateway::class)->getByID($gibbonServiceDeskTicketTypeID))) {
    header("Location: ".$session->get('absoluteURL')."/index.php?q=/modules/ServiceDesk/types_manage.php&return=error1");
    exit;
}

$typeNotificationGateway = $container->get(TypeNotificationGateway::class);
$subscriberGateway = $container->get(SubscriberGateway::class);

// Add: only people who could work on the type (its teams, administrators). They start with their own general channels.
$eligible = array_map('intval', array_column($container->get(TeamGateway::class)->selectNotifiableStaff($gibbonServiceDeskTicketTypeID)->fetchAll(), 'gibbonPersonID'));
foreach ((array) ($_POST['add'] ?? []) as $personID) {
    $personID = intval($personID);
    if (!in_array($personID, $eligible, true)) {
        continue;
    }
    $preference = $subscriberGateway->getPreference($personID);
    $typeNotificationGateway->save($gibbonServiceDeskTicketTypeID, $personID, [
        'newInApp' => $preference['notifyInApp'], 'updateInApp' => $preference['notifyInApp'],
        'newEmail' => $preference['notifyEmail'], 'updateEmail' => $preference['notifyEmail'],
    ]);
}

foreach ((array) ($_POST['remove'] ?? []) as $personID) {
    $typeNotificationGateway->remove($gibbonServiceDeskTicketTypeID, intval($personID));
}

header("Location: {$URL}&return=success0");
