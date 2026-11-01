<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Module\administration\Automation\AutomationEngine;
use Gibbon\Module\administration\Domain\ActivityGateway;
use Gibbon\Module\administration\Domain\TicketGateway;
use Gibbon\Module\administration\Service\Notifier;

/**
 * Due date reminders. Run it once a day from cron:
 *   php /path/to/gibbon/modules/administration/cli_reminders.php 1
 *
 * - Tickets due within N days (default 1 = today and tomorrow) get ONE "due soon" reminder.
 * - Tickets past their due date get ONE "overdue" notice.
 * Both go to whoever works on the ticket (assignee, else team, else space agents) and to the watchers, and fire
 * the ticket_due_soon / ticket_overdue triggers of the automation rules. Changing the due date of a ticket
 * restarts its reminders.
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

$days = max(0, (int)($argv[1] ?? 1));
$tickets = $container->get(TicketGateway::class);
$activity = $container->get(ActivityGateway::class);
$notifier = new Notifier($container);
$engine = new AutomationEngine($container);

$counts = ['dueSoon' => 0, 'overdue' => 0];

foreach ($tickets->selectDueSoon($days) as $ticket) {
    // Mark first, so a failure while notifying never causes a reminder every day.
    $tickets->update($ticket['csTicketID'], ['dueReminderSent' => date('Y-m-d H:i:s')]);
    $notifier->dueSoon($ticket);
    $activity->log($ticket['csTicketID'], 0, sprintf(__('Reminder sent: due on %1$s'), $ticket['dateDue']), 'Event', 'Internal');
    $engine->fire('ticket_due_soon', $ticket['csTicketID']);
    $counts['dueSoon']++;
}

foreach ($tickets->selectOverdue() as $ticket) {
    $tickets->update($ticket['csTicketID'], ['overdueNoticeSent' => date('Y-m-d H:i:s')]);
    $notifier->overdue($ticket);
    $activity->log($ticket['csTicketID'], 0, sprintf(__('Overdue notice sent: was due on %1$s'), $ticket['dateDue']), 'Event', 'Internal');
    $engine->fire('ticket_overdue', $ticket['csTicketID']);
    $counts['overdue']++;
}

echo sprintf(__('%1$s due soon reminders and %2$s overdue notices sent.'), $counts['dueSoon'], $counts['overdue'])."\n";
