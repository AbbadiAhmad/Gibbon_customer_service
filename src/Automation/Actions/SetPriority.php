<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\administration\Automation\Actions;

use Gibbon\Module\administration\Automation\AutomationContext;
use Gibbon\Module\administration\Automation\BaseAction;

class SetPriority extends BaseAction
{
    public function label(): string
    {
        return __('Set priority');
    }

    public function params($container): array
    {
        return [
            'priority' => ['label' => __('Priority'), 'type' => 'select', 'required' => true,
                'options' => ['Low' => __('Low'), 'Medium' => __('Medium'), 'High' => __('High'), 'Urgent' => __('Urgent')]],
        ];
    }

    public function execute(array $ticket, array $params, AutomationContext $ctx): void
    {
        $priority = $params['priority'] ?? '';
        if (in_array($priority, ['Low', 'Medium', 'High', 'Urgent'], true) && $priority !== $ticket['priority']) {
            $ctx->update(['priority' => $priority], sprintf(__('priority set to %1$s'), $priority));
        }
    }
}
