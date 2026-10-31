<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\CustomerService\Automation\Actions;

use Gibbon\Module\CustomerService\Automation\AutomationContext;
use Gibbon\Module\CustomerService\Automation\BaseAction;

use Gibbon\Module\CustomerService\Domain\TeamGateway;
use Gibbon\Module\CustomerService\Domain\WatcherGateway;
use Gibbon\Module\CustomerService\Service\Notifier;

class SendNotification extends BaseAction
{
    public function label(): string
    {
        return __('Send a notification');
    }

    public function params($container): array
    {
        return [
            'recipient' => ['label' => __('To'), 'type' => 'select', 'required' => true, 'options' => [
                'reporter' => __('The requester'), 'assignee' => __('The assignee'), 'team' => __('The members of the ticket team'),
                'watchers' => __('The watchers'), 'staff' => __('Whoever works on the ticket (assignee, team or space agents)'), 'person' => __('A specific person (below)'),
            ]],
            'person'  => ['label' => __('Person'), 'type' => 'person', 'description' => __('Only used when "To" is a specific person.')],
            'message' => ['label' => __('Message'), 'type' => 'textarea', 'required' => true],
        ];
    }

    public function execute(array $ticket, array $params, AutomationContext $ctx): void
    {
        $message = trim((string)($params['message'] ?? ''));
        switch ($params['recipient'] ?? '') {
            case 'reporter':
                $to = [$ticket['gibbonPersonIDReporter']];
                break;
            case 'assignee':
                $to = [$ticket['gibbonPersonIDAssignee']];
                break;
            case 'team':
                $to = empty($ticket['csTeamID']) ? [] : array_keys($ctx->container->get(TeamGateway::class)->selectMembers($ticket['csTeamID']));
                break;
            case 'watchers':
                $to = $ctx->container->get(WatcherGateway::class)->selectPersonIDs($ticket['csTicketID']);
                break;
            case 'staff':
                $to = (new Notifier($ctx->container))->staffRecipients($ticket);
                break;
            case 'person':
                $to = [$params['person'] ?? 0];
                break;
            default:
                $to = [];
        }

        if ($message !== '' && !empty(array_filter($to))) {
            $ctx->notify($to, $message);
        }
    }
}
