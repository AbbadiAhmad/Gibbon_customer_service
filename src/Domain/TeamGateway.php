<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/


namespace Gibbon\Module\ServiceDesk\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * Teams and their members.
 */
class TeamGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonServiceDeskTeam';
    private static $primaryKey = 'gibbonServiceDeskTeamID';
    private static $searchableColumns = ['gibbonServiceDeskTeam.name'];

    public function queryTeams(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonServiceDeskTeam.*',
                '(SELECT COUNT(*) FROM gibbonServiceDeskTeamMember WHERE gibbonServiceDeskTeamMember.gibbonServiceDeskTeamID=gibbonServiceDeskTeam.gibbonServiceDeskTeamID) AS memberCount',
                "(SELECT GROUP_CONCAT(gibbonServiceDeskTicketType.name ORDER BY gibbonServiceDeskTicketType.sequenceNumber SEPARATOR ', ') FROM gibbonServiceDeskTicketTypeTeam JOIN gibbonServiceDeskTicketType ON (gibbonServiceDeskTicketType.gibbonServiceDeskTicketTypeID=gibbonServiceDeskTicketTypeTeam.gibbonServiceDeskTicketTypeID) WHERE gibbonServiceDeskTicketTypeTeam.gibbonServiceDeskTeamID=gibbonServiceDeskTeam.gibbonServiceDeskTeamID) AS ticketTypes",
            ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Active teams as id => name, for select fields.
     */
    public function selectTeamOptions()
    {
        $sql = "SELECT gibbonServiceDeskTeamID AS value, name FROM gibbonServiceDeskTeam WHERE active='Y' ORDER BY name";

        return $this->db()->select($sql);
    }

    public function selectMembersByTeam($gibbonServiceDeskTeamID)
    {
        $data = ['gibbonServiceDeskTeamID' => $gibbonServiceDeskTeamID];
        $sql = "SELECT gibbonServiceDeskTeamMember.gibbonServiceDeskTeamMemberID, gibbonServiceDeskTeamMember.gibbonServiceDeskTeamID, gibbonPerson.gibbonPersonID, gibbonPerson.title, gibbonPerson.surname, gibbonPerson.preferredName, gibbonPerson.email, gibbonPerson.status
                FROM gibbonServiceDeskTeamMember
                JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=gibbonServiceDeskTeamMember.gibbonPersonID)
                WHERE gibbonServiceDeskTeamMember.gibbonServiceDeskTeamID=:gibbonServiceDeskTeamID
                ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    public function insertMember($gibbonServiceDeskTeamID, $gibbonPersonID)
    {
        $data = ['gibbonServiceDeskTeamID' => $gibbonServiceDeskTeamID, 'gibbonPersonID' => $gibbonPersonID];
        $sql = "INSERT IGNORE INTO gibbonServiceDeskTeamMember SET gibbonServiceDeskTeamID=:gibbonServiceDeskTeamID, gibbonPersonID=:gibbonPersonID";

        return $this->db()->insert($sql, $data);
    }

    public function deleteMember($gibbonServiceDeskTeamID, $gibbonServiceDeskTeamMemberID)
    {
        $data = ['gibbonServiceDeskTeamID' => $gibbonServiceDeskTeamID, 'gibbonServiceDeskTeamMemberID' => $gibbonServiceDeskTeamMemberID];
        $sql = "DELETE FROM gibbonServiceDeskTeamMember WHERE gibbonServiceDeskTeamID=:gibbonServiceDeskTeamID AND gibbonServiceDeskTeamMemberID=:gibbonServiceDeskTeamMemberID";

        return $this->db()->delete($sql, $data);
    }

    /**
     * Removes a team with its members, its links to ticket types and the shared filters limited to it.
     */
    public function deleteTeam($gibbonServiceDeskTeamID)
    {
        $data = ['gibbonServiceDeskTeamID' => $gibbonServiceDeskTeamID];
        $this->db()->delete("DELETE FROM gibbonServiceDeskTeamMember WHERE gibbonServiceDeskTeamID=:gibbonServiceDeskTeamID", $data);
        $this->db()->delete("DELETE FROM gibbonServiceDeskTicketTypeTeam WHERE gibbonServiceDeskTeamID=:gibbonServiceDeskTeamID", $data);
        $this->db()->update("UPDATE gibbonServiceDeskFilter SET gibbonServiceDeskTeamID=NULL WHERE gibbonServiceDeskTeamID=:gibbonServiceDeskTeamID", $data);

        return $this->delete($gibbonServiceDeskTeamID);
    }

    /**
     * IDs of the active teams a person belongs to.
     */
    public function selectTeamIDsByPerson($gibbonPersonID): array
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT gibbonServiceDeskTeam.gibbonServiceDeskTeamID
                FROM gibbonServiceDeskTeamMember
                JOIN gibbonServiceDeskTeam ON (gibbonServiceDeskTeam.gibbonServiceDeskTeamID=gibbonServiceDeskTeamMember.gibbonServiceDeskTeamID)
                WHERE gibbonServiceDeskTeamMember.gibbonPersonID=:gibbonPersonID AND gibbonServiceDeskTeam.active='Y'";

        return $this->db()->select($sql, $data)->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * IDs of the ticket types a person handles through an active team.
     */
    public function selectTicketTypeIDsByPerson($gibbonPersonID): array
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT DISTINCT gibbonServiceDeskTicketTypeTeam.gibbonServiceDeskTicketTypeID
                FROM gibbonServiceDeskTeamMember
                JOIN gibbonServiceDeskTeam ON (gibbonServiceDeskTeam.gibbonServiceDeskTeamID=gibbonServiceDeskTeamMember.gibbonServiceDeskTeamID)
                JOIN gibbonServiceDeskTicketTypeTeam ON (gibbonServiceDeskTicketTypeTeam.gibbonServiceDeskTeamID=gibbonServiceDeskTeam.gibbonServiceDeskTeamID)
                WHERE gibbonServiceDeskTeamMember.gibbonPersonID=:gibbonPersonID AND gibbonServiceDeskTeam.active='Y'";

        return $this->db()->select($sql, $data)->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * The people (active users) in the active teams that handle a ticket type.
     */
    public function selectMembersByTicketType($gibbonServiceDeskTicketTypeID)
    {
        $data = ['gibbonServiceDeskTicketTypeID' => $gibbonServiceDeskTicketTypeID];
        $sql = "SELECT DISTINCT gibbonPerson.gibbonPersonID, gibbonPerson.title, gibbonPerson.surname, gibbonPerson.preferredName
                FROM gibbonServiceDeskTicketTypeTeam
                JOIN gibbonServiceDeskTeam ON (gibbonServiceDeskTeam.gibbonServiceDeskTeamID=gibbonServiceDeskTicketTypeTeam.gibbonServiceDeskTeamID)
                JOIN gibbonServiceDeskTeamMember ON (gibbonServiceDeskTeamMember.gibbonServiceDeskTeamID=gibbonServiceDeskTeam.gibbonServiceDeskTeamID)
                JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=gibbonServiceDeskTeamMember.gibbonPersonID)
                WHERE gibbonServiceDeskTicketTypeTeam.gibbonServiceDeskTicketTypeID=:gibbonServiceDeskTicketTypeID
                AND gibbonServiceDeskTeam.active='Y' AND gibbonPerson.status='Full'
                ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    /**
     * Everyone who is in an active team, as gibbonPersonID => [surname, preferredName], for the assignee filter.
     */
    public function selectAllMembers()
    {
        $sql = "SELECT DISTINCT gibbonPerson.gibbonPersonID, gibbonPerson.surname, gibbonPerson.preferredName
                FROM gibbonServiceDeskTeamMember
                JOIN gibbonServiceDeskTeam ON (gibbonServiceDeskTeam.gibbonServiceDeskTeamID=gibbonServiceDeskTeamMember.gibbonServiceDeskTeamID AND gibbonServiceDeskTeam.active='Y')
                JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=gibbonServiceDeskTeamMember.gibbonPersonID)
                WHERE gibbonPerson.status='Full'
                ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

        return $this->db()->select($sql);
    }

    /**
     * The Service Desk administrators: active people with a role that holds the action "Manage Ticket Types".
     * They are told about a new ticket when no team member is there to receive it.
     */
    public function selectAdministrators()
    {
        $sql = "SELECT DISTINCT gibbonPerson.gibbonPersonID
                FROM gibbonAction
                JOIN gibbonModule ON (gibbonModule.gibbonModuleID=gibbonAction.gibbonModuleID)
                JOIN gibbonPermission ON (gibbonPermission.gibbonActionID=gibbonAction.gibbonActionID)
                JOIN gibbonPerson ON (FIND_IN_SET(gibbonPermission.gibbonRoleID, gibbonPerson.gibbonRoleIDAll))
                WHERE gibbonModule.name='ServiceDesk' AND gibbonAction.name='Manage Ticket Types' AND gibbonPerson.status='Full'";

        return $this->db()->select($sql);
    }
}
