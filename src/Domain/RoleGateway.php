<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\administration\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

class RoleGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonCsRole';
    private static $primaryKey = 'csRoleID';
    private static $searchableColumns = ['name', 'description'];

    public function selectAllWithPermissions(): array
    {
        $sql = "SELECT gibbonCsRole.*, GROUP_CONCAT(gibbonCsRolePermission.permission ORDER BY gibbonCsRolePermission.permission SEPARATOR ',') AS permissions
                FROM gibbonCsRole LEFT JOIN gibbonCsRolePermission ON (gibbonCsRolePermission.csRoleID=gibbonCsRole.csRoleID)
                GROUP BY gibbonCsRole.csRoleID ORDER BY gibbonCsRole.name";
        $rows = $this->db()->select($sql)->fetchAll();
        foreach ($rows as &$row) {
            $row['permissions'] = $row['permissions'] === null || $row['permissions'] === '' ? [] : explode(',', $row['permissions']);
        }
        return $rows;
    }

    /** @return array csRoleID => name */
    public function selectOptions(): array
    {
        return $this->db()->select("SELECT csRoleID, name FROM gibbonCsRole ORDER BY name")->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public function selectPermissions($csRoleID): array
    {
        return $this->db()->select("SELECT permission FROM gibbonCsRolePermission WHERE csRoleID=:id", ['id' => $csRoleID])->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function replacePermissions($csRoleID, array $permissions): void
    {
        $this->db()->delete("DELETE FROM gibbonCsRolePermission WHERE csRoleID=:id", ['id' => $csRoleID]);
        foreach (array_unique($permissions) as $permission) {
            $this->db()->insert("INSERT INTO gibbonCsRolePermission (csRoleID, permission) VALUES (:id, :permission)", ['id' => $csRoleID, 'permission' => $permission]);
        }
    }

    public function deleteRole($csRoleID): bool
    {
        $this->db()->delete("DELETE FROM gibbonCsSpaceMember WHERE csRoleID=:id", ['id' => $csRoleID]);
        $this->db()->delete("DELETE FROM gibbonCsRolePermission WHERE csRoleID=:id", ['id' => $csRoleID]);
        return $this->delete($csRoleID);
    }
}
