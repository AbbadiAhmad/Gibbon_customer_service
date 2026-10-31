<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\CustomerService\Automation;

/**
 * One thing an automation rule can DO to a ticket.
 *
 * To add an action: create a class in src/Automation/Actions/ that extends BaseAction (see the existing ones,
 * most are ~20 lines). It is discovered automatically by ActionRegistry - nothing else to register - and
 * appears in the rule editor with the parameters you declare in params().
 */
interface AutomationAction
{
    /** Stored in the rule. Stable! (BaseAction derives it from the class name: SetPriority => set_priority) */
    public function key(): string;

    /** Name shown in the rule editor. */
    public function label(): string;

    /**
     * Parameters the rule editor asks for.
     *
     * @param mixed $container
     * @return array name => ['label' => string, 'type' => text|textarea|number|select|person|team, 'options' => [value => label] (select),
     *                        'required' => bool, 'description' => string]
     */
    public function params($container): array;

    /** Short text for the rule list and the ticket activity. */
    public function describe(array $params, $container): string;

    /**
     * Do it. $ticket is the ticket as it is now (also available as $ctx->ticket()).
     * Change things through $ctx (update, changeStatus, assign, comment, notify, fire) so that activity, notifications
     * and follow-up triggers work; an exception is caught and logged, it never breaks the request.
     */
    public function execute(array $ticket, array $params, AutomationContext $ctx): void;
}
