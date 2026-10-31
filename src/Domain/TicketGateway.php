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

    private static $tableName = 'csTicket';
    private static $primaryKey = 'csTicketID';
    private static $searchableColumns = ['title', 'description'];

    const SELECT = "SELECT csTicket.*, csTicketType.name AS typeName, csTicketType.workflow, csTeam.name AS teamName,
                reporter.preferredName AS reporterPreferredName, reporter.surname AS reporterSurname, reporter.username AS reporterUsername,
                assignee.preferredName AS assigneePreferredName, assignee.surname AS assigneeSurname, assignee.username AS assigneeUsername
            FROM csTicket
            JOIN csTicketType ON (csTicketType.csTicketTypeID=csTicket.csTicketTypeID)
            LEFT JOIN csTeam ON (csTeam.csTeamID=csTicket.csTeamID)
            LEFT JOIN gibbonPerson AS reporter ON (reporter.gibbonPersonID=csTicket.gibbonPersonIDReporter)
            LEFT JOIN gibbonPerson AS assignee ON (assignee.gibbonPersonID=csTicket.gibbonPersonIDAssignee)";

    public function getTicket($csTicketID): array
    {
        $row = $this->db()->select(self::SELECT." WHERE csTicket.csTicketID=:id", ['id' => $csTicketID])->fetch();
        return $row ? $this->decode($row) : [];
    }

    public function selectByReporter($gibbonPersonID, bool $openOnly = false, int $limit = 100): array
    {
        $sql = self::SELECT." WHERE csTicket.gibbonPersonIDReporter=:person";
        if ($openOnly) {
            $sql .= " AND csTicket.status NOT IN ('Resolved','Closed')";
        }
        $sql .= " ORDER BY csTicket.timestampCreated DESC LIMIT ".(int)$limit;
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
            $sql .= " AND csTicket.csTicketID=:onlyTicket";
            $params['onlyTicket'] = $csTicketID;
        }
        $sql .= " ORDER BY FIELD(csTicket.priority,'Urgent','High','Medium','Low'), csTicket.timestampCreated DESC LIMIT ".(int)$limit;

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
                'csTicket.*', 'csTicketType.name AS typeName', 'csTeam.name AS teamName',
                'reporter.preferredName AS reporterPreferredName', 'reporter.surname AS reporterSurname',
                'assignee.preferredName AS assigneePreferredName', 'assignee.surname AS assigneeSurname',
            ])
            ->from('csTicket')
            ->innerJoin('csTicketType', 'csTicketType.csTicketTypeID=csTicket.csTicketTypeID')
            ->leftJoin('csTeam', 'csTeam.csTeamID=csTicket.csTeamID')
            ->leftJoin('gibbonPerson AS reporter', 'reporter.gibbonPersonID=csTicket.gibbonPersonIDReporter')
            ->leftJoin('gibbonPerson AS assignee', 'assignee.gibbonPersonID=csTicket.gibbonPersonIDAssignee');

        // What the person may see: own requests + whatever the boards of their spaces show.
        if ($scopeBoards !== null) {
            $parts = ['csTicket.gibbonPersonIDReporter = :scopeMe'];
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
            $query->where('(csTicket.title LIKE :qTitle OR csTicket.description LIKE :qDescription OR csTicket.csTicketID = :qRef)')
                ->bindValue('qTitle', '%'.addcslashes($q, '\\%_').'%')
                ->bindValue('qDescription', '%'.addcslashes($q, '\\%_').'%')
                ->bindValue('qRef', $ref);
        }
        if (!empty($f['status'])) {
            $query->where('csTicket.status = :fStatus')->bindValue('fStatus', $f['status']);
        }
        if (!empty($f['csTicketTypeID'])) {
            $query->where('csTicket.csTicketTypeID = :fType')->bindValue('fType', $f['csTicketTypeID']);
        }
        if (in_array($f['priority'] ?? '', ['Low', 'Medium', 'High', 'Urgent'], true)) {
            $query->where('csTicket.priority = :fPriority')->bindValue('fPriority', $f['priority']);
        }
        if (($f['assignee'] ?? '') === 'me') {
            $query->where('csTicket.gibbonPersonIDAssignee = :fMe')->bindValue('fMe', $gibbonPersonID);
        } elseif (($f['assignee'] ?? '') === 'none') {
            $query->where('csTicket.gibbonPersonIDAssignee IS NULL');
        }
        if (($f['mine'] ?? '') === 'Y') {
            $query->where('csTicket.gibbonPersonIDReporter = :fMine')->bindValue('fMine', $gibbonPersonID);
        }
        if (($f['open'] ?? '') === 'Y') {
            $query->where("csTicket.status NOT IN ('Resolved','Closed')");
        }

        return $this->runQuery($query, $criteria);
    }

    /** Distinct statuses in use, for the search form. */
    public function selectStatuses(): array
    {
        return $this->db()->select("SELECT DISTINCT status FROM csTicket ORDER BY status")->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** Resolved tickets untouched for $days days (auto-close). */
    public function selectStaleResolved(int $days): array
    {
        $sql = "SELECT csTicketID FROM csTicket WHERE status='Resolved' AND COALESCE(timestampResolved, timestampUpdated, timestampCreated) < (NOW() - INTERVAL ".(int)$days." DAY)";
        return $this->db()->select($sql)->fetchAll(\PDO::FETCH_COLUMN);
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
        return $this->update($csTicketID, $data);
    }

    public function deleteTicket($csTicketID): bool
    {
        $this->db()->delete("DELETE FROM csTicketAttachment WHERE csTicketID=:id", ['id' => $csTicketID]);
        $this->db()->delete("DELETE FROM csTicketActivity WHERE csTicketID=:id", ['id' => $csTicketID]);
        return $this->delete($csTicketID);
    }

    private function decode(array $row): array
    {
        $data = json_decode((string)($row['customData'] ?? ''), true);
        $row['customData'] = is_array($data) ? $data : [];
        return $row;
    }
}
