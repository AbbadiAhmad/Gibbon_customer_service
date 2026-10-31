<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\CustomerService\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

class BoardGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'csBoard';
    private static $primaryKey = 'csBoardID';
    private static $searchableColumns = ['name', 'description'];

    public function selectBySpace($csSpaceID): array
    {
        return $this->db()->select("SELECT * FROM csBoard WHERE csSpaceID=:id ORDER BY name", ['id' => $csSpaceID])->fetchAll();
    }

    /** Active boards of active spaces the person has the view permission in (all of them for $all). */
    public function selectAccessible($gibbonPersonID, bool $all = false): array
    {
        $sql = "SELECT DISTINCT csBoard.*, csSpace.name AS spaceName
                FROM csBoard JOIN csSpace ON (csSpace.csSpaceID=csBoard.csSpaceID)";
        $data = [];
        if (!$all) {
            $sql .= " JOIN csSpaceMember ON (csSpaceMember.csSpaceID=csSpace.csSpaceID AND csSpaceMember.gibbonPersonID=:person)
                      JOIN csRolePermission ON (csRolePermission.csRoleID=csSpaceMember.csRoleID AND csRolePermission.permission='view_board')";
            $data['person'] = $gibbonPersonID;
        }
        $sql .= " WHERE csBoard.active='Y' AND csSpace.active='Y' ORDER BY csSpace.name, csBoard.name";
        return $this->db()->select($sql, $data)->fetchAll();
    }

    /** Every active board of every active space (used to find who must be notified). */
    public function selectAllActive(): array
    {
        $sql = "SELECT csBoard.* FROM csBoard JOIN csSpace ON (csSpace.csSpaceID=csBoard.csSpaceID)
                WHERE csBoard.active='Y' AND csSpace.active='Y'";
        return $this->db()->select($sql)->fetchAll();
    }

    public function selectColumns($csBoardID): array
    {
        return $this->db()->select("SELECT * FROM csBoardColumn WHERE csBoardID=:id ORDER BY sequenceNumber, csBoardColumnID", ['id' => $csBoardID])->fetchAll();
    }

    /** Replace the columns with the given ordered list of names (duplicates and blanks dropped). */
    public function replaceColumns($csBoardID, array $names): void
    {
        $seen = [];
        $this->db()->delete("DELETE FROM csBoardColumn WHERE csBoardID=:id", ['id' => $csBoardID]);
        foreach ($names as $name) {
            $name = mb_substr(trim($name), 0, 60);
            if ($name === '' || isset($seen[mb_strtolower($name)])) {
                continue;
            }
            $seen[mb_strtolower($name)] = true;
            $this->db()->insert(
                "INSERT INTO csBoardColumn (csBoardID, name, sequenceNumber) VALUES (:id, :name, :seq)",
                ['id' => $csBoardID, 'name' => $name, 'seq' => count($seen)]
            );
        }
    }

    public function deleteBoard($csBoardID): bool
    {
        $this->db()->delete("DELETE FROM csBoardColumn WHERE csBoardID=:id", ['id' => $csBoardID]);
        return $this->delete($csBoardID);
    }
}
