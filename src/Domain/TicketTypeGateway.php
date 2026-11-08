<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\administration\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

class TicketTypeGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonCsTicketType';
    private static $primaryKey = 'csTicketTypeID';
    private static $searchableColumns = ['name', 'description'];

    public function selectAllWithCounts(): array
    {
        $sql = "SELECT gibbonCsTicketType.*, gibbonCsTeam.name AS teamName, COUNT(gibbonCsField.csFieldID) AS fieldCount,
                    (SELECT COUNT(*) FROM gibbonCsForm WHERE gibbonCsForm.csTicketTypeID=gibbonCsTicketType.csTicketTypeID) AS formCount
                FROM gibbonCsTicketType
                LEFT JOIN gibbonCsTeam ON (gibbonCsTeam.csTeamID=gibbonCsTicketType.csTeamID)
                LEFT JOIN gibbonCsField ON (gibbonCsField.csTicketTypeID=gibbonCsTicketType.csTicketTypeID)
                GROUP BY gibbonCsTicketType.csTicketTypeID ORDER BY gibbonCsTicketType.name";
        return $this->db()->select($sql)->fetchAll();
    }

    public function selectActive(): array
    {
        return $this->db()->select("SELECT * FROM gibbonCsTicketType WHERE active='Y' ORDER BY name")->fetchAll();
    }

    /** @return array csTicketTypeID => name */
    public function selectOptions(): array
    {
        return $this->db()->select("SELECT csTicketTypeID, name FROM gibbonCsTicketType ORDER BY name")->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public function deleteType($csTicketTypeID): bool
    {
        $count = $this->db()->selectOne("SELECT COUNT(*) FROM gibbonCsTicket WHERE csTicketTypeID=:id", ['id' => $csTicketTypeID]);
        if ((int)$count > 0) {
            return false; // Tickets exist: deactivate the type instead.
        }
        $this->db()->delete("DELETE FROM gibbonCsField WHERE csTicketTypeID=:id", ['id' => $csTicketTypeID]);
        return $this->delete($csTicketTypeID);
    }
}
