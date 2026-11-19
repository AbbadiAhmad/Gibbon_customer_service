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
use Gibbon\Module\ServiceDesk\Domain\SubscriberGateway;
use Gibbon\Module\ServiceDesk\Domain\TypeNotificationGateway;
use Gibbon\Module\ServiceDesk\Domain\TicketTypeGateway;
use Gibbon\Module\ServiceDesk\Service\TicketAccess;
use Gibbon\Module\ServiceDesk\Service\Notifier;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$URL = $session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk/notifications.php';

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/notifications.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

// TEST: try both channels for the current user; the page shows the result.
if (($_POST['action'] ?? '') == 'test') {
    $session->set('serviceDeskNotificationTest', $container->get(Notifier::class)->sendTest($session->get('gibbonPersonID')));
    header("Location: {$URL}");
    exit;
}

// Per ticket type (staff): only the types the person handles - administrators all, team members those of their teams.
if (($_POST['action'] ?? '') == 'types') {
    $access = $container->get(TicketAccess::class);
    if (!$access->isAgent()) {
        header("Location: {$URL}&return=error0");
        exit;
    }
    $typeIDs = array_map('intval', array_keys($container->get(TicketTypeGateway::class)->selectTypeOptions()->fetchKeyPair()));
    if (!$access->isAdmin()) {
        $typeIDs = array_values(array_intersect($typeIDs, $access->getTeamTypeIDs()));
    }

    $typeNotificationGateway = $container->get(TypeNotificationGateway::class);
    foreach ($typeIDs as $typeID) {
        $posted = (array) ($_POST['typeNotify_'.$typeID] ?? []);
        $flags = [];
        foreach (TypeNotificationGateway::FLAGS as $flag) {
            $flags[$flag] = in_array($flag, $posted, true) ? 'Y' : 'N';
        }
        $typeNotificationGateway->save($typeID, $session->get('gibbonPersonID'), $flags);
    }

    header("Location: {$URL}&return=success0");
    exit;
}

$notifyInApp = ($_POST['notifyInApp'] ?? '') == 'Y' ? 'Y' : 'N';
$notifyEmail = ($_POST['notifyEmail'] ?? '') == 'Y' ? 'Y' : 'N';

$subscriberGateway = $container->get(SubscriberGateway::class);
$subscriberGateway->setPreference($session->get('gibbonPersonID'), $notifyInApp, $notifyEmail);

if (($_POST['applyToOpen'] ?? '') == 'Y') {
    $subscriberGateway->updateSubscriptionsByPerson($session->get('gibbonPersonID'), $notifyInApp, $notifyEmail);
}

header("Location: {$URL}&return=success0");
