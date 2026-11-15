<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/

namespace Gibbon\Module\ServiceDesk\Service;

use Gibbon\Domain\QueryCriteria;

/**
 * The criteria of the ticket list: read from the request or from a saved filter (stored as JSON), cleaned to known
 * keys and values, and applied to the query. Saved filters never widen access: TicketGateway::queryTickets always
 * limits the result to what the person may see.
 */
class TicketFilters
{
    public const STATUSES = ['Open', 'In Progress', 'Closed'];

    /**
     * Keeps only known criteria with valid values. Empty criteria are dropped.
     */
    public static function clean(array $input): array
    {
        $criteria = [];

        $search = trim((string) ($input['search'] ?? ''));
        if ($search !== '') $criteria['search'] = mb_substr($search, 0, 60);

        $type = array_values(array_filter(array_map('intval', (array) ($input['type'] ?? []))));
        if (!empty($type)) $criteria['type'] = array_map('strval', $type);

        $status = array_values(array_intersect(self::STATUSES, (array) ($input['status'] ?? [])));
        if (!empty($status)) $criteria['status'] = $status;

        $stage = trim((string) ($input['stage'] ?? ''));
        if ($stage !== '') $criteria['stage'] = mb_substr($stage, 0, 40);

        $assignee = (string) ($input['assignee'] ?? '');
        if (in_array($assignee, ['me', 'none'], true) || intval($assignee) > 0) {
            $criteria['assignee'] = in_array($assignee, ['me', 'none'], true) ? $assignee : strval(intval($assignee));
        }

        if (($input['requester'] ?? '') === 'me') $criteria['requester'] = 'me';

        $priority = (string) ($input['priority'] ?? '');
        if (in_array($priority, TicketService::PRIORITIES, true)) $criteria['priority'] = $priority;

        return $criteria;
    }

    public static function decode($json): array
    {
        $decoded = json_decode((string) $json, true);

        return self::clean(is_array($decoded) ? $decoded : []);
    }

    public static function apply(QueryCriteria $criteria, array $values): QueryCriteria
    {
        if (!empty($values['search'])) {
            $criteria->searchBy(['gibbonServiceDeskTicket.subject', 'requester.surname', 'requester.preferredName'], $values['search']);
        }

        foreach (['type', 'status', 'stage', 'assignee', 'requester', 'priority'] as $name) {
            if (!empty($values[$name])) {
                $criteria->filterBy($name, is_array($values[$name]) ? implode(',', $values[$name]) : $values[$name]);
            }
        }

        return $criteria;
    }

    /**
     * The criteria as URL parameters, to link to a filtered ticket list.
     */
    public static function toQuery(array $values): string
    {
        return http_build_query(self::clean($values));
    }
}
