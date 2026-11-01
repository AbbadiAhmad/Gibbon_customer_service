<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Module\CustomerService\Domain\ActivityGateway;
use Gibbon\Module\CustomerService\Domain\TicketGateway;
use Gibbon\Module\CustomerService\Service\Notifier;

/**
 * Closes tickets that have been "Resolved" for N days without the requester reopening them.
 *
 * Run from cron, e.g. every night:
 *   php /path/to/gibbon/modules/CustomerService/cli_autoClose.php 14
 * The optional argument is the number of days (default 14).
 */
// Command line only - refuse before anything is loaded.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

if (!isCommandLineInterface()) {
    echo __('This script cannot be run from a browser, only via CLI.');
    exit;
}

$days = max(1, (int)($argv[1] ?? 14));
$tickets = $container->get(TicketGateway::class);
$activity = $container->get(ActivityGateway::class);
$notifier = new Notifier($container);

$closed = 0;
foreach ($tickets->selectStaleResolved($days) as $csTicketID) {
    $tickets->updateTicket($csTicketID, ['status' => 'Closed']);
    $activity->log($csTicketID, 0, sprintf(__('Closed automatically after %1$s days without a reply'), $days));
    $notifier->statusChanged($tickets->getTicket($csTicketID), 'Closed', 0);
    $closed++;
}

echo sprintf(__('%1$s tickets closed.'), $closed)."\n";
