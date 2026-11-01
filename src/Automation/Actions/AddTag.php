<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\administration\Automation\Actions;

use Gibbon\Module\administration\Automation\AutomationContext;
use Gibbon\Module\administration\Automation\BaseAction;

use Gibbon\Module\administration\Domain\TagGateway;

class AddTag extends BaseAction
{
    public function label(): string
    {
        return __('Add tags');
    }

    public function params($container): array
    {
        return ['tags' => ['label' => __('Tags'), 'type' => 'text', 'required' => true, 'description' => __('Comma separated.')]];
    }

    public function execute(array $ticket, array $params, AutomationContext $ctx): void
    {
        $added = $ctx->container->get(TagGateway::class)->addToTicket($ticket['csTicketID'], TagGateway::parse($params['tags'] ?? ''));
        if (!empty($added)) {
            $ctx->log(sprintf(__('tags added: %1$s'), implode(', ', $added)));
        }
    }
}
