<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\CustomerService\Automation\Actions;

use Gibbon\Module\CustomerService\Automation\AutomationContext;
use Gibbon\Module\CustomerService\Automation\BaseAction;

class AssignToUser extends BaseAction
{
    public function label(): string
    {
        return __('Assign to a person');
    }

    public function params($container): array
    {
        return ['person' => ['label' => __('Person'), 'type' => 'person', 'required' => true]];
    }

    public function execute(array $ticket, array $params, AutomationContext $ctx): void
    {
        $person = (int)($params['person'] ?? 0);
        if ($person > 0) {
            $ctx->assign($person, $this->personName($ctx->container, $person));
        }
    }
}
