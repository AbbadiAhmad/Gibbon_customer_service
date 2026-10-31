<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\CustomerService\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

class ActivityGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'csTicketActivity';
    private static $primaryKey = 'csTicketActivityID';
    private static $searchableColumns = ['body'];

    public function selectByTicket($csTicketID, bool $includeInternal = false): array
    {
        $sql = "SELECT csTicketActivity.*, gibbonPerson.preferredName, gibbonPerson.surname
                FROM csTicketActivity LEFT JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=csTicketActivity.gibbonPersonID)
                WHERE csTicketActivity.csTicketID=:id".($includeInternal ? "" : " AND csTicketActivity.visibility='Public'")." ORDER BY csTicketActivity.timestamp, csTicketActivity.csTicketActivityID";
        return $this->db()->select($sql, ['id' => $csTicketID])->fetchAll();
    }

    public function log($csTicketID, $gibbonPersonID, string $body, string $kind = 'Event', string $visibility = 'Public'): int
    {
        return (int)$this->insert([
            'csTicketID' => $csTicketID, 'gibbonPersonID' => $gibbonPersonID, 'kind' => $kind, 'body' => $body,
            'visibility' => $visibility == 'Internal' ? 'Internal' : 'Public',
        ]);
    }
}
