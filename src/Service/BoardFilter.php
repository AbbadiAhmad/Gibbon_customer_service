<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\CustomerService\Service;

/**
 * Turns a list of conditions (board filters AND automation rule conditions) into a SQL WHERE fragment
 * over the ticket query of TicketGateway.
 *
 * A condition is {field, op, value}. `field` is
 *   - a core field (coreFields()), compared as text,
 *   - a special field (specialFields()): dates, flags, tags, computed from other tables,
 *   - or the fieldKey of a custom field.
 *
 * Tolerance: a custom field only exists on the ticket types that define it. For a ticket whose type does
 * not have the field the value is simply "empty" - the condition does not match (or, for the negative
 * operators, matches) and nothing raises an error. Malformed conditions are ignored.
 *
 * To add a special field: add it to specialFields() and handle it in special().
 */
class BoardFilter
{
    const OPERATORS = ['equals', 'not_equals', 'contains', 'not_contains', 'empty', 'not_empty', 'before', 'after'];

    const DONE_SQL = Workflow::DONE_SQL;

    /** Core ticket fields => SQL expression (see TicketGateway for the joins). */
    public static function coreFields(): array
    {
        return [
            'type'     => 'gibbonCsTicketType.name',
            'status'   => 'gibbonCsTicket.status',
            'priority' => 'gibbonCsTicket.priority',
            'team'     => 'gibbonCsTeam.name',
            'title'    => 'gibbonCsTicket.title',
            'assignee' => 'assignee.username',
            'reporter' => 'reporter.username',
        ];
    }

    /** Special fields => label. */
    public static function specialFields(): array
    {
        return [
            'due'     => __('Due date'),
            'start'   => __('Start date'),
            'overdue' => __('Overdue (Y/N)'),
            'blocked' => __('Blocked by an open ticket (Y/N)'),
            'subtask' => __('Is a subtask (Y/N)'),
            'tag'     => __('Tag'),
            'category' => __('Status category (To Do / In Progress / Done)'),
        ];
    }

    public static function operatorLabels(): array
    {
        return [
            'equals'       => __('is'),
            'not_equals'   => __('is not'),
            'contains'     => __('contains'),
            'not_contains' => __('does not contain'),
            'empty'        => __('is empty / not set'),
            'not_empty'    => __('is set'),
            'before'       => __('is before'),
            'after'        => __('is after'),
        ];
    }

    public static function isValidKey(string $key): bool
    {
        return (bool)preg_match('/^[A-Za-z0-9_]{1,50}$/', $key);
    }

    /** Names that a custom field key may not use (they would be ambiguous in filters). */
    public static function isReservedKey(string $key): bool
    {
        return isset(self::coreFields()[$key]) || isset(self::specialFields()[$key]);
    }

    /** Decode a stored filter into a clean list of conditions. */
    public static function decode($json): array
    {
        $list = is_array($json) ? $json : json_decode((string)$json, true);
        if (!is_array($list)) {
            return [];
        }

        $clean = [];
        foreach ($list as $condition) {
            if (!is_array($condition)) {
                continue;
            }
            $field = trim((string)($condition['field'] ?? ''));
            $op = (string)($condition['op'] ?? 'equals');
            $value = trim((string)($condition['value'] ?? ''));
            if ($field === '' || !in_array($op, self::OPERATORS, true)) {
                continue;
            }
            if (!self::isReservedKey($field) && !self::isValidKey($field)) {
                continue;
            }
            $clean[] = ['field' => $field, 'op' => $op, 'value' => $value];
        }

        return $clean;
    }

    /**
     * @param string $prefix prepended to the parameter names, so several filters can share one query
     * @return array [string $sql, array $params] - $sql is '1=1' for an empty filter
     */
    public static function toSql($filter, string $prefix = ''): array
    {
        $core = self::coreFields();
        $special = self::specialFields();
        $parts = [];
        $params = [];

        foreach (self::decode($filter) as $i => $c) {
            $param = "{$prefix}bfValue{$i}";

            if (isset($special[$c['field']])) {
                $sql = self::special($c['field'], $c['op'], $c['value'], $param, $params);
                if ($sql !== null) {
                    $parts[] = $sql;
                }
                continue;
            }

            if (isset($core[$c['field']])) {
                $expr = $core[$c['field']];
            } else {
                $expr = "JSON_UNQUOTE(JSON_EXTRACT(COALESCE(NULLIF(gibbonCsTicket.customData,''),'{}'), :{$prefix}bfPath{$i}))";
                $params["{$prefix}bfPath{$i}"] = '$."'.$c['field'].'"';
            }
            $val = "COALESCE({$expr}, '')";

            switch ($c['op']) {
                case 'equals':
                    $parts[] = "{$val} = :{$param}";
                    $params[$param] = $c['value'];
                    break;
                case 'not_equals':
                    $parts[] = "{$val} <> :{$param}";
                    $params[$param] = $c['value'];
                    break;
                case 'contains':
                    $parts[] = "{$val} LIKE :{$param}";
                    $params[$param] = '%'.self::escapeLike($c['value']).'%';
                    break;
                case 'not_contains':
                    $parts[] = "{$val} NOT LIKE :{$param}";
                    $params[$param] = '%'.self::escapeLike($c['value']).'%';
                    break;
                case 'empty':
                    $parts[] = "{$val} IN ('', '[]', 'null')";
                    break;
                case 'not_empty':
                    $parts[] = "{$val} NOT IN ('', '[]', 'null')";
                    break;
                case 'before':
                    $parts[] = "{$val} <> '' AND {$val} < :{$param}";
                    $params[$param] = $c['value'];
                    break;
                case 'after':
                    $parts[] = "{$val} > :{$param}";
                    $params[$param] = $c['value'];
                    break;
            }
        }

        return [empty($parts) ? '1=1' : implode(' AND ', $parts), $params];
    }

    /**
     * SQL for one special field, or null when the condition cannot be evaluated (it is then ignored).
     */
    protected static function special(string $field, string $op, string $value, string $param, array &$params): ?string
    {
        switch ($field) {
            case 'overdue':
                return self::flag("(gibbonCsTicket.dateDue IS NOT NULL AND gibbonCsTicket.dateDue < CURDATE() AND gibbonCsTicket.status NOT IN ".self::DONE_SQL.")", $op, $value);

            case 'blocked':
                return self::flag("EXISTS (SELECT 1 FROM gibbonCsTicketLink AS bl JOIN gibbonCsTicket AS blocker ON (blocker.csTicketID=bl.csTicketIDFrom)
                    WHERE bl.linkType='blocks' AND bl.csTicketIDTo=gibbonCsTicket.csTicketID AND blocker.status NOT IN ".self::DONE_SQL.")", $op, $value);

            case 'subtask':
                return self::flag("gibbonCsTicket.csTicketIDParent IS NOT NULL", $op, $value);

            case 'category':
                // A status nobody classified counts as In Progress.
                $expr = "COALESCE((SELECT category FROM gibbonCsStatus WHERE gibbonCsStatus.name=gibbonCsTicket.status), '".Workflow::DEFAULT_CATEGORY."')";
                if (!in_array($op, ['equals', 'not_equals'], true) || !in_array($value, Workflow::CATEGORIES, true)) {
                    return null;
                }
                $params[$param] = $value;
                return $op == 'equals' ? "{$expr} = :{$param}" : "{$expr} <> :{$param}";

            case 'tag':
                $exists = "EXISTS (SELECT 1 FROM gibbonCsTicketTag AS tt JOIN gibbonCsTag AS tg ON (tg.csTagID=tt.csTagID) WHERE tt.csTicketID=gibbonCsTicket.csTicketID";
                switch ($op) {
                    case 'equals':
                        $params[$param] = $value;
                        return "{$exists} AND tg.name = :{$param})";
                    case 'not_equals':
                        $params[$param] = $value;
                        return "NOT {$exists} AND tg.name = :{$param})";
                    case 'contains':
                        $params[$param] = '%'.self::escapeLike($value).'%';
                        return "{$exists} AND tg.name LIKE :{$param})";
                    case 'not_contains':
                        $params[$param] = '%'.self::escapeLike($value).'%';
                        return "NOT {$exists} AND tg.name LIKE :{$param})";
                    case 'empty':
                        return "NOT {$exists})";
                    case 'not_empty':
                        return "{$exists})";
                }
                return null;

            case 'due':
            case 'start':
                $column = $field == 'due' ? 'gibbonCsTicket.dateDue' : 'gibbonCsTicket.dateStart';
                if ($op == 'empty') {
                    return "{$column} IS NULL";
                }
                if ($op == 'not_empty') {
                    return "{$column} IS NOT NULL";
                }
                $operand = self::dateOperand($value, $param, $params);
                if ($operand === null) {
                    return null;
                }
                $symbol = ['equals' => '=', 'not_equals' => '<>', 'before' => '<', 'after' => '>'][$op] ?? null;
                if ($symbol === null) {
                    return null;
                }
                // A ticket without that date never matches "is before / is after / is", but does match "is not".
                return $op == 'not_equals' ? "({$column} IS NULL OR {$column} <> {$operand})" : "({$column} IS NOT NULL AND {$column} {$symbol} {$operand})";
        }

        return null;
    }

    /** Y/N style flag: wraps $sql according to the operator / value. */
    protected static function flag(string $sql, string $op, string $value): string
    {
        $yes = in_array(strtolower($value), ['y', 'yes', '1', 'true', ''], true);
        switch ($op) {
            case 'not_equals':
            case 'not_contains':
            case 'empty':
                $want = !$yes || $op == 'empty';
                break;
            default:
                $want = $yes || $op == 'not_empty';
        }
        return $want ? "({$sql})" : "NOT ({$sql})";
    }

    /** 'today', '+3' / '-2' (days from today) or a YYYY-MM-DD date => SQL operand. */
    protected static function dateOperand(string $value, string $param, array &$params): ?string
    {
        $value = trim($value);
        if (strtolower($value) === 'today') {
            return 'CURDATE()';
        }
        if (preg_match('/^([+-])(\d{1,3})$/', $value, $m)) {
            return 'DATE_ADD(CURDATE(), INTERVAL '.($m[1] == '-' ? '-' : '').(int)$m[2].' DAY)';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $params[$param] = $value;
            return ':'.$param;
        }
        return null;
    }

    /** Human readable summary used on the board pages. */
    public static function describe($filter): string
    {
        $ops = self::operatorLabels();
        $out = [];
        foreach (self::decode($filter) as $c) {
            $text = $c['field'].' '.$ops[$c['op']];
            if (!in_array($c['op'], ['empty', 'not_empty'], true)) {
                $text .= ' "'.$c['value'].'"';
            }
            $out[] = $text;
        }

        return empty($out) ? __('All tickets') : implode(' '.__('and').' ', $out);
    }

    private static function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }
}
