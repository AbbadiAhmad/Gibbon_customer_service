<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\administration\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

class SpaceGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonCsSpace';
    private static $primaryKey = 'csSpaceID';
    private static $searchableColumns = ['name', 'description'];

    public function selectAllWithCounts(): array
    {
        $sql = "SELECT gibbonCsSpace.*,
                    (SELECT COUNT(DISTINCT gibbonPersonID) FROM gibbonCsSpaceMember WHERE gibbonCsSpaceMember.csSpaceID=gibbonCsSpace.csSpaceID) AS memberCount,
                    (SELECT COUNT(*) FROM gibbonCsBoard WHERE gibbonCsBoard.csSpaceID=gibbonCsSpace.csSpaceID) AS boardCount
                FROM gibbonCsSpace ORDER BY gibbonCsSpace.name";
        return $this->db()->select($sql)->fetchAll();
    }

    /** Spaces in which the person holds a role with the given permission. */
    public function selectSpaceIDsWithPermission($gibbonPersonID, string $permission): array
    {
        $sql = "SELECT DISTINCT gibbonCsSpaceMember.csSpaceID
                FROM gibbonCsSpaceMember JOIN gibbonCsRolePermission ON (gibbonCsRolePermission.csRoleID=gibbonCsSpaceMember.csRoleID)
                WHERE gibbonCsSpaceMember.gibbonPersonID=:person AND gibbonCsRolePermission.permission=:permission";
        return array_map('intval', $this->db()->select($sql, ['person' => $gibbonPersonID, 'permission' => $permission])->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function hasPermission($gibbonPersonID, $csSpaceID, string $permission): bool
    {
        $sql = "SELECT COUNT(*) FROM gibbonCsSpaceMember JOIN gibbonCsRolePermission ON (gibbonCsRolePermission.csRoleID=gibbonCsSpaceMember.csRoleID)
                WHERE gibbonCsSpaceMember.gibbonPersonID=:person AND gibbonCsSpaceMember.csSpaceID=:space AND gibbonCsRolePermission.permission=:permission";
        return (int)$this->db()->selectOne($sql, ['person' => $gibbonPersonID, 'space' => $csSpaceID, 'permission' => $permission]) > 0;
    }

    public function selectMembers($csSpaceID): array
    {
        $sql = "SELECT gibbonCsSpaceMember.csSpaceMemberID, gibbonCsSpaceMember.gibbonPersonID, gibbonCsSpaceMember.csRoleID,
                    gibbonPerson.preferredName, gibbonPerson.surname, gibbonPerson.username, gibbonCsRole.name AS roleName
                FROM gibbonCsSpaceMember
                JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=gibbonCsSpaceMember.gibbonPersonID)
                JOIN gibbonCsRole ON (gibbonCsRole.csRoleID=gibbonCsSpaceMember.csRoleID)
                WHERE gibbonCsSpaceMember.csSpaceID=:space ORDER BY gibbonPerson.surname, gibbonPerson.preferredName, gibbonCsRole.name";
        return $this->db()->select($sql, ['space' => $csSpaceID])->fetchAll();
    }

    public function addMember($csSpaceID, $gibbonPersonID, $csRoleID): bool
    {
        $sql = "INSERT IGNORE INTO gibbonCsSpaceMember (csSpaceID, gibbonPersonID, csRoleID) VALUES (:space, :person, :role)";
        $this->db()->insert($sql, ['space' => $csSpaceID, 'person' => $gibbonPersonID, 'role' => $csRoleID]);
        return true;
    }

    public function getMember($csSpaceMemberID): array
    {
        return $this->db()->select("SELECT * FROM gibbonCsSpaceMember WHERE csSpaceMemberID=:id", ['id' => $csSpaceMemberID])->fetch() ?: [];
    }

    public function removeMember($csSpaceMemberID): bool
    {
        $this->db()->delete("DELETE FROM gibbonCsSpaceMember WHERE csSpaceMemberID=:id", ['id' => $csSpaceMemberID]);
        return true;
    }

    /** Distinct people in the given spaces as gibbonPersonID => name. */
    public function selectPeopleInSpaces(array $spaceIDs): array
    {
        $spaceIDs = array_map('intval', $spaceIDs);
        if (empty($spaceIDs)) {
            return [];
        }
        $sql = "SELECT DISTINCT gibbonPerson.gibbonPersonID, gibbonPerson.preferredName, gibbonPerson.surname
                FROM gibbonCsSpaceMember JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=gibbonCsSpaceMember.gibbonPersonID)
                WHERE gibbonCsSpaceMember.csSpaceID IN (".implode(',', $spaceIDs).") AND gibbonPerson.status='Full'
                ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";
        $out = [];
        foreach ($this->db()->select($sql)->fetchAll() as $p) {
            $out[(int)$p['gibbonPersonID']] = $p['preferredName'].' '.$p['surname'];
        }
        return $out;
    }

    /** Active users holding $permission in any of the spaces: gibbonPersonID list. */
    public function selectPeopleWithPermission(array $spaceIDs, string $permission): array
    {
        $spaceIDs = array_map('intval', $spaceIDs);
        if (empty($spaceIDs)) {
            return [];
        }
        $sql = "SELECT DISTINCT gibbonCsSpaceMember.gibbonPersonID
                FROM gibbonCsSpaceMember
                JOIN gibbonCsRolePermission ON (gibbonCsRolePermission.csRoleID=gibbonCsSpaceMember.csRoleID AND gibbonCsRolePermission.permission=:permission)
                JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=gibbonCsSpaceMember.gibbonPersonID AND gibbonPerson.status='Full')
                WHERE gibbonCsSpaceMember.csSpaceID IN (".implode(',', $spaceIDs).")";
        return array_map('intval', $this->db()->select($sql, ['permission' => $permission])->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function deleteSpace($csSpaceID): bool
    {
        $boards = $this->db()->select("SELECT csBoardID FROM gibbonCsBoard WHERE csSpaceID=:id", ['id' => $csSpaceID])->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($boards as $csBoardID) {
            $this->db()->delete("DELETE FROM gibbonCsBoardColumn WHERE csBoardID=:id", ['id' => $csBoardID]);
        }
        $this->db()->delete("DELETE FROM gibbonCsBoard WHERE csSpaceID=:id", ['id' => $csSpaceID]);
        $this->db()->delete("DELETE FROM gibbonCsSpaceMember WHERE csSpaceID=:id", ['id' => $csSpaceID]);
        return $this->delete($csSpaceID);
    }
}
