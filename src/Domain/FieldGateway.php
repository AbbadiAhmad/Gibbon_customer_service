<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\CustomerService\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

class FieldGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'csField';
    private static $primaryKey = 'csFieldID';
    private static $searchableColumns = ['label', 'fieldKey'];

    public function selectByType($csTicketTypeID): array
    {
        return $this->db()->select("SELECT * FROM csField WHERE csTicketTypeID=:id ORDER BY sequenceNumber, csFieldID", ['id' => $csTicketTypeID])->fetchAll();
    }

    /** All field keys in use on any type, with the labels used and the types that have them. */
    public function selectDistinctKeys(): array
    {
        $sql = "SELECT csField.fieldKey, MIN(csField.label) AS label, GROUP_CONCAT(DISTINCT csTicketType.name ORDER BY csTicketType.name SEPARATOR ', ') AS typeNames
                FROM csField JOIN csTicketType ON (csTicketType.csTicketTypeID=csField.csTicketTypeID)
                GROUP BY csField.fieldKey ORDER BY label";
        return $this->db()->select($sql)->fetchAll();
    }

    public function keyExists($csTicketTypeID, $fieldKey, $exceptID = 0): bool
    {
        $sql = "SELECT COUNT(*) FROM csField WHERE csTicketTypeID=:type AND fieldKey=:key AND csFieldID<>:id";
        return (int)$this->db()->selectOne($sql, ['type' => $csTicketTypeID, 'key' => $fieldKey, 'id' => (int)$exceptID]) > 0;
    }

    public function nextSequence($csTicketTypeID): int
    {
        return 1 + (int)$this->db()->selectOne("SELECT COALESCE(MAX(sequenceNumber),0) FROM csField WHERE csTicketTypeID=:id", ['id' => $csTicketTypeID]);
    }
}
