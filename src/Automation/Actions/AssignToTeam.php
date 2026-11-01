<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\administration\Automation\Actions;

use Gibbon\Module\administration\Automation\AutomationContext;
use Gibbon\Module\administration\Automation\BaseAction;

use Gibbon\Module\administration\Domain\TeamGateway;

class AssignToTeam extends BaseAction
{
    public function label(): string
    {
        return __('Route to a team');
    }

    public function params($container): array
    {
        return ['team' => ['label' => __('Team'), 'type' => 'team', 'required' => true]];
    }

    public function execute(array $ticket, array $params, AutomationContext $ctx): void
    {
        $teams = $ctx->container->get(TeamGateway::class)->selectOptions();
        $team = (int)($params['team'] ?? 0);
        if (isset($teams[$team]) && $team !== (int)$ticket['csTeamID']) {
            $ctx->update(['csTeamID' => $team], sprintf(__('routed to team %1$s'), $teams[$team]));
        }
    }
}
