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

    private static $tableName = 'gibbonCsBoard';
    private static $primaryKey = 'csBoardID';
    private static $searchableColumns = ['name', 'description'];

    public function selectBySpace($csSpaceID): array
    {
        return $this->db()->select("SELECT * FROM gibbonCsBoard WHERE csSpaceID=:id ORDER BY name", ['id' => $csSpaceID])->fetchAll();
    }

    /** Active boards of active spaces the person has the view permission in (all of them for $all). */
    public function selectAccessible($gibbonPersonID, bool $all = false): array
    {
        $sql = "SELECT DISTINCT gibbonCsBoard.*, gibbonCsSpace.name AS spaceName
                FROM gibbonCsBoard JOIN gibbonCsSpace ON (gibbonCsSpace.csSpaceID=gibbonCsBoard.csSpaceID)";
        $data = [];
        if (!$all) {
            $sql .= " JOIN gibbonCsSpaceMember ON (gibbonCsSpaceMember.csSpaceID=gibbonCsSpace.csSpaceID AND gibbonCsSpaceMember.gibbonPersonID=:person)
                      JOIN gibbonCsRolePermission ON (gibbonCsRolePermission.csRoleID=gibbonCsSpaceMember.csRoleID AND gibbonCsRolePermission.permission='view_board')";
            $data['person'] = $gibbonPersonID;
        }
        $sql .= " WHERE gibbonCsBoard.active='Y' AND gibbonCsSpace.active='Y' ORDER BY gibbonCsSpace.name, gibbonCsBoard.name";
        return $this->db()->select($sql, $data)->fetchAll();
    }

    /** Every active board of every active space (used to find who must be notified). */
    public function selectAllActive(): array
    {
        $sql = "SELECT gibbonCsBoard.* FROM gibbonCsBoard JOIN gibbonCsSpace ON (gibbonCsSpace.csSpaceID=gibbonCsBoard.csSpaceID)
                WHERE gibbonCsBoard.active='Y' AND gibbonCsSpace.active='Y'";
        return $this->db()->select($sql)->fetchAll();
    }

    /** Columns in order, each with the category of its status (from the status registry). */
    public function selectColumns($csBoardID): array
    {
        $sql = "SELECT gibbonCsBoardColumn.*, COALESCE(gibbonCsStatus.category, 'In Progress') AS category
                FROM gibbonCsBoardColumn LEFT JOIN gibbonCsStatus ON (gibbonCsStatus.name=gibbonCsBoardColumn.name)
                WHERE gibbonCsBoardColumn.csBoardID=:id ORDER BY gibbonCsBoardColumn.sequenceNumber, gibbonCsBoardColumn.csBoardColumnID";
        return $this->db()->select($sql, ['id' => $csBoardID])->fetchAll();
    }

    /**
     * Replace the columns. Each is ['name' => status name, 'category' => To Do|In Progress|Done].
     * A status that does not exist yet is created in the registry; for an existing status the category chosen
     * here is applied everywhere (a status has one category on all boards). Duplicates and blanks are dropped.
     */
    public function replaceColumns($csBoardID, array $columns, StatusGateway $statuses): void
    {
        $seen = [];
        $this->db()->delete("DELETE FROM gibbonCsBoardColumn WHERE csBoardID=:id", ['id' => $csBoardID]);
        foreach ($columns as $column) {
            $name = mb_substr(trim((string)($column['name'] ?? '')), 0, 60);
            if ($name === '' || isset($seen[mb_strtolower($name)])) {
                continue;
            }
            $seen[mb_strtolower($name)] = true;
            $statuses->upsert($name, (string)($column['category'] ?? ''));
            $this->db()->insert(
                "INSERT INTO gibbonCsBoardColumn (csBoardID, name, sequenceNumber) VALUES (:id, :name, :seq)",
                ['id' => $csBoardID, 'name' => $name, 'seq' => count($seen)]
            );
        }
    }

    public function deleteBoard($csBoardID): bool
    {
        $this->db()->delete("DELETE FROM gibbonCsBoardColumn WHERE csBoardID=:id", ['id' => $csBoardID]);
        return $this->delete($csBoardID);
    }
}
