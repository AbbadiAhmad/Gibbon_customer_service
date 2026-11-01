<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\administration\Automation;

use Gibbon\Module\administration\Domain\ActivityGateway;
use Gibbon\Module\administration\Domain\TicketGateway;
use Gibbon\Module\administration\Service\Notifier;
use Gibbon\Module\administration\Service\Workflow;

/**
 * What an action may do to the ticket. Every change is written to the ticket activity as an internal
 * "Automation" event, and derived events (a status change, an assignment) fire their own triggers
 * one level deeper, so rules can chain - up to AutomationEngine::MAX_DEPTH levels.
 */
class AutomationContext
{
    public $container;
    public $rule;
    public $trigger;
    public $data;
    protected $depth;
    protected $ticket;

    public function __construct($container, array $rule, string $trigger, array $ticket, array $data, int $depth)
    {
        $this->container = $container;
        $this->rule = $rule;
        $this->trigger = $trigger;
        $this->ticket = $ticket;
        $this->data = $data;
        $this->depth = $depth;
    }

    /** The ticket as it is now (reloaded after every change). */
    public function ticket(): array
    {
        return $this->ticket;
    }

    public function reload(): array
    {
        $fresh = $this->container->get(TicketGateway::class)->getTicket($this->ticket['csTicketID']);
        if (!empty($fresh)) {
            $this->ticket = $fresh;
        }
        return $this->ticket;
    }

    /** Add a line to the ticket activity (internal: the requester does not see how the automation works). */
    public function log(string $line): void
    {
        $this->container->get(ActivityGateway::class)->log(
            $this->ticket['csTicketID'], 0, sprintf(__('Automation "%1$s": %2$s'), $this->rule['name'], $line), 'Event', 'Internal'
        );
    }

    /** Update ticket columns and log it. */
    public function update(array $changes, string $logLine): void
    {
        $this->container->get(TicketGateway::class)->updateTicket($this->ticket['csTicketID'], $changes);
        $this->log($logLine);
        $this->reload();
    }

    /** Move the ticket to a status (bypasses the workflow rules of the type) and fire status_changed. */
    public function changeStatus(string $status): void
    {
        $status = trim($status);
        if ($status === '' || $status === $this->ticket['status']) {
            return;
        }
        $from = $this->ticket['status'];
        $this->update(Workflow::statusChanges($this->ticket, $status), sprintf(__('status changed from %1$s to %2$s'), $from, $status));
        (new Notifier($this->container))->statusChanged($this->ticket, $status, 0);
        $this->fire('status_changed', ['from' => $from, 'to' => $status]);
    }

    /** Assign (0 / null = unassign) and fire ticket_assigned. */
    public function assign($gibbonPersonID, string $name = ''): void
    {
        $gibbonPersonID = (int)$gibbonPersonID;
        if ($gibbonPersonID === (int)$this->ticket['gibbonPersonIDAssignee']) {
            return;
        }
        $this->update(['gibbonPersonIDAssignee' => $gibbonPersonID ?: null], $gibbonPersonID ? sprintf(__('assigned to %1$s'), $name ?: $gibbonPersonID) : __('unassigned'));
        (new Notifier($this->container))->assigned($this->ticket, $gibbonPersonID, 0);
        $this->fire('ticket_assigned');
    }

    public function comment(string $body, bool $internal): void
    {
        $this->container->get(ActivityGateway::class)->log($this->ticket['csTicketID'], 0, $body, 'Comment', $internal ? 'Internal' : 'Public');
        if (!$internal) {
            (new Notifier($this->container))->commented($this->ticket, 0, $body);
        }
    }

    /** Send a notification about this ticket to the given people. */
    public function notify(array $gibbonPersonIDs, string $text): void
    {
        (new Notifier($this->container))->custom($this->ticket, $gibbonPersonIDs, $text, 0);
    }

    /** Fire another trigger for this ticket (counts as one level deeper). */
    public function fire(string $trigger, array $data = []): void
    {
        (new AutomationEngine($this->container, $this->depth + 1))->fire($trigger, $this->ticket['csTicketID'], $data);
        $this->reload();
    }
}
