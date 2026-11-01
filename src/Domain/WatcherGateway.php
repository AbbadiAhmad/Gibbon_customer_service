<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\CustomerService\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

/** People following a ticket: they are notified about its public activity without owning it. */
class WatcherGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonCsTicketWatcher';
    private static $primaryKey = 'csTicketWatcherID';
    private static $searchableColumns = [];

    /** @return int[] */
    public function selectPersonIDs($csTicketID): array
    {
        return array_map('intval', $this->db()->select("SELECT gibbonPersonID FROM gibbonCsTicketWatcher WHERE csTicketID=:id", ['id' => $csTicketID])->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** @return array gibbonPersonID => name */
    public function selectWithNames($csTicketID): array
    {
        $sql = "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.preferredName, gibbonPerson.surname
                FROM gibbonCsTicketWatcher JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=gibbonCsTicketWatcher.gibbonPersonID)
                WHERE gibbonCsTicketWatcher.csTicketID=:id ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";
        $out = [];
        foreach ($this->db()->select($sql, ['id' => $csTicketID])->fetchAll() as $p) {
            $out[(int)$p['gibbonPersonID']] = $p['preferredName'].' '.$p['surname'];
        }
        return $out;
    }

    public function isWatching($csTicketID, $gibbonPersonID): bool
    {
        return in_array((int)$gibbonPersonID, $this->selectPersonIDs($csTicketID), true);
    }

    public function add($csTicketID, $gibbonPersonID): void
    {
        $this->db()->insert("INSERT IGNORE INTO gibbonCsTicketWatcher (csTicketID, gibbonPersonID) VALUES (:ticket, :person)", ['ticket' => $csTicketID, 'person' => $gibbonPersonID]);
    }

    public function remove($csTicketID, $gibbonPersonID): void
    {
        $this->db()->delete("DELETE FROM gibbonCsTicketWatcher WHERE csTicketID=:ticket AND gibbonPersonID=:person", ['ticket' => $csTicketID, 'person' => $gibbonPersonID]);
    }
}
