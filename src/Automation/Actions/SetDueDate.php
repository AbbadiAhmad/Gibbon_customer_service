<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\CustomerService\Automation\Actions;

use Gibbon\Module\CustomerService\Automation\AutomationContext;
use Gibbon\Module\CustomerService\Automation\BaseAction;

class SetDueDate extends BaseAction
{
    public function label(): string
    {
        return __('Set due date');
    }

    public function params($container): array
    {
        return ['days' => ['label' => __('Days from today'), 'type' => 'number', 'required' => true, 'description' => __('0 = today. Only sets the date when the ticket has no due date yet, unless overwrite is set to Yes.')],
            'overwrite' => ['label' => __('Overwrite an existing due date'), 'type' => 'select', 'options' => ['N' => __('No'), 'Y' => __('Yes')]]];
    }

    public function execute(array $ticket, array $params, AutomationContext $ctx): void
    {
        if (!empty($ticket['dateDue']) && ($params['overwrite'] ?? 'N') !== 'Y') {
            return;
        }
        $date = date('Y-m-d', strtotime('+'.max(0, (int)($params['days'] ?? 0)).' days'));
        $ctx->update(['dateDue' => $date], sprintf(__('due date set to %1$s'), $date));
    }
}
