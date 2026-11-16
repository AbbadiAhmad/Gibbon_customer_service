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

use Gibbon\Domain\Gateway;

/**
 * When each person last looked at each ticket. A ticket changed after that is shown to them as "Updated".
 */
class SeenGateway extends Gateway
{
    public function markSeen($gibbonServiceDeskTicketID, $gibbonPersonID)
    {
        if (empty($gibbonServiceDeskTicketID) || empty($gibbonPersonID)) return;

        $data = ['gibbonServiceDeskTicketID' => $gibbonServiceDeskTicketID, 'gibbonPersonID' => $gibbonPersonID, 'timestampSeen' => date('Y-m-d H:i:s')];
        $sql = "INSERT INTO gibbonServiceDeskTicketSeen SET gibbonServiceDeskTicketID=:gibbonServiceDeskTicketID, gibbonPersonID=:gibbonPersonID, timestampSeen=:timestampSeen
                ON DUPLICATE KEY UPDATE timestampSeen=VALUES(timestampSeen)";

        return $this->db()->statement($sql, $data);
    }
}
