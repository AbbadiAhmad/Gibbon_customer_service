<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\administration\Service;

use Gibbon\Module\administration\Automation\AutomationEngine;
use Gibbon\Module\administration\Domain\ActivityGateway;
use Gibbon\Module\administration\Domain\SpaceGateway;
use Gibbon\Module\administration\Domain\TeamGateway;
use Gibbon\Module\administration\Domain\TicketGateway;
use Gibbon\Module\administration\Domain\TicketTypeGateway;

/**
 * Quick create (the Jira "Create" shortcut): an agent raises a ticket in one step - type, title, a short summary and
 * optionally the assignee - without going through the request form of the type.
 *
 * Who: a global admin, or anybody who holds `edit_ticket` in at least one space (the agents). Whom to assign to: people
 * of the spaces where the user holds `assign_ticket` (admins: every space) and the members of the team of the chosen type.
 * Everything else about the ticket is the default of a new request (status New, priority Medium, the team of the type).
 * Required custom fields of the type are NOT asked here; the agent completes them on the ticket afterwards.
 */
class QuickCreate
{
    public static function canUse($container, int $gibbonPersonID, bool $isAdmin): bool
    {
        return $isAdmin || !empty($container->get(SpaceGateway::class)->selectSpaceIDsWithPermission($gibbonPersonID, Permissions::EDIT_TICKET));
    }

    /** People this user may assign a quick-created ticket to (gibbonPersonID => name), without the team of the type. */
    public static function assignees($container, int $gibbonPersonID, bool $isAdmin): array
    {
        $spaces = $container->get(SpaceGateway::class);
        $spaceIDs = $isAdmin
            ? array_column($spaces->selectAllWithCounts(), 'csSpaceID')
            : $spaces->selectSpaceIDsWithPermission($gibbonPersonID, Permissions::ASSIGN);

        return empty($spaceIDs) ? [] : $spaces->selectPeopleInSpaces($spaceIDs);
    }

    /** Active ticket types for the select (csTicketTypeID => name). */
    public static function typeOptions($container): array
    {
        $options = [];
        foreach ($container->get(TicketTypeGateway::class)->selectActive() as $type) {
            $options[$type['csTicketTypeID']] = $type['name'];
        }

        return $options;
    }

    /**
     * @param array $input csTicketTypeID, title, summary, gibbonPersonIDAssignee (all raw request values)
     * @return array [int $csTicketID, string $return] - the id and 'success0', or 0 and the Gibbon return code to redirect with
     */
    public static function create($container, int $gibbonPersonID, bool $isAdmin, array $input): array
    {
        if (!self::canUse($container, $gibbonPersonID, $isAdmin)) {
            return [0, 'error0'];
        }

        $type = $container->get(TicketTypeGateway::class)->getByID($input['csTicketTypeID'] ?? '');
        $title = mb_substr(trim((string)($input['title'] ?? '')), 0, 200);
        if (empty($type) || $type['active'] != 'Y' || $title === '') {
            return [0, 'error1'];
        }

        // The assignee is optional, but if given it must be somebody this user may assign to.
        $assignee = (int)($input['gibbonPersonIDAssignee'] ?? 0);
        $assigneeName = '';
        if ($assignee > 0) {
            $allowed = self::assignees($container, $gibbonPersonID, $isAdmin);
            if (!empty($type['csTeamID'])) {
                $allowed += $container->get(TeamGateway::class)->selectMembers($type['csTeamID']);
            }
            if (!isset($allowed[$assignee])) {
                return [0, 'error0'];
            }
            $assigneeName = $allowed[$assignee];
        }

        $tickets = $container->get(TicketGateway::class);
        $csTicketID = $tickets->createTicket([
            'csTicketTypeID'         => $type['csTicketTypeID'],
            'title'                  => $title,
            'description'            => trim((string)($input['summary'] ?? '')),
            'status'                 => 'New',
            'priority'               => 'Medium',
            'gibbonPersonIDReporter' => $gibbonPersonID,
            'gibbonPersonIDAssignee' => $assignee > 0 ? $assignee : null,
            'csTeamID'               => $type['csTeamID'] ?: null,
            'customData'             => [],
        ]);
        if (empty($csTicketID)) {
            return [0, 'error2'];
        }

        $activity = $container->get(ActivityGateway::class);
        $activity->log($csTicketID, $gibbonPersonID, __('Request raised (quick create)'));
        if ($assignee > 0) {
            $activity->log($csTicketID, $gibbonPersonID, sprintf(__('Assignee changed from %1$s to %2$s'), __('Unassigned'), $assigneeName));
        }

        // Same follow-up as a request from the portal: notify whoever works on it (the assignee if there is one), run the rules.
        (new Notifier($container))->ticketCreated($tickets->getTicket($csTicketID), $gibbonPersonID);
        (new AutomationEngine($container))->fire('ticket_created', $csTicketID);

        return [(int)$csTicketID, 'success0'];
    }
}
