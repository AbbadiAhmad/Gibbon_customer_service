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
 * Notification policy: one row per person (their default) and one row per person per ticket (the copy they can change
 * for that ticket alone).
 */
class SubscriberGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonServiceDeskTicketSubscriber';
    private static $primaryKey = 'gibbonServiceDeskTicketID';
    private static $searchableColumns = [];

    /**
     * The module default for people who never opened Notification Settings.
     */
    public const DEFAULT_POLICY = ['notifyInApp' => 'Y', 'notifyEmail' => 'Y'];

    public function getPreference($gibbonPersonID): array
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT notifyInApp, notifyEmail FROM gibbonServiceDeskNotificationPreference WHERE gibbonPersonID=:gibbonPersonID";

        return $this->db()->selectOne($sql, $data) ?: self::DEFAULT_POLICY;
    }

    public function setPreference($gibbonPersonID, $notifyInApp, $notifyEmail)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID, 'notifyInApp' => $notifyInApp, 'notifyEmail' => $notifyEmail];
        $sql = "INSERT INTO gibbonServiceDeskNotificationPreference SET gibbonPersonID=:gibbonPersonID, notifyInApp=:notifyInApp, notifyEmail=:notifyEmail
                ON DUPLICATE KEY UPDATE notifyInApp=VALUES(notifyInApp), notifyEmail=VALUES(notifyEmail)";

        return $this->db()->statement($sql, $data);
    }

    public function getSubscription($gibbonServiceDeskTicketID, $gibbonPersonID): array
    {
        $data = ['gibbonServiceDeskTicketID' => $gibbonServiceDeskTicketID, 'gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT notifyInApp, notifyEmail FROM gibbonServiceDeskTicketSubscriber WHERE gibbonServiceDeskTicketID=:gibbonServiceDeskTicketID AND gibbonPersonID=:gibbonPersonID";

        return $this->db()->selectOne($sql, $data) ?: [];
    }

    /**
     * Copies a person's notification policy to a ticket, unless they already follow it.
     */
    public function subscribe($gibbonServiceDeskTicketID, $gibbonPersonID)
    {
        if (empty($gibbonPersonID) || !empty($this->getSubscription($gibbonServiceDeskTicketID, $gibbonPersonID))) {
            return;
        }

        $policy = $this->getPreference($gibbonPersonID);
        $this->setSubscription($gibbonServiceDeskTicketID, $gibbonPersonID, $policy['notifyInApp'], $policy['notifyEmail']);
    }

    public function setSubscription($gibbonServiceDeskTicketID, $gibbonPersonID, $notifyInApp, $notifyEmail)
    {
        $data = ['gibbonServiceDeskTicketID' => $gibbonServiceDeskTicketID, 'gibbonPersonID' => $gibbonPersonID, 'notifyInApp' => $notifyInApp, 'notifyEmail' => $notifyEmail];
        $sql = "INSERT INTO gibbonServiceDeskTicketSubscriber SET gibbonServiceDeskTicketID=:gibbonServiceDeskTicketID, gibbonPersonID=:gibbonPersonID, notifyInApp=:notifyInApp, notifyEmail=:notifyEmail
                ON DUPLICATE KEY UPDATE notifyInApp=VALUES(notifyInApp), notifyEmail=VALUES(notifyEmail)";

        return $this->db()->statement($sql, $data);
    }

    /**
     * Everyone following a ticket, as gibbonPersonID => [notifyInApp, notifyEmail].
     */
    public function selectSubscribersByTicket($gibbonServiceDeskTicketID): array
    {
        $data = ['gibbonServiceDeskTicketID' => $gibbonServiceDeskTicketID];
        $sql = "SELECT gibbonPersonID, notifyInApp, notifyEmail FROM gibbonServiceDeskTicketSubscriber WHERE gibbonServiceDeskTicketID=:gibbonServiceDeskTicketID";

        return $this->db()->select($sql, $data)->fetchGroupedUnique();
    }

    /**
     * Applies a policy to every ticket the person already follows.
     */
    public function updateSubscriptionsByPerson($gibbonPersonID, $notifyInApp, $notifyEmail)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID, 'notifyInApp' => $notifyInApp, 'notifyEmail' => $notifyEmail];
        $sql = "UPDATE gibbonServiceDeskTicketSubscriber SET notifyInApp=:notifyInApp, notifyEmail=:notifyEmail WHERE gibbonPersonID=:gibbonPersonID";

        return $this->db()->update($sql, $data);
    }
}
