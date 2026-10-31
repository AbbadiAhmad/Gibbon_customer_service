<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\CustomerService\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

class RuleGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'csAutomationRule';
    private static $primaryKey = 'csAutomationRuleID';
    private static $searchableColumns = ['name'];

    public function selectAllWithType(): array
    {
        $sql = "SELECT csAutomationRule.*, csTicketType.name AS typeName FROM csAutomationRule
                LEFT JOIN csTicketType ON (csTicketType.csTicketTypeID=csAutomationRule.csTicketTypeID)
                ORDER BY csAutomationRule.triggerKey, csAutomationRule.sequenceNumber, csAutomationRule.csAutomationRuleID";
        return $this->db()->select($sql)->fetchAll();
    }

    /** Active rules for a trigger that apply to the ticket type (rules without a type apply to all). */
    public function selectActive(string $triggerKey, $csTicketTypeID): array
    {
        $sql = "SELECT * FROM csAutomationRule WHERE active='Y' AND triggerKey=:trigger AND (csTicketTypeID IS NULL OR csTicketTypeID=:type)
                ORDER BY sequenceNumber, csAutomationRuleID";
        return $this->db()->select($sql, ['trigger' => $triggerKey, 'type' => $csTicketTypeID])->fetchAll();
    }

    public function nextSequence(): int
    {
        return 1 + (int)$this->db()->selectOne("SELECT COALESCE(MAX(sequenceNumber),0) FROM csAutomationRule");
    }
}
