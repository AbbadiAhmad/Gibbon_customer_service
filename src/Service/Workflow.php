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
 * - Moving to a status of the Done category (Resolved, Closed, Declined...) always needs a resolution note;
 *   commentRequired adds other statuses that need a comment.
 */
class Workflow
{
    /** Every status belongs to one of these categories (set per status, shared by all boards). */
    const CATEGORIES = ['To Do', 'In Progress', 'Done'];
    /** Category of a status that nobody has classified yet. */
    const DEFAULT_CATEGORY = 'In Progress';
    /** SQL: the names of all statuses that mean "work is finished". Use as `status NOT IN (...)`. */
    const DONE_SQL = "(SELECT name FROM gibbonCsStatus WHERE category='Done')";
    /** Statuses assumed when the registry cannot be read (tests, before the upgrade ran). */
    const FALLBACK = ['New' => 'To Do', 'In Progress' => 'In Progress', 'Waiting' => 'In Progress', 'Resolved' => 'Done', 'Closed' => 'Done'];

    /** Status a reopened ticket goes back to. */
    const REOPEN_STATUS = 'New';

    private static $statusMap;

    public static function categoryOptions(): array
    {
        return array_combine(self::CATEGORIES, array_map('__', self::CATEGORIES));
    }

    /** Give the status => category map (tests, or after the registry changed: null reloads it). */
    public static function setStatusMap(?array $map): void
    {
        self::$statusMap = $map;
    }

    /** status name => category, read once per request from the gibbonCsStatus table. */
    public static function statusMap(): array
    {
        if (self::$statusMap === null) {
            self::$statusMap = self::FALLBACK;
            try {
                $container = $GLOBALS['container'] ?? null;
                if ($container) {
                    $map = $container->get(\Gibbon\Module\CustomerService\Domain\StatusGateway::class)->selectMap();
                    if (!empty($map)) {
                        self::$statusMap = $map;
                    }
                }
            } catch (\Throwable $e) {
                error_log('CustomerService: status registry not readable: '.$e->getMessage());
            }
        }
        return self::$statusMap;
    }

    public static function category(string $status): string
    {
        return self::statusMap()[$status] ?? self::DEFAULT_CATEGORY;
    }

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

    /** Is the status in the Done category? */
    public static function isDone(string $status): bool
    {
        return self::category($status) === 'Done';
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
