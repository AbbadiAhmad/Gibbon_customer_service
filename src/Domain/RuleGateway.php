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

    private static $tableName = 'gibbonCsAutomationRule';
    private static $primaryKey = 'csAutomationRuleID';
    private static $searchableColumns = ['name'];

    public function selectAllWithType(): array
    {
        $sql = "SELECT gibbonCsAutomationRule.*, gibbonCsTicketType.name AS typeName FROM gibbonCsAutomationRule
                LEFT JOIN gibbonCsTicketType ON (gibbonCsTicketType.csTicketTypeID=gibbonCsAutomationRule.csTicketTypeID)
                ORDER BY gibbonCsAutomationRule.triggerKey, gibbonCsAutomationRule.sequenceNumber, gibbonCsAutomationRule.csAutomationRuleID";
        return $this->db()->select($sql)->fetchAll();
    }

    /** Active rules for a trigger that apply to the ticket type (rules without a type apply to all). */
    public function selectActive(string $triggerKey, $csTicketTypeID): array
    {
        $sql = "SELECT * FROM gibbonCsAutomationRule WHERE active='Y' AND triggerKey=:trigger AND (csTicketTypeID IS NULL OR csTicketTypeID=:type)
                ORDER BY sequenceNumber, csAutomationRuleID";
        return $this->db()->select($sql, ['trigger' => $triggerKey, 'type' => $csTicketTypeID])->fetchAll();
    }

    public function nextSequence(): int
    {
        return 1 + (int)$this->db()->selectOne("SELECT COALESCE(MAX(sequenceNumber),0) FROM gibbonCsAutomationRule");
    }
}
