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
use Gibbon\Module\ServiceDesk\Domain\TicketTypeGateway;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$gibbonServiceDeskTicketTypeID = $_POST['gibbonServiceDeskTicketTypeID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk/types_manage.php';

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/types_manage_delete.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

$ticketTypeGateway = $container->get(TicketTypeGateway::class);

if (!$ticketTypeGateway->exists($gibbonServiceDeskTicketTypeID)) {
    header("Location: {$URL}&return=error1");
    exit;
}

// A type with tickets is kept (make it inactive instead), so its tickets stay readable.
if ($ticketTypeGateway->countTickets($gibbonServiceDeskTicketTypeID) > 0) {
    header("Location: {$URL}&return=error4");
    exit;
}

$deleted = $ticketTypeGateway->deleteType($gibbonServiceDeskTicketTypeID);

header("Location: {$URL}&return=".($deleted ? 'success0' : 'error2'));
