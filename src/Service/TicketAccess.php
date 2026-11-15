<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/

namespace Gibbon\Module\ServiceDesk\Service;

use Gibbon\Contracts\Database\Connection;
use Gibbon\Contracts\Services\Session;
use Gibbon\Module\ServiceDesk\Domain\TeamGateway;
use Gibbon\Module\ServiceDesk\Domain\StageGateway;
use Gibbon\Module\ServiceDesk\Domain\GuardianGateway;

/**
 * Record-level rules on top of Gibbon's action permissions (which only say that a page may be used).
 *
 *  - Administrator: holds the action "Manage Ticket Types". Sees and manages every ticket.
 *  - Team member: belongs to an active team that handles the ticket's type. Sees and manages those tickets.
 *  - Requester: sees their own tickets and can comment on them, withdraw them and reopen them.
 *  - Family: the other adults of the requester's family (with data access) are treated like the requester for
 *    ticket types that are shared with the family.
 */
class TicketAccess
{
    protected $session;
    protected $db;
    protected $teamGateway;
    protected $stageGateway;
    protected $guardianGateway;
    protected $isAdmin;
    protected $typeIDs;
    protected $levels = [];

    /**
     * A closed ticket can be reopened by its requester for this many days after it was closed.
     */
    public const REOPEN_DAYS = 30;

    public function __construct(Session $session, Connection $db, TeamGateway $teamGateway, StageGateway $stageGateway, GuardianGateway $guardianGateway)
    {
        $this->session = $session;
        $this->db = $db;
        $this->teamGateway = $teamGateway;
        $this->stageGateway = $stageGateway;
        $this->guardianGateway = $guardianGateway;
    }

    public function isAdmin(): bool
    {
        if ($this->isAdmin === null) {
            $this->isAdmin = isActionAccessible($this->session->get('guid'), $this->db->getConnection(), '/modules/ServiceDesk/types_manage.php');
        }

        return $this->isAdmin;
    }

    /**
     * IDs of the ticket types the current user handles as a team member.
     */
    public function getTeamTypeIDs(): array
    {
        if ($this->typeIDs === null) {
            $this->typeIDs = array_map('intval', $this->teamGateway->selectTicketTypeIDsByPerson($this->session->get('gibbonPersonID')));
        }

        return $this->typeIDs;
    }

    /**
     * Does the user work on tickets at all (administrator or member of a team with ticket types)?
     */
    public function isAgent(): bool
    {
        return $this->isAdmin() || !empty($this->getTeamTypeIDs());
    }

    public function isRequester(array $ticket): bool
    {
        return !empty($ticket) && intval($ticket['gibbonPersonIDRequester']) === intval($this->session->get('gibbonPersonID'));
    }

    /**
     * May the user change the ticket (stage, assignee, priority) and read or write internal notes?
     */
    public function canManage(array $ticket): bool
    {
        if (empty($ticket)) return false;

        return $this->isAdmin() || in_array(intval($ticket['gibbonServiceDeskTicketTypeID']), $this->getTeamTypeIDs(), true);
    }

    /**
     * Another adult of the requester's family, for a ticket type shared with the family.
     */
    public function isFamilyMember(array $ticket): bool
    {
        if (empty($ticket) || ($ticket['shareWithFamily'] ?? 'N') != 'Y' || $this->isRequester($ticket)) return false;

        return $this->guardianGateway->isCoAdult($this->session->get('gibbonPersonID'), $ticket['gibbonPersonIDRequester']);
    }

    /**
     * The requester, or another adult of their family: the people who may comment, withdraw and reopen.
     */
    public function isRequesterSide(array $ticket): bool
    {
        return $this->isRequester($ticket) || $this->isFamilyMember($ticket);
    }

    public function canView(array $ticket): bool
    {
        return $this->isRequesterSide($ticket) || $this->canManage($ticket);
    }

    /**
     * May the user withdraw the request? Requester side only, if the ticket type lets requesters withdraw, while the
     * ticket is not finished, and only when the workflow has a "Withdrawn" stage to send it to.
     */
    public function canWithdraw(array $ticket): bool
    {
        return $this->isRequesterSide($ticket)
            && ($ticket['allowWithdraw'] ?? 'Y') == 'Y'
            && in_array($ticket['stageCategory'], ['Open', 'In Progress'], true)
            && !empty($this->stageGateway->getWithdrawnStage($ticket['gibbonServiceDeskTicketTypeID']));
    }

    /**
     * May the user reopen the request? Requester side only, if the ticket type lets requesters reopen, for
     * REOPEN_DAYS after it was closed.
     */
    public function canReopen(array $ticket): bool
    {
        return $this->isRequesterSide($ticket)
            && ($ticket['allowReopen'] ?? 'Y') == 'Y'
            && $ticket['stageCategory'] == 'Closed'
            && !empty($ticket['timestampClosed'])
            && strtotime($ticket['timestampClosed']) >= strtotime('-'.self::REOPEN_DAYS.' days');
    }

    /**
     * May the user permanently delete the ticket? Needs the action "Delete Tickets" and sight of the ticket.
     */
    public function canDelete(array $ticket): bool
    {
        return isActionAccessible($this->session->get('guid'), $this->db->getConnection(), '/modules/ServiceDesk/tickets_delete.php') && $this->canView($ticket);
    }

    /**
     * Does the user hold "New Request_onBehalf" (create tickets on behalf of others)? They then see every active
     * service and may send a request for another person.
     */
    public function canCreateOnBehalf(): bool
    {
        return getHighestGroupedAction($this->session->get('guid'), '/modules/ServiceDesk/request_new.php', $this->db->getConnection()) == 'New Request_onBehalf';
    }

    /**
     * The level the current role holds in a "_my" / "_all" action family of this module:
     * "all", "my" or "" (none). Used for "Edit Comments" and "Change Comment Visibility".
     *
     * Gibbon's getHighestGroupedAction() cannot be used here: both families list the same page, and it would return
     * only the strongest action of the two together.
     */
    public function getActionLevel(string $family): string
    {
        if (!isset($this->levels[$family])) {
            $data = ['gibbonRoleID' => $this->session->get('gibbonRoleIDCurrent'), 'my' => $family.'_my', 'all' => $family.'_all'];
            $sql = "SELECT gibbonAction.name
                    FROM gibbonAction
                    JOIN gibbonModule ON (gibbonModule.gibbonModuleID=gibbonAction.gibbonModuleID)
                    JOIN gibbonPermission ON (gibbonPermission.gibbonActionID=gibbonAction.gibbonActionID)
                    WHERE gibbonModule.name='ServiceDesk' AND gibbonModule.active='Y'
                    AND gibbonPermission.gibbonRoleID=:gibbonRoleID
                    AND gibbonAction.name IN (:my, :all)";

            $names = $this->db->select($sql, $data)->fetchAll(\PDO::FETCH_COLUMN);
            $this->levels[$family] = in_array($family.'_all', $names) ? 'all' : (in_array($family.'_my', $names) ? 'my' : '');
        }

        return $this->levels[$family];
    }

    /**
     * Is this a comment written by a person (not a log line), that the user may see on a ticket they may see,
     * and does their level in the action family cover it (own comment, or any)?
     */
    protected function coversComment(string $family, array $ticket, array $comment): bool
    {
        if (empty($comment) || !in_array($comment['type'], ['Comment', 'Internal Note'], true)) return false;
        if (!$this->canView($ticket)) return false;
        if ($comment['type'] == 'Internal Note' && !$this->canManage($ticket)) return false;

        $level = $this->getActionLevel($family);
        $own = intval($comment['gibbonPersonID']) === intval($this->session->get('gibbonPersonID'));

        return $level == 'all' || ($level == 'my' && $own);
    }

    /**
     * May the user change the text of the comment, or delete it? (Action "Edit Comments".)
     */
    public function canEditComment(array $ticket, array $comment): bool
    {
        return $this->coversComment('Edit Comments', $ticket, $comment);
    }

    /**
     * May the user switch the comment between public and internal? (Action "Change Comment Visibility".)
     * Only people who work on the ticket: nobody else can see internal notes.
     */
    public function canChangeCommentVisibility(array $ticket, array $comment): bool
    {
        return $this->canManage($ticket) && $this->coversComment('Change Comment Visibility', $ticket, $comment);
    }
}
