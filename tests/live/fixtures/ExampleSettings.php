<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/

namespace Gibbon\Module\ServiceDesk\Automation\Scripts;

use Gibbon\Module\ServiceDesk\Automation\HasSettings;
use Gibbon\Module\ServiceDesk\Automation\AutomationScript;
use Gibbon\Module\ServiceDesk\Automation\AutomationContext;
use Gibbon\Module\ServiceDesk\Automation\AutomationResult;

/**
 * TEST ONLY (copied into the live test site by tests/live/settings.js, never installed by the module).
 *
 * The smallest possible script with settings of every type. It shows that a new script with settings needs nothing
 * but this class: no form code, no storage code, no validation code.
 */
class ExampleSettings implements AutomationScript, HasSettings
{
    public function getName(): string
    {
        return 'Example with settings';
    }

    public function getDescription(): string
    {
        return 'Writes its settings on the ticket.';
    }

    public function getSettings(): array
    {
        return [
            'mode'   => ['label' => 'Mode', 'type' => 'select', 'required' => true, 'options' => ['fast' => 'Fast', 'slow' => 'Slow']],
            'note'   => ['label' => 'Note', 'type' => 'text', 'maxLength' => 10],
            'limit'  => ['label' => 'Limit', 'type' => 'number', 'min' => 1, 'max' => 5, 'default' => 3],
            'notify' => ['label' => 'Notify', 'type' => 'yesno', 'default' => 'Y'],
        ];
    }

    public function run(AutomationContext $context): AutomationResult
    {
        return AutomationResult::success(sprintf('Example ran: mode=%s note=%s limit=%s notify=%s', $context->setting('mode'), $context->setting('note', '-'), $context->setting('limit'), $context->setting('notify')), true);
    }
}
