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
 * The questions on the request form of a ticket type.
 */
class FieldGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonServiceDeskField';
    private static $primaryKey = 'gibbonServiceDeskFieldID';
    private static $searchableColumns = [];

    public function selectFieldsByType($gibbonServiceDeskTicketTypeID)
    {
        $data = ['gibbonServiceDeskTicketTypeID' => $gibbonServiceDeskTicketTypeID];
        $sql = "SELECT * FROM gibbonServiceDeskField WHERE gibbonServiceDeskTicketTypeID=:gibbonServiceDeskTicketTypeID ORDER BY sequenceNumber, gibbonServiceDeskFieldID";

        return $this->db()->select($sql, $data);
    }
}
