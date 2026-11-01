<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\CustomerService\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

class TeamGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonCsTeam';
    private static $primaryKey = 'csTeamID';
    private static $searchableColumns = ['name', 'description'];

    public function selectAllWithCounts(): array
    {
        $sql = "SELECT gibbonCsTeam.*, COUNT(gibbonCsTeamMember.csTeamMemberID) AS memberCount
                FROM gibbonCsTeam LEFT JOIN gibbonCsTeamMember ON (gibbonCsTeamMember.csTeamID=gibbonCsTeam.csTeamID)
                GROUP BY gibbonCsTeam.csTeamID ORDER BY gibbonCsTeam.name";
        return $this->db()->select($sql)->fetchAll();
    }

    /** @return array csTeamID => name (active teams) */
    public function selectOptions(): array
    {
        return $this->db()->select("SELECT csTeamID, name FROM gibbonCsTeam WHERE active='Y' ORDER BY name")->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public function selectMemberIDs($csTeamID): array
    {
        return $this->db()->select("SELECT gibbonPersonID FROM gibbonCsTeamMember WHERE csTeamID=:csTeamID", ['csTeamID' => $csTeamID])->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** Team members as gibbonPersonID => display name. */
    public function selectMembers($csTeamID): array
    {
        $sql = "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.preferredName, gibbonPerson.surname
                FROM gibbonCsTeamMember JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=gibbonCsTeamMember.gibbonPersonID)
                WHERE gibbonCsTeamMember.csTeamID=:csTeamID AND gibbonPerson.status='Full'
                ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";
        $out = [];
        foreach ($this->db()->select($sql, ['csTeamID' => $csTeamID])->fetchAll() as $p) {
            $out[(int)$p['gibbonPersonID']] = $p['preferredName'].' '.$p['surname'];
        }
        return $out;
    }

    public function replaceMembers($csTeamID, array $personIDs): void
    {
        $this->db()->delete("DELETE FROM gibbonCsTeamMember WHERE csTeamID=:csTeamID", ['csTeamID' => $csTeamID]);
        foreach (array_unique(array_filter($personIDs)) as $id) {
            $this->db()->insert("INSERT IGNORE INTO gibbonCsTeamMember (csTeamID, gibbonPersonID) VALUES (:csTeamID, :id)", ['csTeamID' => $csTeamID, 'id' => $id]);
        }
    }

    public function deleteTeam($csTeamID): bool
    {
        $this->db()->delete("DELETE FROM gibbonCsTeamMember WHERE csTeamID=:csTeamID", ['csTeamID' => $csTeamID]);
        $this->db()->update("UPDATE gibbonCsTicket SET csTeamID=NULL WHERE csTeamID=:csTeamID", ['csTeamID' => $csTeamID]);
        $this->db()->update("UPDATE gibbonCsTicketType SET csTeamID=NULL WHERE csTeamID=:csTeamID", ['csTeamID' => $csTeamID]);
        return $this->delete($csTeamID);
    }
}
