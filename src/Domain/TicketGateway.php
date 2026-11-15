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
    // The search is a filter of its own (see 'search' below): it also looks in the answers, names and comments.
    private static $searchableColumns = [];

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
                'gibbonServiceDeskTicketType.shareWithFamily',
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
            // Own requests; requests of the other adults of the family for ticket types shared with the family; and the
            // tickets of the ticket types the person's teams handle.
            $query->where("(gibbonServiceDeskTicket.gibbonPersonIDRequester=:scopeRequester
                    OR (gibbonServiceDeskTicketType.shareWithFamily='Y' AND gibbonServiceDeskTicket.gibbonPersonIDRequester IN (
                        SELECT requesterAdult.gibbonPersonID
                        FROM gibbonFamilyAdult AS viewerAdult
                        JOIN gibbonFamilyAdult AS requesterAdult ON (requesterAdult.gibbonFamilyID=viewerAdult.gibbonFamilyID)
                        WHERE viewerAdult.gibbonPersonID=:scopeFamily AND viewerAdult.childDataAccess='Y'))
                    OR gibbonServiceDeskTicket.gibbonServiceDeskTicketTypeID IN (
                        SELECT gibbonServiceDeskTicketTypeTeam.gibbonServiceDeskTicketTypeID
                        FROM gibbonServiceDeskTeamMember
                        JOIN gibbonServiceDeskTeam ON (gibbonServiceDeskTeam.gibbonServiceDeskTeamID=gibbonServiceDeskTeamMember.gibbonServiceDeskTeamID AND gibbonServiceDeskTeam.active='Y')
                        JOIN gibbonServiceDeskTicketTypeTeam ON (gibbonServiceDeskTicketTypeTeam.gibbonServiceDeskTeamID=gibbonServiceDeskTeam.gibbonServiceDeskTeamID)
                        WHERE gibbonServiceDeskTeamMember.gibbonPersonID=:scopeMember))")
                ->bindValue('scopeRequester', $gibbonPersonID)
                ->bindValue('scopeFamily', $gibbonPersonID)
                ->bindValue('scopeMember', $gibbonPersonID);
        }

        $criteria->addFilterRules([
            // Every word must be found in at least one of: ticket number, subject, the answers of the request form
            // (details, children, classes, dates), requester, assignee, or a public comment. Internal notes are never
            // searched: a requester must not be able to find out what they say.
            'search' => function ($query, $text) {
                $words = array_slice(preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY), 0, 6);
                foreach ($words as $index => $word) {
                    $like = '%'.addcslashes($word, '%_\\').'%';
                    $number = ctype_digit(ltrim($word, '#')) && ltrim($word, '#') !== '' ? intval(ltrim($word, '#')) : 0;
                    $query->where("(gibbonServiceDeskTicket.subject LIKE :searchA{$index}
                            OR COALESCE(gibbonServiceDeskTicket.searchText, gibbonServiceDeskTicket.fields) LIKE :searchB{$index}
                            OR CONCAT(requester.preferredName, ' ', requester.surname) LIKE :searchC{$index}
                            OR CONCAT(requester.surname, ' ', requester.preferredName) LIKE :searchD{$index}
                            OR CONCAT(assignee.preferredName, ' ', assignee.surname) LIKE :searchE{$index}
                            OR CONCAT(assignee.surname, ' ', assignee.preferredName) LIKE :searchF{$index}
                            OR gibbonServiceDeskTicket.gibbonServiceDeskTicketID+0=:searchNumber{$index}
                            OR EXISTS (SELECT 1 FROM gibbonServiceDeskTicketComment AS searchComment WHERE searchComment.gibbonServiceDeskTicketID=gibbonServiceDeskTicket.gibbonServiceDeskTicketID AND searchComment.type IN ('Comment','Log') AND searchComment.comment LIKE :searchG{$index}))");
                    foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $letter) {
                        $query->bindValue("search{$letter}{$index}", $like);
                    }
                    $query->bindValue("searchNumber{$index}", $number);
                }

                return $query;
            },
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
                    gibbonServiceDeskTicketType.name AS typeName, gibbonServiceDeskTicketType.shareWithFamily,
                    gibbonServiceDeskTicketType.allowWithdraw, gibbonServiceDeskTicketType.allowReopen,
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

    /**
     * Closed tickets that are older than the retention time of their ticket type (a type without retention keeps
     * its tickets). Used by the retention script.
     */
    public function selectExpiredTickets()
    {
        $sql = "SELECT gibbonServiceDeskTicket.gibbonServiceDeskTicketID
                FROM gibbonServiceDeskTicket
                JOIN gibbonServiceDeskTicketType ON (gibbonServiceDeskTicketType.gibbonServiceDeskTicketTypeID=gibbonServiceDeskTicket.gibbonServiceDeskTicketTypeID)
                JOIN gibbonServiceDeskStage ON (gibbonServiceDeskStage.gibbonServiceDeskStageID=gibbonServiceDeskTicket.gibbonServiceDeskStageID)
                WHERE gibbonServiceDeskTicketType.retentionDays>0
                AND gibbonServiceDeskStage.category='Closed'
                AND gibbonServiceDeskTicket.timestampClosed IS NOT NULL
                AND gibbonServiceDeskTicket.timestampClosed < DATE_SUB(NOW(), INTERVAL gibbonServiceDeskTicketType.retentionDays DAY)
                ORDER BY gibbonServiceDeskTicket.gibbonServiceDeskTicketID";

        return $this->db()->select($sql)->fetchAll(\PDO::FETCH_COLUMN);
    }
}
