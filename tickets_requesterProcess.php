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
use Gibbon\Module\ServiceDesk\Domain\TicketGateway;
use Gibbon\Module\ServiceDesk\Service\TicketAccess;
use Gibbon\Module\ServiceDesk\Service\TicketService;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$gibbonServiceDeskTicketID = $_POST['gibbonServiceDeskTicketID'] ?? '';
$action = $_POST['action'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk/tickets_view.php&gibbonServiceDeskTicketID='.intval($gibbonServiceDeskTicketID);

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/tickets_requester.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

// The same rules as the page: the requester (or another adult of the family), and only while it is allowed.
$access = $container->get(TicketAccess::class);
$ticket = $container->get(TicketGateway::class)->getTicketByID($gibbonServiceDeskTicketID);
$ticketService = $container->get(TicketService::class);
$note = trim($_POST['note'] ?? '');
$gibbonPersonID = $session->get('gibbonPersonID');

if (!empty($ticket) && $action == 'withdraw' && $access->canWithdraw($ticket)) {
    $done = $ticketService->withdraw($ticket, $note, $gibbonPersonID);
} elseif (!empty($ticket) && $action == 'reopen' && $access->canReopen($ticket)) {
    $done = $ticketService->reopen($ticket, $note, $gibbonPersonID);
} else {
    header("Location: {$URL}&return=error0");
    exit;
}

header("Location: {$URL}&return=".($done ? 'success0' : 'warning1'));
