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
 * Ticket types (the services people can request) and the teams that handle them.
 */
class TicketTypeGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonServiceDeskTicketType';
    private static $primaryKey = 'gibbonServiceDeskTicketTypeID';
    private static $searchableColumns = ['gibbonServiceDeskTicketType.name'];

    public function queryTicketTypes(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonServiceDeskTicketType.*',
                "(SELECT GROUP_CONCAT(gibbonServiceDeskTeam.name ORDER BY gibbonServiceDeskTeam.name SEPARATOR ', ') FROM gibbonServiceDeskTicketTypeTeam JOIN gibbonServiceDeskTeam ON (gibbonServiceDeskTeam.gibbonServiceDeskTeamID=gibbonServiceDeskTicketTypeTeam.gibbonServiceDeskTeamID) WHERE gibbonServiceDeskTicketTypeTeam.gibbonServiceDeskTicketTypeID=gibbonServiceDeskTicketType.gibbonServiceDeskTicketTypeID) AS teams",
                "(SELECT COUNT(*) FROM gibbonServiceDeskTypeNotification WHERE gibbonServiceDeskTypeNotification.gibbonServiceDeskTicketTypeID=gibbonServiceDeskTicketType.gibbonServiceDeskTicketTypeID AND (newInApp='Y' OR newEmail='Y')) AS notifiedNew",
                "(SELECT COUNT(*) FROM gibbonServiceDeskTeam t JOIN gibbonServiceDeskTicketTypeTeam tt ON (tt.gibbonServiceDeskTeamID=t.gibbonServiceDeskTeamID) WHERE tt.gibbonServiceDeskTicketTypeID=gibbonServiceDeskTicketType.gibbonServiceDeskTicketTypeID AND t.active='Y' AND t.gibbonPersonIDDefaultAssignee IS NOT NULL) AS defaultAssignees",
            ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Active ticket types a role category may request, in menu order.
     */
    public function selectRequestableTypes($roleCategory)
    {
        $data = ['roleCategory' => $roleCategory];
        $sql = "SELECT * FROM gibbonServiceDeskTicketType WHERE active='Y' AND FIND_IN_SET(:roleCategory, roleCategories) ORDER BY sequenceNumber, name";

        return $this->db()->select($sql, $data);
    }

    /**
     * All ticket types as id => name, for select fields.
     */
    public function selectTypeOptions()
    {
        $sql = "SELECT gibbonServiceDeskTicketTypeID AS value, name FROM gibbonServiceDeskTicketType ORDER BY sequenceNumber, name";

        return $this->db()->select($sql);
    }

    public function selectTeamIDsByType($gibbonServiceDeskTicketTypeID): array
    {
        $data = ['gibbonServiceDeskTicketTypeID' => $gibbonServiceDeskTicketTypeID];
        $sql = "SELECT gibbonServiceDeskTeamID FROM gibbonServiceDeskTicketTypeTeam WHERE gibbonServiceDeskTicketTypeID=:gibbonServiceDeskTicketTypeID";

        return $this->db()->select($sql, $data)->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Replaces the teams that handle a ticket type.
     */
    public function updateTeams($gibbonServiceDeskTicketTypeID, array $teamIDs)
    {
        $data = ['gibbonServiceDeskTicketTypeID' => $gibbonServiceDeskTicketTypeID];
        $this->db()->delete("DELETE FROM gibbonServiceDeskTicketTypeTeam WHERE gibbonServiceDeskTicketTypeID=:gibbonServiceDeskTicketTypeID", $data);

        foreach (array_unique($teamIDs) as $gibbonServiceDeskTeamID) {
            $this->db()->insert(
                "INSERT INTO gibbonServiceDeskTicketTypeTeam SET gibbonServiceDeskTicketTypeID=:gibbonServiceDeskTicketTypeID, gibbonServiceDeskTeamID=:gibbonServiceDeskTeamID",
                $data + ['gibbonServiceDeskTeamID' => $gibbonServiceDeskTeamID]
            );
        }
    }

    public function countTickets($gibbonServiceDeskTicketTypeID): int
    {
        $data = ['gibbonServiceDeskTicketTypeID' => $gibbonServiceDeskTicketTypeID];
        $sql = "SELECT COUNT(*) FROM gibbonServiceDeskTicket WHERE gibbonServiceDeskTicketTypeID=:gibbonServiceDeskTicketTypeID";

        return (int) $this->db()->selectOne($sql, $data);
    }

    /**
     * Removes a ticket type with its form fields, workflow and team links. Only for types without tickets.
     */
    public function deleteType($gibbonServiceDeskTicketTypeID)
    {
        $data = ['gibbonServiceDeskTicketTypeID' => $gibbonServiceDeskTicketTypeID];
        $this->db()->delete("DELETE FROM gibbonServiceDeskField WHERE gibbonServiceDeskTicketTypeID=:gibbonServiceDeskTicketTypeID", $data);
        $this->db()->delete("DELETE FROM gibbonServiceDeskStage WHERE gibbonServiceDeskTicketTypeID=:gibbonServiceDeskTicketTypeID", $data);
        $this->db()->delete("DELETE FROM gibbonServiceDeskTypeNotification WHERE gibbonServiceDeskTicketTypeID=:gibbonServiceDeskTicketTypeID", $data);
        $this->db()->delete("DELETE FROM gibbonServiceDeskTicketTypeTeam WHERE gibbonServiceDeskTicketTypeID=:gibbonServiceDeskTicketTypeID", $data);

        return $this->delete($gibbonServiceDeskTicketTypeID);
    }

    /**
     * Every active ticket type, for people who may create tickets on behalf of others.
     */
    public function selectActiveTypes()
    {
        $sql = "SELECT * FROM gibbonServiceDeskTicketType WHERE active='Y' ORDER BY sequenceNumber, name";

        return $this->db()->select($sql);
    }
}
