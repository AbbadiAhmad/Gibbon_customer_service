<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\CustomerService\Service;

/**
 * Per ticket type workflow, stored as JSON in gibbonCsTicketType.workflow:
 *
 *   {"transitions": {"New": ["In Progress", "Waiting"], ...}, "commentRequired": ["Waiting"]}
 *
 * - A status that has an entry may only move to the listed statuses.
 * - A status without an entry may move anywhere (so an empty workflow = no restrictions).
 * - Moving to a "done" status (Resolved / Closed) always needs a resolution note;
 *   commentRequired adds other statuses that need a comment.
 */
class Workflow
{
    /** Statuses that mean "work is finished". */
    const DONE = ['Resolved', 'Closed'];
    /** Status a reopened ticket goes back to. */
    const REOPEN_STATUS = 'New';

    public static function decode($json): array
    {
        $data = is_array($json) ? $json : json_decode((string)$json, true);
        $transitions = [];
        foreach ((array)($data['transitions'] ?? []) as $from => $targets) {
            $targets = array_values(array_unique(array_filter(array_map('trim', (array)$targets), 'strlen')));
            if (trim((string)$from) !== '' && !empty($targets)) {
                $transitions[trim((string)$from)] = $targets;
            }
        }
        $required = array_values(array_unique(array_filter(array_map('trim', (array)($data['commentRequired'] ?? [])), 'strlen')));

        return ['transitions' => $transitions, 'commentRequired' => $required];
    }

    /**
     * Parse the editor text. One transition rule per line: `From: To one, To two`.
     * @return string|null JSON, null when there is nothing to store
     */
    public static function fromText(string $transitionsText, string $commentRequiredText): ?string
    {
        $transitions = [];
        foreach (preg_split('/\R/', $transitionsText) as $line) {
            if (strpos($line, ':') === false) {
                continue;
            }
            [$from, $to] = explode(':', $line, 2);
            $transitions[trim($from)] = explode(',', $to);
        }
        $workflow = self::decode(['transitions' => $transitions, 'commentRequired' => explode(',', $commentRequiredText)]);

        return empty($workflow['transitions']) && empty($workflow['commentRequired']) ? null : json_encode($workflow, JSON_UNESCAPED_UNICODE);
    }

    public static function transitionsToText($json): string
    {
        $lines = [];
        foreach (self::decode($json)['transitions'] as $from => $targets) {
            $lines[] = $from.': '.implode(', ', $targets);
        }
        return implode("\n", $lines);
    }

    public static function commentRequiredToText($json): string
    {
        return implode(', ', self::decode($json)['commentRequired']);
    }

    /**
     * Columns to update when a ticket moves to $status (resolution note / resolved time bookkeeping).
     * Shared by the ticket page, the boards and the automation actions.
     */
    public static function statusChanges(array $ticket, string $status, string $comment = ''): array
    {
        $changes = ['status' => $status];
        if (self::isDone($status)) {
            $changes['resolution'] = $comment !== '' ? $comment : ($ticket['resolution'] ?? null);
            $changes['timestampResolved'] = date('Y-m-d H:i:s');
        } elseif (self::isDone($ticket['status'])) {
            $changes['resolution'] = null;
            $changes['timestampResolved'] = null;
        }
        return $changes;
    }

    public static function isDone(string $status): bool
    {
        return in_array($status, self::DONE, true);
    }

    public static function isAllowed($json, string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }
        $transitions = self::decode($json)['transitions'];
        return !isset($transitions[$from]) || in_array($to, $transitions[$from], true);
    }

    public static function requiresComment($json, string $to): bool
    {
        return self::isDone($to) || in_array($to, self::decode($json)['commentRequired'], true);
    }

    /** Keep only the statuses the ticket may move to from $from (the current status always stays). */
    public static function filterTargets($json, string $from, array $statuses): array
    {
        return array_filter($statuses, function ($status) use ($json, $from) {
            return self::isAllowed($json, $from, $status);
        });
    }
}
