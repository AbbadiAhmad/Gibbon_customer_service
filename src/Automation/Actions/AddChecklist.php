<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\CustomerService\Automation\Actions;

use Gibbon\Module\CustomerService\Automation\AutomationContext;
use Gibbon\Module\CustomerService\Automation\BaseAction;

use Gibbon\Module\CustomerService\Domain\ChecklistGateway;

class AddChecklist extends BaseAction
{
    public function label(): string
    {
        return __('Add a checklist');
    }

    public function params($container): array
    {
        return [
            'title' => ['label' => __('Title'), 'type' => 'text', 'required' => true],
            'items' => ['label' => __('Items'), 'type' => 'textarea', 'description' => __('One per line.')],
        ];
    }

    public function execute(array $ticket, array $params, AutomationContext $ctx): void
    {
        $title = trim((string)($params['title'] ?? ''));
        if ($title === '') {
            return;
        }
        $lists = $ctx->container->get(ChecklistGateway::class);
        $csChecklistID = $lists->addChecklist($ticket['csTicketID'], $title);
        foreach (preg_split('/\R/', (string)($params['items'] ?? '')) as $item) {
            if (trim($item) !== '' && $csChecklistID) {
                $lists->addItem($csChecklistID, trim($item));
            }
        }
        $ctx->log(sprintf(__('checklist "%1$s" added'), $title));
    }
}
