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

/**
 * Record-level rules on top of Gibbon's action permissions (which only say that a page may be used).
 *
 *  - Administrator: holds the action "Manage Ticket Types". Sees and manages every ticket.
 *  - Team member: belongs to an active team that handles the ticket's type. Sees and manages those tickets.
 *  - Requester: sees their own tickets and can comment on them.
 */
class TicketAccess
{
    protected $session;
    protected $db;
    protected $teamGateway;
    protected $isAdmin;
    protected $typeIDs;

    public function __construct(Session $session, Connection $db, TeamGateway $teamGateway)
    {
        $this->session = $session;
        $this->db = $db;
        $this->teamGateway = $teamGateway;
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

    public function canView(array $ticket): bool
    {
        return $this->isRequester($ticket) || $this->canManage($ticket);
    }
}
