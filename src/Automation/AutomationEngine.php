<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\administration\Automation;

use Gibbon\Module\administration\Domain\RuleGateway;
use Gibbon\Module\administration\Domain\TicketGateway;
use Gibbon\Module\administration\Service\BoardFilter;

/**
 * Runs the automation rules of a trigger:
 *
 *   trigger (something happened to a ticket)  ->  conditions (same format as board filters)  ->  actions (ActionRegistry)
 *
 * fire() is called by the ticket pages and by the reminder cron. It never throws.
 */
class AutomationEngine
{
    /** Chained triggers (rule A changes the status, rule B reacts to that...) stop at this depth. */
    const MAX_DEPTH = 3;
    /** A rule runs at most this many times per ticket in one request (loop protection). */
    const MAX_RUNS = 3;

    private static $runs = [];

    protected $container;
    protected $depth;

    public function __construct($container, int $depth = 0)
    {
        $this->container = $container;
        $this->depth = $depth;
    }

    /** @return array trigger key => label */
    public static function triggers(): array
    {
        return [
            'ticket_created'  => __('A request is raised'),
            'status_changed'  => __('The status changes'),
            'ticket_assigned' => __('The ticket is assigned'),
            'comment_added'   => __('A public comment is added'),
            'ticket_reopened' => __('The requester reopens the ticket'),
            'ticket_due_soon' => __('The due date is near (reminder)'),
            'ticket_overdue'  => __('The ticket becomes overdue'),
        ];
    }

    /**
     * @param array $data extra facts about the event, e.g. ['from' => 'New', 'to' => 'Resolved']
     * @return int the number of rules that ran
     */
    public function fire(string $trigger, $csTicketID, array $data = []): int
    {
        if ($this->depth >= self::MAX_DEPTH) {
            return 0;
        }

        $ran = 0;
        try {
            $tickets = $this->container->get(TicketGateway::class);
            $ticket = $tickets->getTicket($csTicketID);
            if (empty($ticket)) {
                return 0;
            }

            foreach ($this->container->get(RuleGateway::class)->selectActive($trigger, $ticket['csTicketTypeID']) as $rule) {
                $runKey = $rule['csAutomationRuleID'].'-'.$ticket['csTicketID'];
                if ((self::$runs[$runKey] ?? 0) >= self::MAX_RUNS) {
                    continue;
                }

                // Conditions are evaluated against the ticket as it is now (previous rules may have changed it).
                $conditions = BoardFilter::decode($rule['conditions']);
                if (!empty($conditions) && empty($tickets->selectByFilter($conditions, $ticket['csTicketID'], 1))) {
                    continue;
                }

                self::$runs[$runKey] = (self::$runs[$runKey] ?? 0) + 1;
                $ran++;
                $this->runActions($rule, $trigger, $ticket, $data);
                $ticket = $tickets->getTicket($csTicketID) ?: $ticket;
            }
        } catch (\Throwable $e) {
            error_log('administration automation failed: '.$e->getMessage());
        }

        return $ran;
    }

    protected function runActions(array $rule, string $trigger, array $ticket, array $data): void
    {
        $ctx = new AutomationContext($this->container, $rule, $trigger, $ticket, $data, $this->depth);

        foreach ((array)json_decode((string)$rule['actions'], true) as $definition) {
            // An action that no longer exists (class removed) is skipped, not an error.
            $action = ActionRegistry::get((string)($definition['type'] ?? ''));
            if ($action === null) {
                continue;
            }
            try {
                $action->execute($ctx->ticket(), (array)($definition['params'] ?? []), $ctx);
            } catch (\Throwable $e) {
                error_log('administration automation action '.$action->key().' failed: '.$e->getMessage());
            }
            $ctx->reload();
        }
    }
}
