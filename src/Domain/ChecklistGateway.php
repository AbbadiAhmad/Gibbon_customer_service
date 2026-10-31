<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\CustomerService\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

/** Checklists (with items) inside a ticket. */
class ChecklistGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'csChecklist';
    private static $primaryKey = 'csChecklistID';
    private static $searchableColumns = ['title'];

    /** Checklists of a ticket, each with an `items` list. */
    public function selectByTicket($csTicketID): array
    {
        $lists = $this->db()->select("SELECT * FROM csChecklist WHERE csTicketID=:id ORDER BY sequenceNumber, csChecklistID", ['id' => $csTicketID])->fetchAll();
        foreach ($lists as &$list) {
            $list['items'] = $this->db()->select("SELECT * FROM csChecklistItem WHERE csChecklistID=:id ORDER BY sequenceNumber, csChecklistItemID", ['id' => $list['csChecklistID']])->fetchAll();
        }
        return $lists;
    }

    /** @return array [done, total] over every checklist of the ticket */
    public function progress($csTicketID): array
    {
        $sql = "SELECT SUM(csChecklistItem.done='Y') AS doneCount, COUNT(*) AS total FROM csChecklistItem
                JOIN csChecklist ON (csChecklist.csChecklistID=csChecklistItem.csChecklistID) WHERE csChecklist.csTicketID=:id";
        $row = $this->db()->select($sql, ['id' => $csTicketID])->fetch();
        return [(int)($row['doneCount'] ?? 0), (int)($row['total'] ?? 0)];
    }

    public function getForTicket($csChecklistID, $csTicketID): array
    {
        return $this->db()->select("SELECT * FROM csChecklist WHERE csChecklistID=:id AND csTicketID=:ticket", ['id' => $csChecklistID, 'ticket' => $csTicketID])->fetch() ?: [];
    }

    public function addChecklist($csTicketID, string $title): int
    {
        $seq = 1 + (int)$this->db()->selectOne("SELECT COALESCE(MAX(sequenceNumber),0) FROM csChecklist WHERE csTicketID=:id", ['id' => $csTicketID]);
        return (int)$this->insert(['csTicketID' => $csTicketID, 'title' => mb_substr($title, 0, 100), 'sequenceNumber' => $seq]);
    }

    public function addItem($csChecklistID, string $text): int
    {
        $seq = 1 + (int)$this->db()->selectOne("SELECT COALESCE(MAX(sequenceNumber),0) FROM csChecklistItem WHERE csChecklistID=:id", ['id' => $csChecklistID]);
        return (int)$this->db()->insert(
            "INSERT INTO csChecklistItem (csChecklistID, text, sequenceNumber) VALUES (:list, :text, :seq)",
            ['list' => $csChecklistID, 'text' => mb_substr($text, 0, 255), 'seq' => $seq]
        );
    }

    /** Set the done flag of every item of the checklist: ticked ids are done, the others are not. */
    public function setDone($csChecklistID, array $doneItemIDs, $gibbonPersonID): void
    {
        $doneItemIDs = array_map('intval', $doneItemIDs);
        foreach ($this->db()->select("SELECT csChecklistItemID, done FROM csChecklistItem WHERE csChecklistID=:id", ['id' => $csChecklistID])->fetchAll() as $item) {
            $shouldBeDone = in_array((int)$item['csChecklistItemID'], $doneItemIDs, true);
            if ($shouldBeDone && $item['done'] != 'Y') {
                $this->db()->update("UPDATE csChecklistItem SET done='Y', gibbonPersonIDDone=:person, timestampDone=NOW() WHERE csChecklistItemID=:id", ['person' => $gibbonPersonID, 'id' => $item['csChecklistItemID']]);
            } elseif (!$shouldBeDone && $item['done'] == 'Y') {
                $this->db()->update("UPDATE csChecklistItem SET done='N', gibbonPersonIDDone=NULL, timestampDone=NULL WHERE csChecklistItemID=:id", ['id' => $item['csChecklistItemID']]);
            }
        }
    }

    public function removeItems($csChecklistID, array $itemIDs): void
    {
        foreach (array_map('intval', $itemIDs) as $id) {
            $this->db()->delete("DELETE FROM csChecklistItem WHERE csChecklistItemID=:id AND csChecklistID=:list", ['id' => $id, 'list' => $csChecklistID]);
        }
    }

    public function deleteChecklist($csChecklistID): bool
    {
        $this->db()->delete("DELETE FROM csChecklistItem WHERE csChecklistID=:id", ['id' => $csChecklistID]);
        return $this->delete($csChecklistID);
    }

    public function deleteForTicket($csTicketID): void
    {
        foreach ($this->db()->select("SELECT csChecklistID FROM csChecklist WHERE csTicketID=:id", ['id' => $csTicketID])->fetchAll(\PDO::FETCH_COLUMN) as $id) {
            $this->deleteChecklist($id);
        }
    }
}
