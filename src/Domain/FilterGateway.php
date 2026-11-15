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
 * Saved ticket filters: personal ones, and shared ones made by administrators (for everyone or for one team).
 */
class FilterGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonServiceDeskFilter';
    private static $primaryKey = 'gibbonServiceDeskFilterID';
    private static $searchableColumns = [];

    /**
     * The filters a person can use: shared first, then their own. Administrators see every shared filter.
     */
    public function selectFiltersByPerson($gibbonPersonID, bool $isAdmin)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID, 'gibbonPersonIDMember' => $gibbonPersonID, 'isAdmin' => $isAdmin ? 1 : 0];
        $sql = "SELECT gibbonServiceDeskFilter.*, gibbonServiceDeskTeam.name AS teamName
                FROM gibbonServiceDeskFilter
                LEFT JOIN gibbonServiceDeskTeam ON (gibbonServiceDeskTeam.gibbonServiceDeskTeamID=gibbonServiceDeskFilter.gibbonServiceDeskTeamID)
                WHERE gibbonServiceDeskFilter.gibbonPersonID=:gibbonPersonID
                OR (gibbonServiceDeskFilter.gibbonPersonID IS NULL AND (
                        gibbonServiceDeskFilter.gibbonServiceDeskTeamID IS NULL
                        OR :isAdmin=1
                        OR gibbonServiceDeskFilter.gibbonServiceDeskTeamID IN (SELECT gibbonServiceDeskTeamID FROM gibbonServiceDeskTeamMember WHERE gibbonPersonID=:gibbonPersonIDMember)
                    ))
                ORDER BY (gibbonServiceDeskFilter.gibbonPersonID IS NULL) DESC, gibbonServiceDeskFilter.sequenceNumber, gibbonServiceDeskFilter.name";

        return $this->db()->select($sql, $data);
    }
}
