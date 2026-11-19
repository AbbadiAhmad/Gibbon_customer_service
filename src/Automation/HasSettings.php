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
 * An automation script that can be configured on the stage that uses it.
 *
 * Implement this next to AutomationScript and return the settings the script offers. Nothing else has to be written:
 * the stage form shows the settings under the script when it is ticked, the values are checked when the stage is
 * saved and again when the script runs, they are stored with the stage, copied with the workflow, and the script reads
 * them with $context->setting('name'). Scripts without settings do not implement this interface.
 *
 * getSettings() returns  name => definition  where name is letters and digits (starting with a letter) and the
 * definition is an array with:
 *
 *   label       string    Shown on the form (pass it through __m()).
 *   description string    Optional help text under the label.
 *   type        string    select | text | number | yesno
 *   required    bool      Default false. A required setting must have a value for the stage to be saved.
 *   default     mixed     Used when the stage has no value (not for a select that is required).
 *   options     array|callable   select only: value => label, or a function returning that list (use a function for
 *                         lists that come from the database, so they are always current).
 *   min, max    number    number only.
 *   maxLength   int       text only (default 100).
 *
 * A stored select value that is no longer among the options (a code that was deactivated) makes the stage fail safely
 * instead of silently using a different value; see AutomationSettings::resolve().
 */
interface HasSettings
{
    /**
     * @return array<string, array>
     */
    public function getSettings(): array;
}
