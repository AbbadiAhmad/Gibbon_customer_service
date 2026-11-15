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

/**
 * An automation script: code that runs when a ticket enters a workflow stage.
 *
 * To add one, create a class in src/Automation/Scripts/ (file name = class name) that implements this interface.
 * It then appears in the "Automation" list when editing a stage. No registration needed.
 *
 * How a script is run (see TicketService::changeStage):
 *   1. a database transaction is started and the ticket is moved into the stage,
 *   2. run() is called,
 *   3. success: the transaction is committed, the result message is written on the ticket as a comment, and the
 *      ticket moves on to the stage's "next stage" if one is set,
 *   4. failure (AutomationResult::failure or any exception): everything is rolled back, the ticket stays where it
 *      was and the reason is written on the ticket as an internal note.
 *
 * Rules for run():
 *   - use $context->db() (or gateways from $context->container()) so the work is part of the transaction,
 *   - Gibbon's database layer does not throw on a failed query: check the database after writing and return
 *     AutomationResult::failure() when the data is not what you expect,
 *   - do not start, commit or roll back transactions, and do not change the ticket itself.
 */
interface AutomationScript
{
    /**
     * Short name shown to administrators when choosing an automation for a stage.
     */
    public function getName(): string;

    /**
     * One or two sentences: what the script does and which request form fields (by name) it reads.
     */
    public function getDescription(): string;

    public function run(AutomationContext $context): AutomationResult;
}
