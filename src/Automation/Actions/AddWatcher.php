<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\CustomerService\Automation\Actions;

use Gibbon\Module\CustomerService\Automation\AutomationContext;
use Gibbon\Module\CustomerService\Automation\BaseAction;

use Gibbon\Module\CustomerService\Domain\WatcherGateway;

class AddWatcher extends BaseAction
{
    public function label(): string
    {
        return __('Add a watcher');
    }

    public function params($container): array
    {
        return ['person' => ['label' => __('Person'), 'type' => 'person', 'required' => true]];
    }

    public function execute(array $ticket, array $params, AutomationContext $ctx): void
    {
        $person = (int)($params['person'] ?? 0);
        if ($person > 0) {
            $ctx->container->get(WatcherGateway::class)->add($ticket['csTicketID'], $person);
            $ctx->log(sprintf(__('%1$s is now watching'), $this->personName($ctx->container, $person)));
        }
    }
}
