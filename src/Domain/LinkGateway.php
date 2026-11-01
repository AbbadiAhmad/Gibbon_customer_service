<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\CustomerService\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

/**
 * Links between tickets. A row reads "FROM <linkType> TO": FROM blocks TO, FROM duplicates TO, FROM relates to TO.
 * The other side sees the inverse wording (is blocked by / is duplicated by / relates to).
 */
class LinkGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonCsTicketLink';
    private static $primaryKey = 'csTicketLinkID';
    private static $searchableColumns = [];

    /** Wording for the ticket that is being looked at: [linkType][direction]. direction out = this ticket is FROM. */
    public static function labels(): array
    {
        return [
            'blocks'     => ['out' => __('blocks'),     'in' => __('is blocked by')],
            'duplicates' => ['out' => __('duplicates'), 'in' => __('is duplicated by')],
            'relates'    => ['out' => __('relates to'), 'in' => __('relates to')],
        ];
    }

    /** What the add form offers: "type|direction" => label. */
    public static function formOptions(): array
    {
        return [
            'blocks|out'     => __('blocks'),
            'blocks|in'      => __('is blocked by'),
            'duplicates|out' => __('duplicates'),
            'duplicates|in'  => __('is duplicated by'),
            'relates|out'    => __('relates to'),
        ];
    }

    /** Links of a ticket with the ticket on the other side. */
    public function selectForTicket($csTicketID): array
    {
        $cols = "gibbonCsTicketLink.csTicketLinkID, gibbonCsTicketLink.linkType, other.csTicketID AS otherID, other.title AS otherTitle, other.status AS otherStatus";
        $sql = "SELECT {$cols}, 'out' AS direction FROM gibbonCsTicketLink JOIN gibbonCsTicket AS other ON (other.csTicketID=gibbonCsTicketLink.csTicketIDTo)
                    WHERE gibbonCsTicketLink.csTicketIDFrom=:outId
                UNION ALL
                SELECT {$cols}, 'in' AS direction FROM gibbonCsTicketLink JOIN gibbonCsTicket AS other ON (other.csTicketID=gibbonCsTicketLink.csTicketIDFrom)
                    WHERE gibbonCsTicketLink.csTicketIDTo=:inId
                ORDER BY linkType, otherID";
        return $this->db()->select($sql, ['outId' => $csTicketID, 'inId' => $csTicketID])->fetchAll();
    }

    /** @return int[] the ids (of those given) that are blocked by an unfinished ticket */
    public function selectBlockedIDs(array $csTicketIDs): array
    {
        $csTicketIDs = array_map('intval', $csTicketIDs);
        if (empty($csTicketIDs)) {
            return [];
        }
        $sql = "SELECT DISTINCT gibbonCsTicketLink.csTicketIDTo FROM gibbonCsTicketLink JOIN gibbonCsTicket AS blocker ON (blocker.csTicketID=gibbonCsTicketLink.csTicketIDFrom)
                WHERE gibbonCsTicketLink.linkType='blocks' AND blocker.status NOT IN ('Resolved','Closed') AND gibbonCsTicketLink.csTicketIDTo IN (".implode(',', $csTicketIDs).")";
        return array_map('intval', $this->db()->select($sql)->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** Tickets that block this one and are not finished yet. */
    public function selectOpenBlockers($csTicketID): array
    {
        $sql = "SELECT blocker.csTicketID, blocker.title, blocker.status FROM gibbonCsTicketLink
                JOIN gibbonCsTicket AS blocker ON (blocker.csTicketID=gibbonCsTicketLink.csTicketIDFrom)
                WHERE gibbonCsTicketLink.linkType='blocks' AND gibbonCsTicketLink.csTicketIDTo=:id AND blocker.status NOT IN ('Resolved','Closed')";
        return $this->db()->select($sql, ['id' => $csTicketID])->fetchAll();
    }

    /** Is there already any link of this kind between the two tickets (in either direction for "relates")? */
    public function linkExists($from, $to, string $linkType): bool
    {
        $sql = "SELECT COUNT(*) FROM gibbonCsTicketLink WHERE linkType=:type AND ((csTicketIDFrom=:from AND csTicketIDTo=:to) OR (csTicketIDFrom=:to2 AND csTicketIDTo=:from2))";
        return (int)$this->db()->selectOne($sql, ['type' => $linkType, 'from' => $from, 'to' => $to, 'to2' => $to, 'from2' => $from]) > 0;
    }

    /** A pair of tickets may only be linked "blocks" in one direction, and only once for the same type. */
    public function addLink($from, $to, string $linkType, $gibbonPersonID): bool
    {
        if ((int)$from === (int)$to || !in_array($linkType, ['blocks', 'duplicates', 'relates'], true) || $this->linkExists($from, $to, $linkType)) {
            return false;
        }
        return !empty($this->insert(['csTicketIDFrom' => $from, 'csTicketIDTo' => $to, 'linkType' => $linkType, 'gibbonPersonID' => $gibbonPersonID]));
    }

    /** Only links that involve the ticket may be removed through it. */
    public function removeLink($csTicketLinkID, $csTicketID): bool
    {
        $sql = "DELETE FROM gibbonCsTicketLink WHERE csTicketLinkID=:id AND (csTicketIDFrom=:ticket OR csTicketIDTo=:ticket2)";
        return $this->db()->delete($sql, ['id' => $csTicketLinkID, 'ticket' => $csTicketID, 'ticket2' => $csTicketID]) > 0;
    }

    public function deleteForTicket($csTicketID): void
    {
        $this->db()->delete("DELETE FROM gibbonCsTicketLink WHERE csTicketIDFrom=:a OR csTicketIDTo=:b", ['a' => $csTicketID, 'b' => $csTicketID]);
    }
}
