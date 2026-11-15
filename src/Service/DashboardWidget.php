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

use Gibbon\Services\Format;
use Gibbon\Module\ServiceDesk\Domain\TicketGateway;

/**
 * The small ticket lists on the Gibbon dashboards: the hook files only choose which tickets to show.
 */
class DashboardWidget
{
    protected $ticketGateway;

    public function __construct(TicketGateway $ticketGateway)
    {
        $this->ticketGateway = $ticketGateway;
    }

    /**
     * The newest open tickets for a person, with the total, limited to $limit.
     *
     * @param array $filters TicketFilters criteria, e.g. ['assignee' => 'none'].
     * @return array [total, rows]
     */
    public function findOpenTickets($gibbonPersonID, bool $isAdmin, array $filters = [], int $limit = 5): array
    {
        $criteria = $this->ticketGateway->newQueryCriteria(false)->sortBy('timestampModified', 'DESC')->pageSize($limit);
        $criteria = TicketFilters::apply($criteria, ['status' => ['Open', 'In Progress']] + $filters);
        $tickets = $this->ticketGateway->queryTickets($criteria, $gibbonPersonID, $isAdmin);

        return [$tickets->getResultCount(), $tickets->toArray()];
    }

    /**
     * One block: a heading with the total, the tickets, and links.
     *
     * @param array $links [label => url]
     */
    public function render(string $heading, int $total, array $tickets, string $emptyText, array $links, bool $showRequester = false): string
    {
        $url = $this->moduleURL().'/tickets_view.php&gibbonServiceDeskTicketID=';
        $output = '<div class="serviceDesk-widget">';
        $output .= '<h4>'.htmlspecialchars($heading).' <span class="serviceDesk-count">'.intval($total).'</span></h4>';

        if (empty($tickets)) {
            $output .= '<p class="serviceDesk-widgetEmpty">'.htmlspecialchars($emptyText).'</p>';
        } else {
            $output .= '<ul>';
            foreach ($tickets as $ticket) {
                $color = preg_match('/^#[0-9a-fA-F]{6}$/', $ticket['typeColor']) ? $ticket['typeColor'] : '#9ca3af';
                $output .= '<li style="border-left-color:'.$color.'">'
                    .Format::link($url.intval($ticket['gibbonServiceDeskTicketID']), '#'.intval($ticket['gibbonServiceDeskTicketID']).' '.htmlspecialchars($ticket['subject']))
                    .'<br/>'.Format::small(htmlspecialchars(__m($ticket['stageName']))
                        .($showRequester ? ' &middot; '.htmlspecialchars(Format::name('', $ticket['requesterPreferredName'], $ticket['requesterSurname'], 'Student', false)) : '')
                        .' &middot; '.Format::relativeTime($ticket['timestampModified'], false))
                    .'</li>';
            }
            $output .= '</ul>';
        }

        $items = [];
        foreach ($links as $label => $href) {
            $items[] = Format::link($href, htmlspecialchars($label));
        }
        $output .= '<p class="serviceDesk-widgetLinks">'.implode(' &middot; ', $items).'</p></div>';

        return $output;
    }

    public function moduleURL(): string
    {
        global $session;

        return $session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk';
    }
}
