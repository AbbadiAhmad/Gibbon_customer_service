<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\administration\Automation\Actions;

use Gibbon\Module\administration\Automation\AutomationContext;
use Gibbon\Module\administration\Automation\BaseAction;

class AddComment extends BaseAction
{
    public function label(): string
    {
        return __('Add a comment');
    }

    public function params($container): array
    {
        return [
            'body'       => ['label' => __('Comment'), 'type' => 'textarea', 'required' => true],
            'visibility' => ['label' => __('Visibility'), 'type' => 'select', 'options' => ['Public' => __('Reply to the requester'), 'Internal' => __('Internal note')]],
        ];
    }

    public function execute(array $ticket, array $params, AutomationContext $ctx): void
    {
        $body = trim((string)($params['body'] ?? ''));
        if ($body !== '') {
            $ctx->comment($body, ($params['visibility'] ?? 'Public') === 'Internal');
        }
    }
}
