<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\CustomerService\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

class AttachmentGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'csTicketAttachment';
    private static $primaryKey = 'csTicketAttachmentID';
    private static $searchableColumns = ['fileName'];

    /** Attachments of a ticket, grouped by csTicketActivityID (0 = attached to the request itself). */
    public function selectGroupedByTicket($csTicketID, bool $includeInternal = false): array
    {
        $sql = "SELECT csTicketAttachment.*, COALESCE(csTicketActivity.visibility, 'Public') AS visibility
                FROM csTicketAttachment LEFT JOIN csTicketActivity ON (csTicketActivity.csTicketActivityID=csTicketAttachment.csTicketActivityID)
                WHERE csTicketAttachment.csTicketID=:id ORDER BY csTicketAttachment.csTicketAttachmentID";
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
        $sql = "SELECT csTicketAttachment.*, COALESCE(csTicketActivity.visibility, 'Public') AS visibility
                FROM csTicketAttachment LEFT JOIN csTicketActivity ON (csTicketActivity.csTicketActivityID=csTicketAttachment.csTicketActivityID)
                WHERE csTicketAttachment.csTicketAttachmentID=:id";
        return $this->db()->select($sql, ['id' => $csTicketAttachmentID])->fetch() ?: [];
    }
}
