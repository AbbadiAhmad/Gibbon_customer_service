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
 * The adults of a family, read from Gibbon's own family tables: who else may follow the tickets of a parent.
 * An adult counts only with "data access" (childDataAccess = Y) and an active user account.
 */
class GuardianGateway extends Gateway
{
    /**
     * The other adults of the person's families, as gibbonPersonID list.
     */
    public function selectCoAdultIDs($gibbonPersonID): array
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT DISTINCT other.gibbonPersonID
                FROM gibbonFamilyAdult AS mine
                JOIN gibbonFamilyAdult AS other ON (other.gibbonFamilyID=mine.gibbonFamilyID AND other.gibbonPersonID<>mine.gibbonPersonID)
                JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=other.gibbonPersonID AND gibbonPerson.status='Full')
                WHERE mine.gibbonPersonID=:gibbonPersonID AND other.childDataAccess='Y'";

        return $this->db()->select($sql, $data)->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Does the viewer share a family with the requester, with data access? (Both are adults of that family.)
     */
    public function isCoAdult($gibbonPersonIDViewer, $gibbonPersonIDRequester): bool
    {
        if (empty($gibbonPersonIDViewer) || empty($gibbonPersonIDRequester)) return false;

        $data = ['viewer' => $gibbonPersonIDViewer, 'requester' => $gibbonPersonIDRequester];
        $sql = "SELECT COUNT(*)
                FROM gibbonFamilyAdult AS viewer
                JOIN gibbonFamilyAdult AS requester ON (requester.gibbonFamilyID=viewer.gibbonFamilyID)
                WHERE viewer.gibbonPersonID=:viewer AND viewer.childDataAccess='Y' AND requester.gibbonPersonID=:requester";

        return (int) $this->db()->selectOne($sql, $data) > 0;
    }
}
