<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\administration\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

use Gibbon\Module\administration\Service\FormAccess;

/** Request forms. A form builds a ticket of its ticket type, but has its own questions, mapping and visibility. */
class FormGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonCsForm';
    private static $primaryKey = 'csFormID';
    private static $searchableColumns = ['name', 'description'];

    public function selectAllWithCounts(): array
    {
        $sql = "SELECT gibbonCsForm.*, gibbonCsTicketType.name AS typeName,
                    (SELECT COUNT(*) FROM gibbonCsFormField WHERE gibbonCsFormField.csFormID=gibbonCsForm.csFormID) AS fieldCount
                FROM gibbonCsForm JOIN gibbonCsTicketType ON (gibbonCsTicketType.csTicketTypeID=gibbonCsForm.csTicketTypeID)
                ORDER BY gibbonCsForm.groupName, gibbonCsForm.sequenceNumber, gibbonCsForm.name";
        return $this->db()->select($sql)->fetchAll();
    }

    /** Forms the user may open: active, the ticket type active, and visible to the role (or everything for $all, e.g. administrators previewing). */
    public function selectVisible(string $roleCategory, $gibbonRoleID, bool $all = false): array
    {
        $sql = "SELECT gibbonCsForm.*, gibbonCsTicketType.name AS typeName FROM gibbonCsForm
                JOIN gibbonCsTicketType ON (gibbonCsTicketType.csTicketTypeID=gibbonCsForm.csTicketTypeID)
                WHERE gibbonCsForm.active='Y' AND gibbonCsTicketType.active='Y'
                ORDER BY gibbonCsForm.groupName, gibbonCsForm.sequenceNumber, gibbonCsForm.name";
        return array_values(array_filter($this->db()->select($sql)->fetchAll(), function ($form) use ($roleCategory, $gibbonRoleID, $all) {
            return $all || FormAccess::allows($form, $roleCategory, $gibbonRoleID);
        }));
    }

    /** A form by its number or by its slug (the readable part of the shareable link). */
    public function getByIdOrSlug($csFormID, $slug): array
    {
        if (!empty($slug)) {
            return $this->db()->select("SELECT * FROM gibbonCsForm WHERE slug=:slug", ['slug' => $slug])->fetch() ?: [];
        }
        return empty($csFormID) ? [] : $this->getByID($csFormID);
    }

    public function slugExists(string $slug, $exceptID = 0): bool
    {
        return (int)$this->db()->selectOne("SELECT COUNT(*) FROM gibbonCsForm WHERE slug=:slug AND csFormID<>:id", ['slug' => $slug, 'id' => (int)$exceptID]) > 0;
    }

    /** @return array csFormID => name of the forms built on a ticket type */
    public function selectByType($csTicketTypeID): array
    {
        return $this->db()->select("SELECT csFormID, name FROM gibbonCsForm WHERE csTicketTypeID=:id ORDER BY name", ['id' => $csTicketTypeID])->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    /** @return array gibbonRoleID => name */
    public function selectRoleOptions(): array
    {
        return $this->db()->select("SELECT gibbonRoleID, name FROM gibbonRole ORDER BY name")->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    /** Tickets keep existing: they just forget which form they came from. */
    public function deleteForm($csFormID): bool
    {
        $this->db()->update("UPDATE gibbonCsTicket SET csFormID=NULL WHERE csFormID=:id", ['id' => $csFormID]);
        $this->db()->delete("DELETE FROM gibbonCsFormField WHERE csFormID=:id", ['id' => $csFormID]);
        return $this->delete($csFormID);
    }
}
