<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\CustomerService\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

class RoleGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'csRole';
    private static $primaryKey = 'csRoleID';
    private static $searchableColumns = ['name', 'description'];

    public function selectAllWithPermissions(): array
    {
        $sql = "SELECT csRole.*, GROUP_CONCAT(csRolePermission.permission ORDER BY csRolePermission.permission SEPARATOR ',') AS permissions
                FROM csRole LEFT JOIN csRolePermission ON (csRolePermission.csRoleID=csRole.csRoleID)
                GROUP BY csRole.csRoleID ORDER BY csRole.name";
        $rows = $this->db()->select($sql)->fetchAll();
        foreach ($rows as &$row) {
            $row['permissions'] = $row['permissions'] === null || $row['permissions'] === '' ? [] : explode(',', $row['permissions']);
        }
        return $rows;
    }

    /** @return array csRoleID => name */
    public function selectOptions(): array
    {
        return $this->db()->select("SELECT csRoleID, name FROM csRole ORDER BY name")->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public function selectPermissions($csRoleID): array
    {
        return $this->db()->select("SELECT permission FROM csRolePermission WHERE csRoleID=:id", ['id' => $csRoleID])->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function replacePermissions($csRoleID, array $permissions): void
    {
        $this->db()->delete("DELETE FROM csRolePermission WHERE csRoleID=:id", ['id' => $csRoleID]);
        foreach (array_unique($permissions) as $permission) {
            $this->db()->insert("INSERT INTO csRolePermission (csRoleID, permission) VALUES (:id, :permission)", ['id' => $csRoleID, 'permission' => $permission]);
        }
    }

    public function deleteRole($csRoleID): bool
    {
        $this->db()->delete("DELETE FROM csSpaceMember WHERE csRoleID=:id", ['id' => $csRoleID]);
        $this->db()->delete("DELETE FROM csRolePermission WHERE csRoleID=:id", ['id' => $csRoleID]);
        return $this->delete($csRoleID);
    }
}
