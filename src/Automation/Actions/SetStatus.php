<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\administration\Automation\Actions;

use Gibbon\Module\administration\Automation\AutomationContext;
use Gibbon\Module\administration\Automation\BaseAction;

class SetStatus extends BaseAction
{
    public function label(): string
    {
        return __('Set status');
    }

    public function params($container): array
    {
        return [
            'status' => ['label' => __('Status'), 'type' => 'text', 'required' => true, 'description' => __('Usually the name of a board column, e.g. In Progress. Ignores the workflow rules.')],
        ];
    }

    public function execute(array $ticket, array $params, AutomationContext $ctx): void
    {
        $ctx->changeStatus((string)($params['status'] ?? ''));
    }
}
