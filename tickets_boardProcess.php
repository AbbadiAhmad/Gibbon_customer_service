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
use Gibbon\Module\ServiceDesk\Domain\SeenGateway;
use Gibbon\Module\ServiceDesk\Domain\StageGateway;
use Gibbon\Module\ServiceDesk\Domain\TicketGateway;
use Gibbon\Module\ServiceDesk\Service\TicketAccess;
use Gibbon\Module\ServiceDesk\Service\TicketFilters;
use Gibbon\Module\ServiceDesk\Service\TicketService;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

// Back to the board the card was moved on: a saved filter, or cleaned criteria (never the raw string).
parse_str((string) ($_POST['criteria'] ?? ''), $posted);
$back = !empty($posted['gibbonServiceDeskFilterID'])
    ? 'gibbonServiceDeskFilterID='.intval($posted['gibbonServiceDeskFilterID'])
    : TicketFilters::toQuery($posted);
$URL = $session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk/tickets.php'.($back !== '' ? '&'.$back : '');

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/tickets.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

// Moving a card is a stage change: the same rules as on the ticket page.
$ticket = $container->get(TicketGateway::class)->getTicketByID($_POST['gibbonServiceDeskTicketID'] ?? '');
if (empty($ticket) || !$container->get(TicketAccess::class)->canManage($ticket)) {
    header("Location: {$URL}&return=error0");
    exit;
}

$stage = $container->get(StageGateway::class)->getByID($_POST['gibbonServiceDeskStageID'] ?? '');
if (empty($stage) || intval($stage['gibbonServiceDeskTicketTypeID']) !== intval($ticket['gibbonServiceDeskTicketTypeID'])) {
    header("Location: {$URL}&return=error1");
    exit;
}

if (!$container->get(TicketService::class)->changeStage($ticket, $stage, $session->get('gibbonPersonID'))) {
    // The stage's automation failed: the ticket stayed where it was, the reason is in its history.
    header("Location: {$URL}&return=warning1");
    exit;
}

// The person moved the card themselves: the change is not news to them.
$container->get(SeenGateway::class)->markSeen($ticket['gibbonServiceDeskTicketID'], $session->get('gibbonPersonID'));

header("Location: {$URL}&return=success0");
