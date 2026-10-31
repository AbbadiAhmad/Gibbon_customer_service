<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\CustomerService\Service;

/**
 * Turns the filter of a board (a JSON list of conditions) into a SQL WHERE fragment.
 *
 * Conditions are {field, op, value}. `field` is either a core field (see coreFields())
 * or the fieldKey of a custom field.
 *
 * Tolerance: a custom field only exists on the ticket types that define it. For a ticket
 * whose type does not have the field the value is simply "empty" - the condition does not
 * match (or, for the negative operators, matches) and nothing raises an error. Malformed
 * conditions (unknown operator, invalid key) are ignored.
 */
class BoardFilter
{
    const OPERATORS = ['equals', 'not_equals', 'contains', 'not_contains', 'empty', 'not_empty'];

    /** Core ticket fields => SQL expression (see TicketGateway for the joins). */
    public static function coreFields(): array
    {
        return [
            'type'     => 'csTicketType.name',
            'status'   => 'csTicket.status',
            'priority' => 'csTicket.priority',
            'team'     => 'csTeam.name',
            'title'    => 'csTicket.title',
            'assignee' => 'assignee.username',
            'reporter' => 'reporter.username',
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
        ];
    }

    public static function isValidKey(string $key): bool
    {
        return (bool)preg_match('/^[A-Za-z0-9_]{1,50}$/', $key);
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
            if (!isset(self::coreFields()[$field]) && !self::isValidKey($field)) {
                continue;
            }
            $clean[] = ['field' => $field, 'op' => $op, 'value' => $value];
        }

        return $clean;
    }

    /**
     * @return array [string $sql, array $params] - $sql is '1=1' for an empty filter
     */
    public static function toSql($filter): array
    {
        $core = self::coreFields();
        $parts = [];
        $params = [];

        foreach (self::decode($filter) as $i => $c) {
            if (isset($core[$c['field']])) {
                $expr = $core[$c['field']];
            } else {
                $expr = "JSON_UNQUOTE(JSON_EXTRACT(COALESCE(NULLIF(csTicket.customData,''),'{}'), :bfPath{$i}))";
                $params["bfPath{$i}"] = '$."'.$c['field'].'"';
            }
            $val = "COALESCE({$expr}, '')";
            $param = "bfValue{$i}";

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
            }
        }

        return [empty($parts) ? '1=1' : implode(' AND ', $parts), $params];
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
