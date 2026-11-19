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
 * Which staff are told about the tickets of a ticket type, and how. One row per person per type, with four switches:
 * new tickets and updates, each in the notification centre and by email. Nobody has a row until they (or an
 * administrator) choose one: staff are not told about tickets they are not involved in.
 */
class TypeNotificationGateway extends Gateway
{
    public const FLAGS = ['newInApp', 'newEmail', 'updateInApp', 'updateEmail'];

    /**
     * The people told about a ticket type, with their names, for the administrator's list.
     */
    public function selectByType($gibbonServiceDeskTicketTypeID)
    {
        $data = ['gibbonServiceDeskTicketTypeID' => $gibbonServiceDeskTicketTypeID];
        $sql = "SELECT gibbonServiceDeskTypeNotification.*, gibbonPerson.title, gibbonPerson.surname, gibbonPerson.preferredName, gibbonPerson.status
                FROM gibbonServiceDeskTypeNotification
                JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=gibbonServiceDeskTypeNotification.gibbonPersonID)
                WHERE gibbonServiceDeskTypeNotification.gibbonServiceDeskTicketTypeID=:gibbonServiceDeskTicketTypeID
                ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

        return $this->db()->select($sql, $data);
    }

    /**
     * One person's switches, as typeID => [flag => 'Y'|'N'].
     */
    public function selectByPerson($gibbonPersonID): array
    {
        $rows = $this->db()->select("SELECT * FROM gibbonServiceDeskTypeNotification WHERE gibbonPersonID=:gibbonPersonID", ['gibbonPersonID' => $gibbonPersonID])->fetchAll();

        $byType = [];
        foreach ($rows as $row) {
            $byType[intval($row['gibbonServiceDeskTicketTypeID'])] = array_intersect_key($row, array_flip(self::FLAGS));
        }

        return $byType;
    }

    /**
     * Who has chosen to be told about this kind of event on a ticket type: personID => [notifyInApp, notifyEmail].
     * Whether they may still work on the type is the caller's check (they can have left the team since).
     *
     * @param string $event 'new' (a ticket was created in the type, or moved into it) or 'update'
     */
    public function selectRecipients($gibbonServiceDeskTicketTypeID, string $event): array
    {
        $inApp = $event == 'new' ? 'newInApp' : 'updateInApp';
        $email = $event == 'new' ? 'newEmail' : 'updateEmail';

        $sql = "SELECT gibbonPersonID, {$inApp} AS notifyInApp, {$email} AS notifyEmail
                FROM gibbonServiceDeskTypeNotification
                WHERE gibbonServiceDeskTicketTypeID=:gibbonServiceDeskTicketTypeID AND ({$inApp}='Y' OR {$email}='Y')";

        $people = [];
        foreach ($this->db()->select($sql, ['gibbonServiceDeskTicketTypeID' => $gibbonServiceDeskTicketTypeID])->fetchAll() as $row) {
            $people[intval($row['gibbonPersonID'])] = ['notifyInApp' => $row['notifyInApp'], 'notifyEmail' => $row['notifyEmail']];
        }

        return $people;
    }

    /**
     * Stores a person's switches for a type; with every switch off the row is removed.
     *
     * @param array $flags flag => 'Y'|'N' (missing flags count as 'N')
     */
    public function save($gibbonServiceDeskTicketTypeID, $gibbonPersonID, array $flags)
    {
        $values = [];
        foreach (self::FLAGS as $flag) {
            $values[$flag] = ($flags[$flag] ?? 'N') == 'Y' ? 'Y' : 'N';
        }

        $data = ['gibbonServiceDeskTicketTypeID' => $gibbonServiceDeskTicketTypeID, 'gibbonPersonID' => $gibbonPersonID];
        if (!in_array('Y', $values, true)) {
            return $this->db()->delete("DELETE FROM gibbonServiceDeskTypeNotification WHERE gibbonServiceDeskTicketTypeID=:gibbonServiceDeskTicketTypeID AND gibbonPersonID=:gibbonPersonID", $data);
        }

        $sql = "INSERT INTO gibbonServiceDeskTypeNotification SET gibbonServiceDeskTicketTypeID=:gibbonServiceDeskTicketTypeID, gibbonPersonID=:gibbonPersonID,
                newInApp=:newInApp, newEmail=:newEmail, updateInApp=:updateInApp, updateEmail=:updateEmail
                ON DUPLICATE KEY UPDATE newInApp=VALUES(newInApp), newEmail=VALUES(newEmail), updateInApp=VALUES(updateInApp), updateEmail=VALUES(updateEmail)";

        return $this->db()->statement($sql, $data + $values);
    }

    public function remove($gibbonServiceDeskTicketTypeID, $gibbonPersonID)
    {
        return $this->db()->delete(
            "DELETE FROM gibbonServiceDeskTypeNotification WHERE gibbonServiceDeskTicketTypeID=:gibbonServiceDeskTicketTypeID AND gibbonPersonID=:gibbonPersonID",
            ['gibbonServiceDeskTicketTypeID' => $gibbonServiceDeskTicketTypeID, 'gibbonPersonID' => $gibbonPersonID]
        );
    }
}
