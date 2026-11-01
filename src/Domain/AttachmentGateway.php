<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\administration\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

class AttachmentGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonCsTicketAttachment';
    private static $primaryKey = 'csTicketAttachmentID';
    private static $searchableColumns = ['fileName'];

    /** Attachments of a ticket, grouped by csTicketActivityID (0 = attached to the request itself). */
    public function selectGroupedByTicket($csTicketID, bool $includeInternal = false): array
    {
        $sql = "SELECT gibbonCsTicketAttachment.*, COALESCE(gibbonCsTicketActivity.visibility, 'Public') AS visibility
                FROM gibbonCsTicketAttachment LEFT JOIN gibbonCsTicketActivity ON (gibbonCsTicketActivity.csTicketActivityID=gibbonCsTicketAttachment.csTicketActivityID)
                WHERE gibbonCsTicketAttachment.csTicketID=:id ORDER BY gibbonCsTicketAttachment.csTicketAttachmentID";
        $grouped = [];
        foreach ($this->db()->select($sql, ['id' => $csTicketID])->fetchAll() as $row) {
            if ($row['visibility'] == 'Internal' && !$includeInternal) {
                continue;
            }
            $grouped[(int)$row['csTicketActivityID']][] = $row;
        }
        return $grouped;
    }

    /** Attachment with the visibility of the comment it belongs to. */
    public function getWithVisibility($csTicketAttachmentID): array
    {
        $sql = "SELECT gibbonCsTicketAttachment.*, COALESCE(gibbonCsTicketActivity.visibility, 'Public') AS visibility
                FROM gibbonCsTicketAttachment LEFT JOIN gibbonCsTicketActivity ON (gibbonCsTicketActivity.csTicketActivityID=gibbonCsTicketAttachment.csTicketActivityID)
                WHERE gibbonCsTicketAttachment.csTicketAttachmentID=:id";
        return $this->db()->select($sql, ['id' => $csTicketAttachmentID])->fetch() ?: [];
    }
}
