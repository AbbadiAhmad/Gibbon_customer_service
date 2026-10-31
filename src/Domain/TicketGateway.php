<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\CustomerService\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;
use Gibbon\Module\CustomerService\Service\BoardFilter;

class TicketGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'csTicket';
    private static $primaryKey = 'csTicketID';
    private static $searchableColumns = ['title', 'description'];

    const SELECT = "SELECT csTicket.*, csTicketType.name AS typeName, csTeam.name AS teamName,
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
