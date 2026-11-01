<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\CustomerService\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Module\CustomerService\Service\BoardFilter;

class TicketGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonCsTicket';
    private static $primaryKey = 'csTicketID';
    private static $searchableColumns = ['title', 'description'];

    const SELECT = "SELECT gibbonCsTicket.*, gibbonCsTicketType.name AS typeName, gibbonCsTicketType.workflow, gibbonCsTeam.name AS teamName,
                reporter.preferredName AS reporterPreferredName, reporter.surname AS reporterSurname, reporter.username AS reporterUsername,
                assignee.preferredName AS assigneePreferredName, assignee.surname AS assigneeSurname, assignee.username AS assigneeUsername,
                parent.title AS parentTitle, parent.status AS parentStatus
            FROM gibbonCsTicket
            JOIN gibbonCsTicketType ON (gibbonCsTicketType.csTicketTypeID=gibbonCsTicket.csTicketTypeID)
            LEFT JOIN gibbonCsTeam ON (gibbonCsTeam.csTeamID=gibbonCsTicket.csTeamID)
            LEFT JOIN gibbonPerson AS reporter ON (reporter.gibbonPersonID=gibbonCsTicket.gibbonPersonIDReporter)
            LEFT JOIN gibbonPerson AS assignee ON (assignee.gibbonPersonID=gibbonCsTicket.gibbonPersonIDAssignee)
            LEFT JOIN gibbonCsTicket AS parent ON (parent.csTicketID=gibbonCsTicket.csTicketIDParent)";

    public function getTicket($csTicketID): array
    {
        $row = $this->db()->select(self::SELECT." WHERE gibbonCsTicket.csTicketID=:id", ['id' => $csTicketID])->fetch();
        return $row ? $this->decode($row) : [];
    }

    public function selectByReporter($gibbonPersonID, bool $openOnly = false, int $limit = 100): array
    {
        $sql = self::SELECT." WHERE gibbonCsTicket.gibbonPersonIDReporter=:person";
        if ($openOnly) {
            $sql .= " AND gibbonCsTicket.status NOT IN (SELECT name FROM gibbonCsStatus WHERE category='Done')";
        }
        $sql .= " ORDER BY gibbonCsTicket.timestampCreated DESC LIMIT ".(int)$limit;
        return array_map([$this, 'decode'], $this->db()->select($sql, ['person' => $gibbonPersonID])->fetchAll());
    }

    /**
     * Tickets matched by a board filter. Pass $csTicketID to test a single ticket.
     */
    public function selectByFilter($filter, $csTicketID = null, int $limit = 500): array
    {
        [$where, $params] = BoardFilter::toSql($filter);
        $sql = self::SELECT." WHERE ({$where})";
        if ($csTicketID !== null) {
            $sql .= " AND gibbonCsTicket.csTicketID=:onlyTicket";
            $params['onlyTicket'] = $csTicketID;
        }
        $sql .= " ORDER BY FIELD(gibbonCsTicket.priority,'Urgent','High','Medium','Low'), gibbonCsTicket.timestampCreated DESC LIMIT ".(int)$limit;

        return array_map([$this, 'decode'], $this->db()->select($sql, $params)->fetchAll());
    }

    /**
     * Paginated search over the tickets a person may see.
     *
     * @param array|null $scopeBoards  boards whose filters define what the person may see (null = everything, for admins)
     * @param array      $f            search, status, csTicketTypeID, priority, assignee (me|none), mine (Y), open (Y)
     */
    public function querySearch(QueryCriteria $criteria, $gibbonPersonID, ?array $scopeBoards, array $f = [])
    {
        $query = $this
            ->newQuery()
            ->cols([
                'gibbonCsTicket.*', 'gibbonCsTicketType.name AS typeName', 'gibbonCsTeam.name AS teamName',
                'reporter.preferredName AS reporterPreferredName', 'reporter.surname AS reporterSurname',
                'assignee.preferredName AS assigneePreferredName', 'assignee.surname AS assigneeSurname',
            ])
            ->from('gibbonCsTicket')
            ->innerJoin('gibbonCsTicketType', 'gibbonCsTicketType.csTicketTypeID=gibbonCsTicket.csTicketTypeID')
            ->leftJoin('gibbonCsTeam', 'gibbonCsTeam.csTeamID=gibbonCsTicket.csTeamID')
            ->leftJoin('gibbonPerson AS reporter', 'reporter.gibbonPersonID=gibbonCsTicket.gibbonPersonIDReporter')
            ->leftJoin('gibbonPerson AS assignee', 'assignee.gibbonPersonID=gibbonCsTicket.gibbonPersonIDAssignee');

        // What the person may see: own requests + whatever the boards of their spaces show.
        if ($scopeBoards !== null) {
            $parts = ['gibbonCsTicket.gibbonPersonIDReporter = :scopeMe'];
            $params = ['scopeMe' => $gibbonPersonID];
            foreach (array_values($scopeBoards) as $n => $board) {
                [$where, $boardParams] = BoardFilter::toSql($board['filter'], 's'.$n.'_');
                $parts[] = '('.$where.')';
                $params += $boardParams;
            }
            $query->where('('.implode(' OR ', $parts).')')->bindValues($params);
        }

        $q = trim($f['search'] ?? '');
        if ($q !== '') {
            $ref = preg_match('/^(?:CS-?)?(\d+)$/i', $q, $m) ? (int)$m[1] : 0;
            $query->where('(gibbonCsTicket.title LIKE :qTitle OR gibbonCsTicket.description LIKE :qDescription OR gibbonCsTicket.csTicketID = :qRef)')
                ->bindValue('qTitle', '%'.addcslashes($q, '\\%_').'%')
                ->bindValue('qDescription', '%'.addcslashes($q, '\\%_').'%')
                ->bindValue('qRef', $ref);
        }
        if (!empty($f['status'])) {
            $query->where('gibbonCsTicket.status = :fStatus')->bindValue('fStatus', $f['status']);
        }
        if (!empty($f['csTicketTypeID'])) {
            $query->where('gibbonCsTicket.csTicketTypeID = :fType')->bindValue('fType', $f['csTicketTypeID']);
        }
        if (in_array($f['priority'] ?? '', ['Low', 'Medium', 'High', 'Urgent'], true)) {
            $query->where('gibbonCsTicket.priority = :fPriority')->bindValue('fPriority', $f['priority']);
        }
        if (($f['assignee'] ?? '') === 'me') {
            $query->where('gibbonCsTicket.gibbonPersonIDAssignee = :fMe')->bindValue('fMe', $gibbonPersonID);
        } elseif (($f['assignee'] ?? '') === 'none') {
            $query->where('gibbonCsTicket.gibbonPersonIDAssignee IS NULL');
        }
        if (($f['mine'] ?? '') === 'Y') {
            $query->where('gibbonCsTicket.gibbonPersonIDReporter = :fMine')->bindValue('fMine', $gibbonPersonID);
        }
        if (($f['open'] ?? '') === 'Y') {
            $query->where("gibbonCsTicket.status NOT IN (SELECT name FROM gibbonCsStatus WHERE category='Done')");
        }
        if (($f['overdue'] ?? '') === 'Y') {
            $query->where("(gibbonCsTicket.dateDue < CURDATE() AND gibbonCsTicket.status NOT IN (SELECT name FROM gibbonCsStatus WHERE category='Done'))");
        }
        if (!empty($f['tag'])) {
            $query->where('EXISTS (SELECT 1 FROM gibbonCsTicketTag AS ft JOIN gibbonCsTag AS fg ON (fg.csTagID=ft.csTagID) WHERE ft.csTicketID=gibbonCsTicket.csTicketID AND fg.name = :fTag)')
                ->bindValue('fTag', $f['tag']);
        }
        if (($f['watching'] ?? '') === 'Y') {
            $query->where('EXISTS (SELECT 1 FROM gibbonCsTicketWatcher AS fw WHERE fw.csTicketID=gibbonCsTicket.csTicketID AND fw.gibbonPersonID = :fWatcher)')
                ->bindValue('fWatcher', $gibbonPersonID);
        }

        return $this->runQuery($query, $criteria);
    }

    /** Distinct statuses in use, for the search form. */
    public function selectStatuses(): array
    {
        return $this->db()->select("SELECT DISTINCT status FROM gibbonCsTicket ORDER BY status")->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** Resolved tickets untouched for $days days (auto-close). */
    public function selectStaleResolved(int $days): array
    {
        $sql = "SELECT csTicketID FROM gibbonCsTicket WHERE status='Resolved' AND COALESCE(timestampResolved, timestampUpdated, timestampCreated) < (NOW() - INTERVAL ".(int)$days." DAY)";
        return $this->db()->select($sql)->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** Subtasks of a ticket (they are tickets of their own). */
    public function selectSubtasks($csTicketID): array
    {
        $sql = self::SELECT." WHERE gibbonCsTicket.csTicketIDParent=:id ORDER BY gibbonCsTicket.csTicketID";
        return array_map([$this, 'decode'], $this->db()->select($sql, ['id' => $csTicketID])->fetchAll());
    }

    /** @return array csTicketID => [done, total] for the tickets that have subtasks */
    public function selectSubtaskProgress(array $csTicketIDs): array
    {
        $csTicketIDs = array_map('intval', $csTicketIDs);
        if (empty($csTicketIDs)) {
            return [];
        }
        $sql = "SELECT csTicketIDParent, SUM(status IN (SELECT name FROM gibbonCsStatus WHERE category='Done')) AS doneCount, COUNT(*) AS total FROM gibbonCsTicket
                WHERE csTicketIDParent IN (".implode(',', $csTicketIDs).") GROUP BY csTicketIDParent";
        $out = [];
        foreach ($this->db()->select($sql)->fetchAll() as $row) {
            $out[(int)$row['csTicketIDParent']] = [(int)$row['doneCount'], (int)$row['total']];
        }
        return $out;
    }

    public function countOpenSubtasks($csTicketID): int
    {
        return (int)$this->db()->selectOne("SELECT COUNT(*) FROM gibbonCsTicket WHERE csTicketIDParent=:id AND status NOT IN (SELECT name FROM gibbonCsStatus WHERE category='Done')", ['id' => $csTicketID]);
    }

    /** Open tickets due within $days days (including today) that have not had their reminder yet. */
    public function selectDueSoon(int $days): array
    {
        $sql = self::SELECT." WHERE gibbonCsTicket.dateDue IS NOT NULL AND gibbonCsTicket.dateDue >= CURDATE() AND gibbonCsTicket.dateDue <= DATE_ADD(CURDATE(), INTERVAL ".(int)$days." DAY)
                AND gibbonCsTicket.status NOT IN (SELECT name FROM gibbonCsStatus WHERE category='Done') AND gibbonCsTicket.dueReminderSent IS NULL";
        return array_map([$this, 'decode'], $this->db()->select($sql)->fetchAll());
    }

    /** Open tickets past their due date that have not had their overdue notice yet. */
    public function selectOverdue(): array
    {
        $sql = self::SELECT." WHERE gibbonCsTicket.dateDue IS NOT NULL AND gibbonCsTicket.dateDue < CURDATE()
                AND gibbonCsTicket.status NOT IN (SELECT name FROM gibbonCsStatus WHERE category='Done') AND gibbonCsTicket.overdueNoticeSent IS NULL";
        return array_map([$this, 'decode'], $this->db()->select($sql)->fetchAll());
    }

    public function createTicket(array $data): int
    {
        $data['customData'] = json_encode($data['customData'] ?? new \stdClass(), JSON_UNESCAPED_UNICODE);
        return (int)$this->insert($data);
    }

    public function updateTicket($csTicketID, array $data): bool
    {
        if (array_key_exists('customData', $data)) {
            $data['customData'] = json_encode($data['customData'], JSON_UNESCAPED_UNICODE);
        }
        // A new due date starts the reminders over.
        if (array_key_exists('dateDue', $data)) {
            $data['dueReminderSent'] = null;
            $data['overdueNoticeSent'] = null;
        }
        return $this->update($csTicketID, $data);
    }

    public function deleteTicket($csTicketID): bool
    {
        $this->db()->update("UPDATE gibbonCsTicket SET csTicketIDParent=NULL WHERE csTicketIDParent=:id", ['id' => $csTicketID]);
        $this->db()->delete("DELETE FROM gibbonCsTicketTag WHERE csTicketID=:id", ['id' => $csTicketID]);
        $this->db()->delete("DELETE FROM gibbonCsTicketWatcher WHERE csTicketID=:id", ['id' => $csTicketID]);
        $this->db()->delete("DELETE FROM gibbonCsTicketLink WHERE csTicketIDFrom=:id OR csTicketIDTo=:id2", ['id' => $csTicketID, 'id2' => $csTicketID]);
        $this->db()->delete("DELETE gibbonCsChecklistItem FROM gibbonCsChecklistItem JOIN gibbonCsChecklist ON (gibbonCsChecklist.csChecklistID=gibbonCsChecklistItem.csChecklistID) WHERE gibbonCsChecklist.csTicketID=:id", ['id' => $csTicketID]);
        $this->db()->delete("DELETE FROM gibbonCsChecklist WHERE csTicketID=:id", ['id' => $csTicketID]);
        $this->db()->delete("DELETE FROM gibbonCsTicketAttachment WHERE csTicketID=:id", ['id' => $csTicketID]);
        $this->db()->delete("DELETE FROM gibbonCsTicketActivity WHERE csTicketID=:id", ['id' => $csTicketID]);
        return $this->delete($csTicketID);
    }

    private function decode(array $row): array
    {
        $data = json_decode((string)($row['customData'] ?? ''), true);
        $row['customData'] = is_array($data) ? $data : [];
        return $row;
    }
}
