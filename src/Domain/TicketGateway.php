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
use Gibbon\Domain\DataSet;
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
     * The columns a ticket list can be sorted by, and the largest page it returns.
     */
    public const SORTABLE = [
        'unread', 'lastUpdate', 'gibbonServiceDeskTicket.gibbonServiceDeskTicketID', 'subject', 'priority', 'stageName',
        'requester.surname', 'requester.preferredName', 'assignee.surname', 'assignee.preferredName',
    ];
    public const MAX_PAGE_SIZE = 500;

    /**
     * A page of tickets for a person, with the filters of TicketFilters: search, type (ids), status (stage categories),
     * stage (name), assignee (me / none / person id), requester (me), priority, team (id), closedSince (date).
     *
     * Built for many tickets (see docs/AUDIT.md): the page is found in two steps. Step 1 reads only the ticket table and
     * the person's "seen" rows to find which tickets are on the page and how many match; step 2 loads names and labels
     * for those few tickets. Everything that can be looked up beforehand in a small table (stages, teams, family, people
     * matching a search word) is turned into a list of ids, so the ticket table is reached through its indexes.
     *
     * Columns added to each row:
     *  - `unread` (1/0): has the ticket changed since this person last opened it? A ticket never opened counts while it
     *    is not closed.
     *  - `lastUpdate`: the time of the last change this person can see. People who work on the ticket (administrator,
     *    or its type is in $managedTypeIDs) count every change; everybody else only public ones, so an internal note
     *    neither alerts a requester nor shows up as a new "last update".
     */
    public function queryTickets(QueryCriteria $criteria, $gibbonPersonID, bool $isAdmin, array $managedTypeIDs = [])
    {
        $me = intval($gibbonPersonID);
        $managed = self::ids($managedTypeIDs);

        // The browser can post its own sorting and page size (Gibbon's tables do): accept only the columns a ticket
        // list sorts by, and a sane page size. Otherwise a person could order the list by any column of a joined
        // table (and read something from the order), or ask for every ticket in one page.
        $sorting = array_intersect_key($criteria->getSortBy(), array_flip(self::SORTABLE));
        $criteria->fromArray(['sortBy' => $sorting]);
        if ($criteria->getPageSize() < 1 || $criteria->getPageSize() > self::MAX_PAGE_SIZE) {
            $criteria->pageSize(self::MAX_PAGE_SIZE);
        }

        // Small tables, read once: the stages (to turn "status" and "stage" into stage ids).
        $stages = $this->db()->select("SELECT gibbonServiceDeskStageID, name, category FROM gibbonServiceDeskStage")->fetchAll();
        $stageIDs = function (callable $test) use ($stages) {
            return self::ids(array_column(array_filter($stages, $test), 'gibbonServiceDeskStageID'));
        };
        $closed = $stageIDs(function ($stage) {
            return $stage['category'] == 'Closed';
        });

        $manages = $isAdmin ? '1=1' : (empty($managed) ? '1=0' : 'gibbonServiceDeskTicket.gibbonServiceDeskTicketTypeID IN ('.implode(',', $managed).')');
        $lastUpdate = "(CASE WHEN {$manages} THEN gibbonServiceDeskTicket.timestampModified ELSE COALESCE(gibbonServiceDeskTicket.timestampModifiedPublic, gibbonServiceDeskTicket.timestampModified) END)";
        $notClosed = empty($closed) ? '1' : '(gibbonServiceDeskTicket.gibbonServiceDeskStageID NOT IN ('.implode(',', $closed).'))';

        // STEP 1: which tickets are on the page
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonServiceDeskTicket.gibbonServiceDeskTicketID',
                "(CASE WHEN seen.timestampSeen IS NULL THEN {$notClosed} WHEN {$lastUpdate} > seen.timestampSeen THEN 1 ELSE 0 END) AS unread",
                "{$lastUpdate} AS lastUpdate",
            ])
            ->leftJoin('gibbonServiceDeskTicketSeen AS seen', 'seen.gibbonServiceDeskTicketID=gibbonServiceDeskTicket.gibbonServiceDeskTicketID AND seen.gibbonPersonID=:seenPerson')
            ->bindValue('seenPerson', $me);

        // Names are only joined when the list is sorted by them.
        if ($criteria->hasSort('requester.surname') || $criteria->hasSort('requester.preferredName')) {
            $query->leftJoin('gibbonPerson AS requester', 'requester.gibbonPersonID=gibbonServiceDeskTicket.gibbonPersonIDRequester');
        }
        if ($criteria->hasSort('assignee.surname') || $criteria->hasSort('assignee.preferredName')) {
            $query->leftJoin('gibbonPerson AS assignee', 'assignee.gibbonPersonID=gibbonServiceDeskTicket.gibbonPersonIDAssignee');
        }
        if ($criteria->hasSort('stageName')) {
            $query->cols(['gibbonServiceDeskStage.name AS stageName'])
                ->innerJoin('gibbonServiceDeskStage', 'gibbonServiceDeskStage.gibbonServiceDeskStageID=gibbonServiceDeskTicket.gibbonServiceDeskStageID');
        }

        // SCOPE: own requests; requests of the other adults of the family for ticket types shared with the family; and
        // the tickets of the ticket types the person's teams handle. (The same rule for one ticket: TicketAccess.)
        if (!$isAdmin) {
            $coAdults = self::ids($this->db()->select(
                "SELECT requesterAdult.gibbonPersonID
                 FROM gibbonFamilyAdult AS viewerAdult
                 JOIN gibbonFamilyAdult AS requesterAdult ON (requesterAdult.gibbonFamilyID=viewerAdult.gibbonFamilyID AND requesterAdult.gibbonPersonID<>viewerAdult.gibbonPersonID)
                 WHERE viewerAdult.gibbonPersonID=:gibbonPersonID AND viewerAdult.childDataAccess='Y'",
                ['gibbonPersonID' => $me]
            )->fetchAll(\PDO::FETCH_COLUMN));
            $shared = empty($coAdults) ? [] : self::ids($this->db()->select("SELECT gibbonServiceDeskTicketTypeID FROM gibbonServiceDeskTicketType WHERE shareWithFamily='Y'")->fetchAll(\PDO::FETCH_COLUMN));

            $family = !empty($coAdults) && !empty($shared)
                ? ' OR (gibbonServiceDeskTicket.gibbonPersonIDRequester IN ('.implode(',', $coAdults).') AND gibbonServiceDeskTicket.gibbonServiceDeskTicketTypeID IN ('.implode(',', $shared).'))'
                : '';

            if (empty($managed)) {
                // A requester: everything hangs on the requester, so the requester index does the work.
                $query->where('gibbonServiceDeskTicket.gibbonPersonIDRequester IN ('.implode(',', array_merge([$me], !empty($family) ? $coAdults : [])).')')
                    ->where("(gibbonServiceDeskTicket.gibbonPersonIDRequester={$me}{$family})");
            } else {
                $query->where("(gibbonServiceDeskTicket.gibbonPersonIDRequester={$me}{$family} OR gibbonServiceDeskTicket.gibbonServiceDeskTicketTypeID IN (".implode(',', $managed).'))');
            }
        }

        $db = $this->db();
        $criteria->addFilterRules([
            // Every word must be found in at least one of: ticket number, subject, the search text of the ticket (the
            // answers of the request form and its public comments), requester name, assignee name. Internal notes are
            // not part of the search text: a requester must not be able to find out what they say.
            'search' => function ($query, $text) use ($db) {
                $words = array_slice(preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY), 0, 6);
                foreach ($words as $index => $word) {
                    $like = '%'.addcslashes($word, '%_\\').'%';
                    $conditions = ["gibbonServiceDeskTicket.subject LIKE :searchA{$index}", "gibbonServiceDeskTicket.searchText LIKE :searchB{$index}"];
                    $query->bindValue("searchA{$index}", $like)->bindValue("searchB{$index}", $like);

                    if (ctype_digit(ltrim($word, '#')) && ltrim($word, '#') !== '') {
                        $conditions[] = 'gibbonServiceDeskTicket.gibbonServiceDeskTicketID='.intval(ltrim($word, '#'));
                    }

                    // People whose name contains the word: looked up in the (small) person table, then matched by id.
                    $people = self::ids($db->select(
                        "SELECT gibbonPersonID FROM gibbonPerson WHERE CONCAT(preferredName, ' ', surname) LIKE :name1 OR CONCAT(surname, ' ', preferredName) LIKE :name2 LIMIT 1000",
                        ['name1' => $like, 'name2' => $like]
                    )->fetchAll(\PDO::FETCH_COLUMN));
                    if (!empty($people)) {
                        $conditions[] = 'gibbonServiceDeskTicket.gibbonPersonIDRequester IN ('.implode(',', $people).')';
                        $conditions[] = 'gibbonServiceDeskTicket.gibbonPersonIDAssignee IN ('.implode(',', $people).')';
                    }

                    $query->where('('.implode(' OR ', $conditions).')');
                }

                return $query;
            },
            'type' => function ($query, $ids) {
                $ids = self::ids(explode(',', $ids));
                return $query->where(empty($ids) ? '1=0' : 'gibbonServiceDeskTicket.gibbonServiceDeskTicketTypeID IN ('.implode(',', $ids).')');
            },
            'status' => function ($query, $categories) use ($stageIDs) {
                $categories = explode(',', $categories);
                $ids = $stageIDs(function ($stage) use ($categories) {
                    return in_array($stage['category'], $categories, true);
                });
                return $query->where(empty($ids) ? '1=0' : 'gibbonServiceDeskTicket.gibbonServiceDeskStageID IN ('.implode(',', $ids).')');
            },
            'stage' => function ($query, $name) use ($stageIDs) {
                $ids = $stageIDs(function ($stage) use ($name) {
                    return $stage['name'] === $name;
                });
                return $query->where(empty($ids) ? '1=0' : 'gibbonServiceDeskTicket.gibbonServiceDeskStageID IN ('.implode(',', $ids).')');
            },
            'assignee' => function ($query, $assignee) use ($me) {
                if ($assignee == 'none') {
                    return $query->where('gibbonServiceDeskTicket.gibbonPersonIDAssignee IS NULL');
                }
                return $query->where('gibbonServiceDeskTicket.gibbonPersonIDAssignee='.($assignee == 'me' ? $me : intval($assignee)));
            },
            'requester' => function ($query, $requester) use ($me) {
                return $query->where('gibbonServiceDeskTicket.gibbonPersonIDRequester='.($requester == 'me' ? $me : intval($requester)));
            },
            'priority' => function ($query, $priority) {
                return $query
                    ->where('gibbonServiceDeskTicket.priority=:filterPriority')
                    ->bindValue('filterPriority', $priority);
            },
            // Board view: closed tickets only while they are recent (date = oldest closing day still shown).
            'closedSince' => function ($query, $date) use ($notClosed) {
                return $query
                    ->where("({$notClosed} OR gibbonServiceDeskTicket.timestampClosed>=:filterClosedSince)")
                    ->bindValue('filterClosedSince', $date);
            },
            'team' => function ($query, $gibbonServiceDeskTeamID) use ($db) {
                $ids = self::ids($db->select("SELECT gibbonServiceDeskTicketTypeID FROM gibbonServiceDeskTicketTypeTeam WHERE gibbonServiceDeskTeamID=:gibbonServiceDeskTeamID", ['gibbonServiceDeskTeamID' => $gibbonServiceDeskTeamID])->fetchAll(\PDO::FETCH_COLUMN));
                return $query->where(empty($ids) ? '1=0' : 'gibbonServiceDeskTicket.gibbonServiceDeskTicketTypeID IN ('.implode(',', $ids).')');
            },
        ]);

        // The order when the person has not chosen one: tickets with news first, then the latest update. It is applied
        // to a copy, so the table on the page does not think the person sorted by these columns (clicking a heading
        // would then only flip one of them). The ticket number always breaks ties.
        $run = clone $criteria;
        if (empty($sorting)) {
            $run->sortBy('unread', 'DESC')->sortBy('lastUpdate', 'DESC');
        }
        if (!isset($sorting['gibbonServiceDeskTicket.gibbonServiceDeskTicketID'])) {
            $run->sortBy('gibbonServiceDeskTicket.gibbonServiceDeskTicketID', 'DESC');
        }

        $page = $this->runQuery($query, $run);
        $found = array_column($page->toArray(), null, 'gibbonServiceDeskTicketID');

        // STEP 2: names and labels for the tickets of the page, in the order of step 1. The JSON answers and the search
        // text are not loaded: lists do not show them.
        $rows = [];
        if (!empty($found)) {
            $sql = "SELECT gibbonServiceDeskTicket.gibbonServiceDeskTicketID, gibbonServiceDeskTicket.gibbonSchoolYearID,
                        gibbonServiceDeskTicket.gibbonServiceDeskTicketTypeID, gibbonServiceDeskTicket.gibbonServiceDeskStageID,
                        gibbonServiceDeskTicket.gibbonPersonIDRequester, gibbonServiceDeskTicket.gibbonPersonIDAssignee,
                        gibbonServiceDeskTicket.subject, gibbonServiceDeskTicket.priority,
                        gibbonServiceDeskTicket.timestampCreated, gibbonServiceDeskTicket.timestampClosed,
                        gibbonServiceDeskTicketType.name AS typeName, gibbonServiceDeskTicketType.color AS typeColor, gibbonServiceDeskTicketType.shareWithFamily,
                        gibbonServiceDeskStage.name AS stageName, gibbonServiceDeskStage.category AS stageCategory,
                        requester.title AS requesterTitle, requester.surname AS requesterSurname, requester.preferredName AS requesterPreferredName,
                        assignee.title AS assigneeTitle, assignee.surname AS assigneeSurname, assignee.preferredName AS assigneePreferredName
                    FROM gibbonServiceDeskTicket
                    JOIN gibbonServiceDeskTicketType ON (gibbonServiceDeskTicketType.gibbonServiceDeskTicketTypeID=gibbonServiceDeskTicket.gibbonServiceDeskTicketTypeID)
                    JOIN gibbonServiceDeskStage ON (gibbonServiceDeskStage.gibbonServiceDeskStageID=gibbonServiceDeskTicket.gibbonServiceDeskStageID)
                    LEFT JOIN gibbonPerson AS requester ON (requester.gibbonPersonID=gibbonServiceDeskTicket.gibbonPersonIDRequester)
                    LEFT JOIN gibbonPerson AS assignee ON (assignee.gibbonPersonID=gibbonServiceDeskTicket.gibbonPersonIDAssignee)
                    WHERE gibbonServiceDeskTicket.gibbonServiceDeskTicketID IN (".implode(',', self::ids(array_keys($found))).")";

            $details = array_column($this->db()->select($sql)->fetchAll(), null, 'gibbonServiceDeskTicketID');
            foreach ($found as $id => $row) {
                if (isset($details[$id])) {
                    $rows[] = $details[$id] + ['unread' => $row['unread'], 'lastUpdate' => $row['lastUpdate']];
                }
            }
        }

        return (new DataSet($rows))
            ->setResultCount($page->getResultCount(), $page->getTotalCount())
            ->setPagination($criteria->getPage(), $criteria->getPageSize());
    }

    /**
     * Ids as plain positive integers, safe to write into an IN (...) list.
     */
    protected static function ids(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0;
        })));
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
     * its tickets), oldest first, at most $limit. Used by the retention script, batch by batch.
     */
    public function selectExpiredTickets(int $limit = 500): array
    {
        $sql = "SELECT gibbonServiceDeskTicket.gibbonServiceDeskTicketID, gibbonServiceDeskTicket.gibbonSchoolYearID, gibbonServiceDeskTicketType.name AS typeName
                FROM gibbonServiceDeskTicket
                JOIN gibbonServiceDeskTicketType ON (gibbonServiceDeskTicketType.gibbonServiceDeskTicketTypeID=gibbonServiceDeskTicket.gibbonServiceDeskTicketTypeID)
                JOIN gibbonServiceDeskStage ON (gibbonServiceDeskStage.gibbonServiceDeskStageID=gibbonServiceDeskTicket.gibbonServiceDeskStageID)
                WHERE gibbonServiceDeskTicketType.retentionDays>0
                AND gibbonServiceDeskStage.category='Closed'
                AND gibbonServiceDeskTicket.timestampClosed IS NOT NULL
                AND gibbonServiceDeskTicket.timestampClosed < DATE_SUB(NOW(), INTERVAL gibbonServiceDeskTicketType.retentionDays DAY)
                ORDER BY gibbonServiceDeskTicket.gibbonServiceDeskTicketID
                LIMIT ".max(1, $limit);

        return $this->db()->select($sql)->fetchAll();
    }

    public function countExpiredTickets(): int
    {
        $sql = "SELECT COUNT(*)
                FROM gibbonServiceDeskTicket
                JOIN gibbonServiceDeskTicketType ON (gibbonServiceDeskTicketType.gibbonServiceDeskTicketTypeID=gibbonServiceDeskTicket.gibbonServiceDeskTicketTypeID)
                JOIN gibbonServiceDeskStage ON (gibbonServiceDeskStage.gibbonServiceDeskStageID=gibbonServiceDeskTicket.gibbonServiceDeskStageID)
                WHERE gibbonServiceDeskTicketType.retentionDays>0
                AND gibbonServiceDeskStage.category='Closed'
                AND gibbonServiceDeskTicket.timestampClosed IS NOT NULL
                AND gibbonServiceDeskTicket.timestampClosed < DATE_SUB(NOW(), INTERVAL gibbonServiceDeskTicketType.retentionDays DAY)";

        return (int) $this->db()->selectOne($sql);
    }

    /**
     * Rows that point to something that no longer exists: left-overs that deleting a ticket or a Gibbon user by other
     * means (a database tool, another module) can leave behind. Each entry: [what, SELECT COUNT, DELETE or UPDATE].
     * Used by the housekeeping part of the retention script; see docs/AUDIT.md.
     */
    public static function orphanChecks(): array
    {
        $noTicket = "NOT EXISTS (SELECT 1 FROM gibbonServiceDeskTicket WHERE gibbonServiceDeskTicket.gibbonServiceDeskTicketID=x.gibbonServiceDeskTicketID)";
        $noPerson = "NOT EXISTS (SELECT 1 FROM gibbonPerson WHERE gibbonPerson.gibbonPersonID=x.gibbonPersonID)";

        $checks = [];
        foreach (['gibbonServiceDeskTicketComment' => 'history lines', 'gibbonServiceDeskTicketSubscriber' => 'follower rows', 'gibbonServiceDeskTicketSeen' => '"seen" marks'] as $table => $label) {
            $checks[] = [$label.' of tickets that no longer exist', "SELECT COUNT(*) FROM {$table} x WHERE {$noTicket}", "DELETE x FROM {$table} x WHERE {$noTicket}"];
        }
        foreach (['gibbonServiceDeskTicketSubscriber' => 'follower rows', 'gibbonServiceDeskTicketSeen' => '"seen" marks', 'gibbonServiceDeskNotificationPreference' => 'notification settings', 'gibbonServiceDeskViewPreference' => 'view settings', 'gibbonServiceDeskTeamMember' => 'team memberships', 'gibbonServiceDeskTypeNotification' => 'ticket type notification settings'] as $table => $label) {
            $checks[] = [$label.' of people who no longer exist', "SELECT COUNT(*) FROM {$table} x WHERE {$noPerson}", "DELETE x FROM {$table} x WHERE {$noPerson}"];
        }
        $checks[] = [
            'ticket type notification settings of ticket types that no longer exist',
            "SELECT COUNT(*) FROM gibbonServiceDeskTypeNotification x WHERE NOT EXISTS (SELECT 1 FROM gibbonServiceDeskTicketType t WHERE t.gibbonServiceDeskTicketTypeID=x.gibbonServiceDeskTicketTypeID)",
            "DELETE x FROM gibbonServiceDeskTypeNotification x WHERE NOT EXISTS (SELECT 1 FROM gibbonServiceDeskTicketType t WHERE t.gibbonServiceDeskTicketTypeID=x.gibbonServiceDeskTicketTypeID)",
        ];
        $checks[] = ['personal filters of people who no longer exist', "SELECT COUNT(*) FROM gibbonServiceDeskFilter x WHERE x.gibbonPersonID IS NOT NULL AND {$noPerson}", "DELETE x FROM gibbonServiceDeskFilter x WHERE x.gibbonPersonID IS NOT NULL AND {$noPerson}"];
        $checks[] = [
            'default assignees who are no longer members of their team',
            "SELECT COUNT(*) FROM gibbonServiceDeskTeam x WHERE x.gibbonPersonIDDefaultAssignee IS NOT NULL AND NOT EXISTS (SELECT 1 FROM gibbonServiceDeskTeamMember m WHERE m.gibbonServiceDeskTeamID=x.gibbonServiceDeskTeamID AND m.gibbonPersonID=x.gibbonPersonIDDefaultAssignee)",
            "UPDATE gibbonServiceDeskTeam x SET x.gibbonPersonIDDefaultAssignee=NULL WHERE x.gibbonPersonIDDefaultAssignee IS NOT NULL AND NOT EXISTS (SELECT 1 FROM gibbonServiceDeskTeamMember m WHERE m.gibbonServiceDeskTeamID=x.gibbonServiceDeskTeamID AND m.gibbonPersonID=x.gibbonPersonIDDefaultAssignee)",
        ];
        $checks[] = [
            'assignees who no longer exist (tickets become unassigned)',
            "SELECT COUNT(*) FROM gibbonServiceDeskTicket x WHERE x.gibbonPersonIDAssignee IS NOT NULL AND NOT EXISTS (SELECT 1 FROM gibbonPerson WHERE gibbonPerson.gibbonPersonID=x.gibbonPersonIDAssignee)",
            "UPDATE gibbonServiceDeskTicket x SET x.gibbonPersonIDAssignee=NULL WHERE x.gibbonPersonIDAssignee IS NOT NULL AND NOT EXISTS (SELECT 1 FROM gibbonPerson WHERE gibbonPerson.gibbonPersonID=x.gibbonPersonIDAssignee)",
        ];

        return $checks;
    }

    /**
     * Counts (and, unless $dryRun, removes) the left-overs of orphanChecks(). Returns [what => count].
     */
    public function cleanOrphans(bool $dryRun): array
    {
        $found = [];
        foreach (self::orphanChecks() as $check) {
            $count = (int) $this->db()->selectOne($check[1]);
            if ($count > 0) {
                $found[$check[0]] = $count;
                if (!$dryRun) {
                    $this->db()->statement($check[2]);
                }
            }
        }

        return $found;
    }
}
