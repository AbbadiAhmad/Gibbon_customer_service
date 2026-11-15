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
 * Remembers, per person, whether they last looked at the tickets as a list or as a board.
 */
class ViewPreferenceGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonServiceDeskViewPreference';
    private static $primaryKey = 'gibbonPersonID';
    private static $searchableColumns = [];

    public function getTicketView($gibbonPersonID): string
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT ticketView FROM gibbonServiceDeskViewPreference WHERE gibbonPersonID=:gibbonPersonID";

        return $this->db()->selectOne($sql, $data) ?: 'List';
    }

    public function setTicketView($gibbonPersonID, string $ticketView)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID, 'ticketView' => $ticketView == 'Board' ? 'Board' : 'List'];
        $sql = "INSERT INTO gibbonServiceDeskViewPreference SET gibbonPersonID=:gibbonPersonID, ticketView=:ticketView
                ON DUPLICATE KEY UPDATE ticketView=VALUES(ticketView)";

        return $this->db()->statement($sql, $data);
    }
}
