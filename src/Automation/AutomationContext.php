<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/

namespace Gibbon\Module\ServiceDesk\Automation;

use Psr\Container\ContainerInterface;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Module\ServiceDesk\Service\RequestFields;

/**
 * Everything an automation script may use: the ticket, the answers of its request form, who moved the ticket,
 * and the database connection that holds the running transaction.
 */
class AutomationContext
{
    protected $ticket;
    protected $stage;
    protected $actorID;
    protected $db;
    protected $values;

    public function __construct(array $ticket, array $stage, $actorID, Connection $db)
    {
        $this->ticket = $ticket;
        $this->stage = $stage;
        $this->actorID = $actorID;
        $this->db = $db;
        $this->values = RequestFields::values($ticket['fields'] ?? '');
    }

    /**
     * The ticket row (gibbonServiceDeskTicketID, subject, gibbonPersonIDRequester, gibbonSchoolYearID, ...).
     */
    public function ticket(): array
    {
        return $this->ticket;
    }

    public function ticketID(): int
    {
        return intval($this->ticket['gibbonServiceDeskTicketID']);
    }

    /**
     * The stage being entered (name, category, automation, nextStage).
     */
    public function stage(): array
    {
        return $this->stage;
    }

    /**
     * gibbonPersonID of the person who moved the ticket into the stage.
     */
    public function actorID()
    {
        return $this->actorID;
    }

    /**
     * An answer of the request form by field name. Dates are Y-m-d, Students is a list of gibbonPersonID,
     * Classes is a list of [gibbonPersonID, gibbonCourseClassID].
     */
    public function field(string $name, $default = null)
    {
        $value = $this->values[$name] ?? null;

        return $value === null || $value === '' || $value === [] ? $default : $value;
    }

    public function fields(): array
    {
        return $this->values;
    }

    public function db(): Connection
    {
        return $this->db;
    }

    /**
     * Gibbon's container, to get core gateways: $context->container()->get(SomeGateway::class).
     */
    public function container(): ContainerInterface
    {
        // Gibbon's container cannot inject itself (it does not manage ContainerInterface), so the global is used,
        // as Gibbon core does.
        global $container;

        return $container;
    }
}
