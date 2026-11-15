<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/

/**
 * Deletes closed tickets whose ticket type has a retention time (Manage Ticket Types) that has passed.
 *
 * Run it from cron, for example every night:   php /path/to/gibbon/modules/ServiceDesk/cli_deleteExpiredTickets.php
 * Try it without deleting anything:            php .../cli_deleteExpiredTickets.php --dry-run
 *
 * It refuses to run from a web server: the first statement checks the command line before Gibbon is loaded.
 */
if (PHP_SAPI !== 'cli') {
    exit("This script can only be run from the command line.\n");
}

use Gibbon\Module\ServiceDesk\Domain\TicketGateway;
use Gibbon\Module\ServiceDesk\Service\TicketService;

require_once __DIR__.'/../../gibbon.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);
$ticketGateway = $container->get(TicketGateway::class);
$ticketService = $container->get(TicketService::class);

$deleted = 0;
$expired = $ticketGateway->selectExpiredTickets();

foreach ($expired as $gibbonServiceDeskTicketID) {
    $ticket = $ticketGateway->getTicketByID($gibbonServiceDeskTicketID);
    if (empty($ticket)) continue;

    if ($dryRun) {
        echo 'Would delete ticket #'.intval($gibbonServiceDeskTicketID)." (".$ticket['typeName'].")\n";
    } elseif ($ticketService->deleteTicket($ticket, null)) {
        $deleted++;
    }
}

echo $dryRun
    ? count($expired)." ticket(s) would be deleted.\n"
    : "$deleted of ".count($expired)." expired ticket(s) deleted.\n";
