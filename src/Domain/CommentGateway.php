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
 * The history of a ticket: comments, internal notes and log lines written by the workflow.
 */
class CommentGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonServiceDeskTicketComment';
    private static $primaryKey = 'gibbonServiceDeskTicketCommentID';
    private static $searchableColumns = [];

    /**
     * Internal notes and internal log lines are left out in the query unless asked for: the requester never receives them.
     */
    public function selectCommentsByTicket($gibbonServiceDeskTicketID, bool $includeInternal)
    {
        $data = ['gibbonServiceDeskTicketID' => $gibbonServiceDeskTicketID];
        $sql = "SELECT gibbonServiceDeskTicketComment.*, gibbonPerson.title, gibbonPerson.surname, gibbonPerson.preferredName, gibbonPerson.image_240
                FROM gibbonServiceDeskTicketComment
                LEFT JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=gibbonServiceDeskTicketComment.gibbonPersonID)
                WHERE gibbonServiceDeskTicketComment.gibbonServiceDeskTicketID=:gibbonServiceDeskTicketID "
                .($includeInternal ? "" : "AND gibbonServiceDeskTicketComment.type IN ('Comment','Log') ")
                ."ORDER BY gibbonServiceDeskTicketComment.timestamp, gibbonServiceDeskTicketComment.gibbonServiceDeskTicketCommentID";

        return $this->db()->select($sql, $data);
    }
}
