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

    private static $tableName = 'csTeam';
    private static $primaryKey = 'csTeamID';
    private static $searchableColumns = ['name', 'description'];

    public function selectAllWithCounts(): array
    {
        $sql = "SELECT csTeam.*, COUNT(csTeamMember.csTeamMemberID) AS memberCount
                FROM csTeam LEFT JOIN csTeamMember ON (csTeamMember.csTeamID=csTeam.csTeamID)
                GROUP BY csTeam.csTeamID ORDER BY csTeam.name";
        return $this->db()->select($sql)->fetchAll();
    }

    /** @return array csTeamID => name (active teams) */
    public function selectOptions(): array
    {
        return $this->db()->select("SELECT csTeamID, name FROM csTeam WHERE active='Y' ORDER BY name")->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public function selectMemberIDs($csTeamID): array
    {
        return $this->db()->select("SELECT gibbonPersonID FROM csTeamMember WHERE csTeamID=:csTeamID", ['csTeamID' => $csTeamID])->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** Team members as gibbonPersonID => display name. */
    public function selectMembers($csTeamID): array
    {
        $sql = "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.preferredName, gibbonPerson.surname
                FROM csTeamMember JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=csTeamMember.gibbonPersonID)
                WHERE csTeamMember.csTeamID=:csTeamID AND gibbonPerson.status='Full'
                ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";
        $out = [];
        foreach ($this->db()->select($sql, ['csTeamID' => $csTeamID])->fetchAll() as $p) {
            $out[(int)$p['gibbonPersonID']] = $p['preferredName'].' '.$p['surname'];
        }
        return $out;
    }

    public function replaceMembers($csTeamID, array $personIDs): void
    {
        $this->db()->delete("DELETE FROM csTeamMember WHERE csTeamID=:csTeamID", ['csTeamID' => $csTeamID]);
        foreach (array_unique(array_filter($personIDs)) as $id) {
            $this->db()->insert("INSERT IGNORE INTO csTeamMember (csTeamID, gibbonPersonID) VALUES (:csTeamID, :id)", ['csTeamID' => $csTeamID, 'id' => $id]);
        }
    }

    public function deleteTeam($csTeamID): bool
    {
        $this->db()->delete("DELETE FROM csTeamMember WHERE csTeamID=:csTeamID", ['csTeamID' => $csTeamID]);
        $this->db()->update("UPDATE csTicket SET csTeamID=NULL WHERE csTeamID=:csTeamID", ['csTeamID' => $csTeamID]);
        $this->db()->update("UPDATE csTicketType SET csTeamID=NULL WHERE csTeamID=:csTeamID", ['csTeamID' => $csTeamID]);
        return $this->delete($csTeamID);
    }
}
