<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\CustomerService\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

class SpaceGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'csSpace';
    private static $primaryKey = 'csSpaceID';
    private static $searchableColumns = ['name', 'description'];

    public function selectAllWithCounts(): array
    {
        $sql = "SELECT csSpace.*,
                    (SELECT COUNT(DISTINCT gibbonPersonID) FROM csSpaceMember WHERE csSpaceMember.csSpaceID=csSpace.csSpaceID) AS memberCount,
                    (SELECT COUNT(*) FROM csBoard WHERE csBoard.csSpaceID=csSpace.csSpaceID) AS boardCount
                FROM csSpace ORDER BY csSpace.name";
        return $this->db()->select($sql)->fetchAll();
    }

    /** Spaces in which the person holds a role with the given permission. */
    public function selectSpaceIDsWithPermission($gibbonPersonID, string $permission): array
    {
        $sql = "SELECT DISTINCT csSpaceMember.csSpaceID
                FROM csSpaceMember JOIN csRolePermission ON (csRolePermission.csRoleID=csSpaceMember.csRoleID)
                WHERE csSpaceMember.gibbonPersonID=:person AND csRolePermission.permission=:permission";
        return array_map('intval', $this->db()->select($sql, ['person' => $gibbonPersonID, 'permission' => $permission])->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function hasPermission($gibbonPersonID, $csSpaceID, string $permission): bool
    {
        $sql = "SELECT COUNT(*) FROM csSpaceMember JOIN csRolePermission ON (csRolePermission.csRoleID=csSpaceMember.csRoleID)
                WHERE csSpaceMember.gibbonPersonID=:person AND csSpaceMember.csSpaceID=:space AND csRolePermission.permission=:permission";
        return (int)$this->db()->selectOne($sql, ['person' => $gibbonPersonID, 'space' => $csSpaceID, 'permission' => $permission]) > 0;
    }

    public function selectMembers($csSpaceID): array
    {
        $sql = "SELECT csSpaceMember.csSpaceMemberID, csSpaceMember.gibbonPersonID, csSpaceMember.csRoleID,
                    gibbonPerson.preferredName, gibbonPerson.surname, gibbonPerson.username, csRole.name AS roleName
                FROM csSpaceMember
                JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=csSpaceMember.gibbonPersonID)
                JOIN csRole ON (csRole.csRoleID=csSpaceMember.csRoleID)
                WHERE csSpaceMember.csSpaceID=:space ORDER BY gibbonPerson.surname, gibbonPerson.preferredName, csRole.name";
        return $this->db()->select($sql, ['space' => $csSpaceID])->fetchAll();
    }

    public function addMember($csSpaceID, $gibbonPersonID, $csRoleID): bool
    {
        $sql = "INSERT IGNORE INTO csSpaceMember (csSpaceID, gibbonPersonID, csRoleID) VALUES (:space, :person, :role)";
        $this->db()->insert($sql, ['space' => $csSpaceID, 'person' => $gibbonPersonID, 'role' => $csRoleID]);
        return true;
    }

    public function getMember($csSpaceMemberID): array
    {
        return $this->db()->select("SELECT * FROM csSpaceMember WHERE csSpaceMemberID=:id", ['id' => $csSpaceMemberID])->fetch() ?: [];
    }

    public function removeMember($csSpaceMemberID): bool
    {
        $this->db()->delete("DELETE FROM csSpaceMember WHERE csSpaceMemberID=:id", ['id' => $csSpaceMemberID]);
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
                FROM csSpaceMember JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=csSpaceMember.gibbonPersonID)
                WHERE csSpaceMember.csSpaceID IN (".implode(',', $spaceIDs).") AND gibbonPerson.status='Full'
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
        $sql = "SELECT DISTINCT csSpaceMember.gibbonPersonID
                FROM csSpaceMember
                JOIN csRolePermission ON (csRolePermission.csRoleID=csSpaceMember.csRoleID AND csRolePermission.permission=:permission)
                JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=csSpaceMember.gibbonPersonID AND gibbonPerson.status='Full')
                WHERE csSpaceMember.csSpaceID IN (".implode(',', $spaceIDs).")";
        return array_map('intval', $this->db()->select($sql, ['permission' => $permission])->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function deleteSpace($csSpaceID): bool
    {
        $boards = $this->db()->select("SELECT csBoardID FROM csBoard WHERE csSpaceID=:id", ['id' => $csSpaceID])->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($boards as $csBoardID) {
            $this->db()->delete("DELETE FROM csBoardColumn WHERE csBoardID=:id", ['id' => $csBoardID]);
        }
        $this->db()->delete("DELETE FROM csBoard WHERE csSpaceID=:id", ['id' => $csSpaceID]);
        $this->db()->delete("DELETE FROM csSpaceMember WHERE csSpaceID=:id", ['id' => $csSpaceID]);
        return $this->delete($csSpaceID);
    }
}
