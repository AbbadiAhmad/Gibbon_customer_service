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

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$URL = $session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk/notifications.php';

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/notifications.php') == false) {
    header("Location: {$URL}&return=error0");
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
