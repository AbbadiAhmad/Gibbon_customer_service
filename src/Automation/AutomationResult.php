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
 * What an automation script reports back: whether it worked, and a message for the ticket.
 */
class AutomationResult
{
    protected $success;
    protected $message;
    protected $internal;

    protected function __construct(bool $success, string $message, bool $internal = false)
    {
        $this->success = $success;
        $this->message = $message;
        $this->internal = $internal;
    }

    /**
     * @param string $message  Written on the ticket's history.
     * @param bool   $internal True: only the team reads the message. False (default): the requester reads it too.
     */
    public static function success(string $message = '', bool $internal = false): self
    {
        return new self(true, $message, $internal);
    }

    public function isInternal(): bool
    {
        return $this->internal;
    }

    /**
     * @param string $message Written on the ticket as an internal note for the team.
     */
    public static function failure(string $message): self
    {
        return new self(false, $message);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
