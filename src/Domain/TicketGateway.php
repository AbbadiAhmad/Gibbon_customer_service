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
 * Tickets. Every list query takes the person and whether they are an administrator, so nobody is ever shown a ticket
 * they may not open: people see their own requests and the tickets of the types their teams handle.
 */
class TicketGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonServiceDeskTicket';
    private static $primaryKey = 'gibbonServiceDeskTicketID';
    private static $searchableColumns = ['gibbonServiceDeskTicket.subject', 'requester.surname', 'requester.preferredName'];

    /**
     * Filters understood here (see TicketFilters): type (ids), status (stage categories), stage (name),
     * assignee (me / none / person id), requester (me), priority, team (id).
     */
    public function queryTickets(QueryCriteria $criteria, $gibbonPersonID, bool $isAdmin)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonServiceDeskTicket.*',
                'gibbonServiceDeskTicketType.name AS typeName',
                'gibbonServiceDeskTicketType.color AS typeColor',
                'gibbonServiceDeskStage.name AS stageName',
                'gibbonServiceDeskStage.category AS stageCategory',
                'requester.title AS requesterTitle', 'requester.surname AS requesterSurname', 'requester.preferredName AS requesterPreferredName',
                'assignee.title AS assigneeTitle', 'assignee.surname AS assigneeSurname', 'assignee.preferredName AS assigneePreferredName',
            ])
            ->innerJoin('gibbonServiceDeskTicketType', 'gibbonServiceDeskTicketType.gibbonServiceDeskTicketTypeID=gibbonServiceDeskTicket.gibbonServiceDeskTicketTypeID')
            ->innerJoin('gibbonServiceDeskStage', 'gibbonServiceDeskStage.gibbonServiceDeskStageID=gibbonServiceDeskTicket.gibbonServiceDeskStageID')
            ->leftJoin('gibbonPerson AS requester', 'requester.gibbonPersonID=gibbonServiceDeskTicket.gibbonPersonIDRequester')
            ->leftJoin('gibbonPerson AS assignee', 'assignee.gibbonPersonID=gibbonServiceDeskTicket.gibbonPersonIDAssignee');

        if (!$isAdmin) {
            $query->where("(gibbonServiceDeskTicket.gibbonPersonIDRequester=:scopeRequester
                    OR gibbonServiceDeskTicket.gibbonServiceDeskTicketTypeID IN (
                        SELECT gibbonServiceDeskTicketTypeTeam.gibbonServiceDeskTicketTypeID
                        FROM gibbonServiceDeskTeamMember
                        JOIN gibbonServiceDeskTeam ON (gibbonServiceDeskTeam.gibbonServiceDeskTeamID=gibbonServiceDeskTeamMember.gibbonServiceDeskTeamID AND gibbonServiceDeskTeam.active='Y')
                        JOIN gibbonServiceDeskTicketTypeTeam ON (gibbonServiceDeskTicketTypeTeam.gibbonServiceDeskTeamID=gibbonServiceDeskTeam.gibbonServiceDeskTeamID)
                        WHERE gibbonServiceDeskTeamMember.gibbonPersonID=:scopeMember))")
                ->bindValue('scopeRequester', $gibbonPersonID)
                ->bindValue('scopeMember', $gibbonPersonID);
        }

        $criteria->addFilterRules([
            'type' => function ($query, $ids) {
                return $query
                    // +0 turns the zero-filled id into a plain number. (CAST ... AS UNSIGNED breaks in the query builder: it reads AS as an alias.)
                    ->where('FIND_IN_SET(gibbonServiceDeskTicket.gibbonServiceDeskTicketTypeID+0, :filterType)')
                    ->bindValue('filterType', $ids);
            },
            'status' => function ($query, $categories) {
                return $query
                    ->where('FIND_IN_SET(gibbonServiceDeskStage.category, :filterStatus)')
                    ->bindValue('filterStatus', $categories);
            },
            'stage' => function ($query, $name) {
                return $query
                    ->where('gibbonServiceDeskStage.name=:filterStage')
                    ->bindValue('filterStage', $name);
            },
            'assignee' => function ($query, $assignee) use ($gibbonPersonID) {
                if ($assignee == 'none') {
                    return $query->where('gibbonServiceDeskTicket.gibbonPersonIDAssignee IS NULL');
                }
                return $query
                    ->where('gibbonServiceDeskTicket.gibbonPersonIDAssignee=:filterAssignee')
                    ->bindValue('filterAssignee', $assignee == 'me' ? $gibbonPersonID : $assignee);
            },
            'requester' => function ($query, $requester) use ($gibbonPersonID) {
                return $query
                    ->where('gibbonServiceDeskTicket.gibbonPersonIDRequester=:filterRequester')
                    ->bindValue('filterRequester', $requester == 'me' ? $gibbonPersonID : $requester);
            },
            'priority' => function ($query, $priority) {
                return $query
                    ->where('gibbonServiceDeskTicket.priority=:filterPriority')
                    ->bindValue('filterPriority', $priority);
            },
            // Board view: closed tickets only while they are recent (date = oldest closing day still shown).
            'closedSince' => function ($query, $date) {
                return $query
                    ->where("(gibbonServiceDeskStage.category<>'Closed' OR gibbonServiceDeskTicket.timestampClosed>=:filterClosedSince)")
                    ->bindValue('filterClosedSince', $date);
            },
            'team' => function ($query, $gibbonServiceDeskTeamID) {
                return $query
                    ->where('gibbonServiceDeskTicket.gibbonServiceDeskTicketTypeID IN (SELECT gibbonServiceDeskTicketTypeID FROM gibbonServiceDeskTicketTypeTeam WHERE gibbonServiceDeskTeamID=:filterTeam)')
                    ->bindValue('filterTeam', $gibbonServiceDeskTeamID);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * One ticket with the names needed to show it.
     */
    public function getTicketByID($gibbonServiceDeskTicketID)
    {
        $data = ['gibbonServiceDeskTicketID' => $gibbonServiceDeskTicketID];
        $sql = "SELECT gibbonServiceDeskTicket.*,
                    gibbonServiceDeskTicketType.name AS typeName,
                    gibbonServiceDeskStage.name AS stageName, gibbonServiceDeskStage.category AS stageCategory,
                    requester.title AS requesterTitle, requester.surname AS requesterSurname, requester.preferredName AS requesterPreferredName,
                    assignee.title AS assigneeTitle, assignee.surname AS assigneeSurname, assignee.preferredName AS assigneePreferredName
                FROM gibbonServiceDeskTicket
                JOIN gibbonServiceDeskTicketType ON (gibbonServiceDeskTicketType.gibbonServiceDeskTicketTypeID=gibbonServiceDeskTicket.gibbonServiceDeskTicketTypeID)
                JOIN gibbonServiceDeskStage ON (gibbonServiceDeskStage.gibbonServiceDeskStageID=gibbonServiceDeskTicket.gibbonServiceDeskStageID)
                LEFT JOIN gibbonPerson AS requester ON (requester.gibbonPersonID=gibbonServiceDeskTicket.gibbonPersonIDRequester)
                LEFT JOIN gibbonPerson AS assignee ON (assignee.gibbonPersonID=gibbonServiceDeskTicket.gibbonPersonIDAssignee)
                WHERE gibbonServiceDeskTicket.gibbonServiceDeskTicketID=:gibbonServiceDeskTicketID";

        return $this->db()->selectOne($sql, $data) ?: [];
    }
}
