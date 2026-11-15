<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/


namespace Gibbon\Module\ServiceDesk\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

/**
 * Workflow stages. Stages without a ticket type form the default workflow, which is copied to every new ticket type.
 */
class StageGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonServiceDeskStage';
    private static $primaryKey = 'gibbonServiceDeskStageID';
    private static $searchableColumns = [];

    /**
     * The stages of a ticket type in order, or the default workflow when the type is empty.
     */
    public function selectStagesByType($gibbonServiceDeskTicketTypeID = null)
    {
        if (empty($gibbonServiceDeskTicketTypeID)) {
            $sql = "SELECT * FROM gibbonServiceDeskStage WHERE gibbonServiceDeskTicketTypeID IS NULL ORDER BY sequenceNumber, gibbonServiceDeskStageID";
            return $this->db()->select($sql);
        }

        $data = ['gibbonServiceDeskTicketTypeID' => $gibbonServiceDeskTicketTypeID];
        $sql = "SELECT * FROM gibbonServiceDeskStage WHERE gibbonServiceDeskTicketTypeID=:gibbonServiceDeskTicketTypeID ORDER BY sequenceNumber, gibbonServiceDeskStageID";

        return $this->db()->select($sql, $data);
    }

    /**
     * The stage new tickets of a type start in: the first one in order.
     */
    public function getFirstStage($gibbonServiceDeskTicketTypeID)
    {
        return $this->selectStagesByType($gibbonServiceDeskTicketTypeID)->fetch() ?: [];
    }

    public function getStageByName($gibbonServiceDeskTicketTypeID, $name)
    {
        foreach ($this->selectStagesByType($gibbonServiceDeskTicketTypeID)->fetchAll() as $stage) {
            if ($stage['name'] === $name) return $stage;
        }

        return [];
    }

    /**
     * Gives a new ticket type its own copy of the default workflow.
     */
    public function copyDefaultWorkflow($gibbonServiceDeskTicketTypeID)
    {
        $data = ['gibbonServiceDeskTicketTypeID' => $gibbonServiceDeskTicketTypeID];
        $sql = "INSERT INTO gibbonServiceDeskStage (gibbonServiceDeskTicketTypeID, name, category, sequenceNumber, automation, nextStage, withdrawn)
                SELECT :gibbonServiceDeskTicketTypeID, name, category, sequenceNumber, automation, nextStage, withdrawn
                FROM gibbonServiceDeskStage WHERE gibbonServiceDeskTicketTypeID IS NULL ORDER BY sequenceNumber, gibbonServiceDeskStageID";

        return $this->db()->statement($sql, $data);
    }

    /**
     * Keeps "next stage" references (stored by name) valid when a stage is renamed.
     */
    public function renameNextStage($gibbonServiceDeskTicketTypeID, $oldName, $newName)
    {
        $data = ['oldName' => $oldName, 'newName' => $newName];
        if (empty($gibbonServiceDeskTicketTypeID)) {
            $sql = "UPDATE gibbonServiceDeskStage SET nextStage=:newName WHERE nextStage=:oldName AND gibbonServiceDeskTicketTypeID IS NULL";
        } else {
            $data['gibbonServiceDeskTicketTypeID'] = $gibbonServiceDeskTicketTypeID;
            $sql = "UPDATE gibbonServiceDeskStage SET nextStage=:newName WHERE nextStage=:oldName AND gibbonServiceDeskTicketTypeID=:gibbonServiceDeskTicketTypeID";
        }

        return $this->db()->update($sql, $data);
    }

    public function countTickets($gibbonServiceDeskStageID): int
    {
        $data = ['gibbonServiceDeskStageID' => $gibbonServiceDeskStageID];
        $sql = "SELECT COUNT(*) FROM gibbonServiceDeskTicket WHERE gibbonServiceDeskStageID=:gibbonServiceDeskStageID";

        return (int) $this->db()->selectOne($sql, $data);
    }

    /**
     * Distinct stage names over all workflows, for the ticket filters.
     */
    public function selectStageNames(): array
    {
        $sql = "SELECT name FROM gibbonServiceDeskStage GROUP BY name ORDER BY MIN(sequenceNumber), name";

        return $this->db()->select($sql)->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * The stage a requester's "Withdraw" sends a ticket to (flag withdrawn), or [] when the workflow has none.
     */
    public function getWithdrawnStage($gibbonServiceDeskTicketTypeID): array
    {
        foreach ($this->selectStagesByType($gibbonServiceDeskTicketTypeID)->fetchAll() as $stage) {
            if ($stage['withdrawn'] == 'Y') return $stage;
        }

        return [];
    }

    /**
     * Makes the given stage the only withdrawn stage of its workflow.
     */
    public function clearOtherWithdrawnStages($gibbonServiceDeskTicketTypeID, $gibbonServiceDeskStageID)
    {
        $data = ['gibbonServiceDeskStageID' => $gibbonServiceDeskStageID];
        if (empty($gibbonServiceDeskTicketTypeID)) {
            $sql = "UPDATE gibbonServiceDeskStage SET withdrawn='N' WHERE gibbonServiceDeskTicketTypeID IS NULL AND gibbonServiceDeskStageID<>:gibbonServiceDeskStageID";
        } else {
            $data['gibbonServiceDeskTicketTypeID'] = $gibbonServiceDeskTicketTypeID;
            $sql = "UPDATE gibbonServiceDeskStage SET withdrawn='N' WHERE gibbonServiceDeskTicketTypeID=:gibbonServiceDeskTicketTypeID AND gibbonServiceDeskStageID<>:gibbonServiceDeskStageID";
        }

        return $this->db()->update($sql, $data);
    }
}
