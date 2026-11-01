<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\CustomerService\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

/** Free labels on tickets. Tags are created on the fly and shared by all tickets (names are case-insensitive). */
class TagGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonCsTag';
    private static $primaryKey = 'csTagID';
    private static $searchableColumns = ['name'];

    /** @return array csTagID => name */
    public function selectByTicket($csTicketID): array
    {
        $sql = "SELECT gibbonCsTag.csTagID, gibbonCsTag.name FROM gibbonCsTicketTag JOIN gibbonCsTag ON (gibbonCsTag.csTagID=gibbonCsTicketTag.csTagID)
                WHERE gibbonCsTicketTag.csTicketID=:id ORDER BY gibbonCsTag.name";
        return $this->db()->select($sql, ['id' => $csTicketID])->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    /** @return array csTicketID => [name, ...] */
    public function selectByTickets(array $csTicketIDs): array
    {
        $csTicketIDs = array_map('intval', $csTicketIDs);
        if (empty($csTicketIDs)) {
            return [];
        }
        $sql = "SELECT gibbonCsTicketTag.csTicketID, gibbonCsTag.name FROM gibbonCsTicketTag JOIN gibbonCsTag ON (gibbonCsTag.csTagID=gibbonCsTicketTag.csTagID)
                WHERE gibbonCsTicketTag.csTicketID IN (".implode(',', $csTicketIDs).") ORDER BY gibbonCsTag.name";
        $out = [];
        foreach ($this->db()->select($sql)->fetchAll() as $row) {
            $out[(int)$row['csTicketID']][] = $row['name'];
        }
        return $out;
    }

    /** All tags in use with the number of tickets, for suggestions. */
    public function selectPopular(int $limit = 50): array
    {
        $sql = "SELECT gibbonCsTag.name, COUNT(gibbonCsTicketTag.csTicketTagID) AS uses FROM gibbonCsTag
                LEFT JOIN gibbonCsTicketTag ON (gibbonCsTicketTag.csTagID=gibbonCsTag.csTagID) GROUP BY gibbonCsTag.csTagID ORDER BY uses DESC, gibbonCsTag.name LIMIT ".(int)$limit;
        return $this->db()->select($sql)->fetchAll();
    }

    /** Split "a, b,c" into clean, de-duplicated tag names. */
    public static function parse($text): array
    {
        $names = [];
        foreach (explode(',', (string)$text) as $name) {
            $name = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($name))), 0, 50);
            if ($name !== '' && !isset($names[mb_strtolower($name)])) {
                $names[mb_strtolower($name)] = $name;
            }
        }
        return array_values($names);
    }

    /** @return string[] the names that were newly added to the ticket */
    public function addToTicket($csTicketID, array $names): array
    {
        $added = [];
        $current = array_map('mb_strtolower', $this->selectByTicket($csTicketID));
        foreach ($names as $name) {
            if (in_array(mb_strtolower($name), $current, true)) {
                continue;
            }
            $this->db()->insert("INSERT IGNORE INTO gibbonCsTag (name) VALUES (:name)", ['name' => $name]);
            $csTagID = $this->db()->selectOne("SELECT csTagID FROM gibbonCsTag WHERE name=:name", ['name' => $name]);
            if (!empty($csTagID)) {
                $this->db()->insert("INSERT IGNORE INTO gibbonCsTicketTag (csTicketID, csTagID) VALUES (:ticket, :tag)", ['ticket' => $csTicketID, 'tag' => $csTagID]);
                $added[] = $name;
            }
        }
        return $added;
    }

    /** @return string[] the names removed */
    public function removeFromTicket($csTicketID, array $csTagIDs): array
    {
        $removed = [];
        $current = $this->selectByTicket($csTicketID);
        foreach ($csTagIDs as $csTagID) {
            if (isset($current[(int)$csTagID])) {
                $this->db()->delete("DELETE FROM gibbonCsTicketTag WHERE csTicketID=:ticket AND csTagID=:tag", ['ticket' => $csTicketID, 'tag' => (int)$csTagID]);
                $removed[] = $current[(int)$csTagID];
            }
        }
        return $removed;
    }
}
