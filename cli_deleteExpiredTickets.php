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
 * Retention and housekeeping, to be run from cron (for example every night):
 *
 *     php /path/to/gibbon/modules/ServiceDesk/cli_deleteExpiredTickets.php
 *
 * 1. Deletes closed tickets whose ticket type has a retention time (Manage Ticket Types) that has passed - in batches,
 *    with one line in Gibbon's log per batch.
 * 2. Removes left-overs that point to tickets or people that no longer exist.
 *
 * Options:  --dry-run       show what would be deleted, change nothing
 *           --max=5000      stop after this many tickets in one run (default 20000; the rest follows next run)
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
$max = 20000;
foreach ($argv ?? [] as $argument) {
    if (preg_match('/^--max=(\d+)$/', $argument, $matches)) {
        $max = max(1, intval($matches[1]));
    }
}
$batchSize = 500;

$ticketGateway = $container->get(TicketGateway::class);
$ticketService = $container->get(TicketService::class);

// 1. RETENTION
$expired = $ticketGateway->countExpiredTickets();
$deleted = 0;

if ($dryRun) {
    foreach ($ticketGateway->selectExpiredTickets(20) as $ticket) {
        echo 'Would delete ticket #'.intval($ticket['gibbonServiceDeskTicketID']).' ('.$ticket['typeName'].")\n";
    }
    echo $expired > 20 ? '... and '.($expired - 20)." more.\n" : '';
    echo "$expired ticket(s) would be deleted.\n";
} else {
    while ($deleted < $max) {
        $batch = $ticketGateway->selectExpiredTickets(min($batchSize, $max - $deleted));
        if (empty($batch)) break;

        $count = $ticketService->deleteTickets($batch, null);
        if ($count == 0) {
            echo "A batch could not be deleted and was rolled back; stopping.\n";
            break;
        }
        $deleted += $count;
    }
    echo "$deleted of $expired expired ticket(s) deleted.".($deleted < $expired ? ' The rest follows with the next run.' : '')."\n";
}

// 2. HOUSEKEEPING
$orphans = $ticketGateway->cleanOrphans($dryRun);
foreach ($orphans as $what => $count) {
    echo ($dryRun ? 'Would remove ' : 'Removed ').$count.' '.$what.".\n";
}
if (empty($orphans)) {
    echo "No left-overs found.\n";
}
