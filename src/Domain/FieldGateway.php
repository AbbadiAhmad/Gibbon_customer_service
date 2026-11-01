<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\administration\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

class FieldGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonCsField';
    private static $primaryKey = 'csFieldID';
    private static $searchableColumns = ['label', 'fieldKey'];

    public function selectByType($csTicketTypeID): array
    {
        return $this->db()->select("SELECT * FROM gibbonCsField WHERE csTicketTypeID=:id ORDER BY sequenceNumber, csFieldID", ['id' => $csTicketTypeID])->fetchAll();
    }

    /** All field keys in use on any type, with the labels used and the types that have them. */
    public function selectDistinctKeys(): array
    {
        $sql = "SELECT gibbonCsField.fieldKey, MIN(gibbonCsField.label) AS label, GROUP_CONCAT(DISTINCT gibbonCsTicketType.name ORDER BY gibbonCsTicketType.name SEPARATOR ', ') AS typeNames
                FROM gibbonCsField JOIN gibbonCsTicketType ON (gibbonCsTicketType.csTicketTypeID=gibbonCsField.csTicketTypeID)
                GROUP BY gibbonCsField.fieldKey ORDER BY label";
        return $this->db()->select($sql)->fetchAll();
    }

    public function keyExists($csTicketTypeID, $fieldKey, $exceptID = 0): bool
    {
        $sql = "SELECT COUNT(*) FROM gibbonCsField WHERE csTicketTypeID=:type AND fieldKey=:key AND csFieldID<>:id";
        return (int)$this->db()->selectOne($sql, ['type' => $csTicketTypeID, 'key' => $fieldKey, 'id' => (int)$exceptID]) > 0;
    }

    public function nextSequence($csTicketTypeID): int
    {
        // Custom fields start after the basic fields (their default order is 1-5, attachments last).
        return 1 + max(5, (int)$this->db()->selectOne("SELECT COALESCE(MAX(sequenceNumber),0) FROM gibbonCsField WHERE csTicketTypeID=:id", ['id' => $csTicketTypeID]));
    }
}
