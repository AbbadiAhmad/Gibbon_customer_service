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
use Gibbon\Contracts\Services\Session;
use Gibbon\Module\ServiceDesk\Domain\TicketGateway;

/**
 * The box "My open tickets" in the sidebar of Gibbon's home page.
 *
 * Requesters see their open requests (and the family's shared ones); team members and administrators see the open
 * tickets waiting for them: assigned to them, or assigned to nobody. The two most recently updated are listed.
 */
class DashboardWidget
{
    public const LIMIT = 2;

    protected $session;
    protected $ticketGateway;
    protected $access;

    /**
     * The hook runs once per dashboard tab set (for a parent: once per child); the box is added once per page.
     */
    protected static $boxAdded = false;

    public function __construct(Session $session, TicketGateway $ticketGateway, TicketAccess $access)
    {
        $this->session = $session;
        $this->ticketGateway = $ticketGateway;
        $this->access = $access;
    }

    /**
     * Called by hook_dashboard.php. Adds the sidebar box (first call only) and returns the content of the dashboard
     * tab Gibbon insists on: a marker and a script that removes that tab and its button again. If a future Gibbon
     * changes the tab markup the script finds nothing to remove, and the tab simply shows the short note below.
     */
    public function hook($page): string
    {
        if (!self::$boxAdded) {
            self::$boxAdded = true;

            $box = $this->renderBox();
            if ($box !== '') {
                $page->stylesheets->add('serviceDesk', 'modules/ServiceDesk/css/module.css');
                // Gibbon itself moves this session value into the sidebar after the dashboard is built. (Adding it with
                // $page->addSidebarExtra() as well would leave an empty second box: the 'bottom' position prints every
                // entry, also Gibbon's own empty one.)
                $this->session->set('sidebarExtra', $box);
                $this->session->set('sidebarExtraPosition', 'bottom');
            }
        }

        $url = $this->moduleURL().'/tickets.php';

        return '<div class="serviceDesk-hideTab">'.Format::link($url, __m('Open the Service Desk')).'</div>'
            ."<script>(function () {
                document.querySelectorAll('.serviceDesk-hideTab').forEach(function (marker) {
                    var panel = marker.closest('[role=tabpanel]');
                    if (!panel || !panel.parentElement || !panel.parentElement.parentElement) return;
                    var index = Array.prototype.indexOf.call(panel.parentElement.children, panel);
                    var buttons = panel.parentElement.parentElement.querySelectorAll('[role=tablist] [role=tab]');
                    if (buttons.length < 2 || !buttons[index]) return;
                    buttons[index].remove();
                    panel.remove();
                });
            })();</script>";
    }

    /**
     * The open tickets that matter to the person: [total, newest rows].
     */
    public function findTickets(): array
    {
        $gibbonPersonID = $this->session->get('gibbonPersonID');

        if (!$this->access->isAgent()) {
            return $this->query($gibbonPersonID, []);
        }

        // Agents: what is assigned to them and what nobody has picked up yet.
        list($mine, $mineRows) = $this->query($gibbonPersonID, ['assignee' => 'me']);
        list($free, $freeRows) = $this->query($gibbonPersonID, ['assignee' => 'none']);

        $rows = array_merge($mineRows, $freeRows);
        usort($rows, function ($a, $b) {
            return [$b['unread'], $b['lastUpdate'], $b['gibbonServiceDeskTicketID']] <=> [$a['unread'], $a['lastUpdate'], $a['gibbonServiceDeskTicketID']];
        });

        return [$mine + $free, array_slice($rows, 0, self::LIMIT)];
    }

    protected function query($gibbonPersonID, array $filters): array
    {
        // No order given: the gateway lists tickets with news first, then the latest update.
        $criteria = $this->ticketGateway->newQueryCriteria(false)->pageSize(self::LIMIT);
        $criteria = TicketFilters::apply($criteria, ['status' => ['Open', 'In Progress']] + $filters);
        $tickets = $this->ticketGateway->queryTickets($criteria, $gibbonPersonID, $this->access->isAdmin(), $this->access->getTeamTypeIDs());

        return [$tickets->getResultCount(), $tickets->toArray()];
    }

    /**
     * The sidebar box, or '' when the person has no open ticket.
     */
    public function renderBox(): string
    {
        list($total, $tickets) = $this->findTickets();
        if (empty($total) || empty($tickets)) {
            return '';
        }

        $url = $this->moduleURL();
        $isAgent = $this->access->isAgent();

        $output = '<div class="serviceDesk-box">';
        $output .= '<h2 class="serviceDesk-boxTitle">'.($isAgent ? __m('Open tickets for me') : __m('My open requests')).' <span class="serviceDesk-count">'.intval($total).'</span></h2>';
        $output .= '<ul>';
        foreach ($tickets as $ticket) {
            $color = preg_match('/^#[0-9a-fA-F]{6}$/', $ticket['typeColor']) ? $ticket['typeColor'] : '#9ca3af';
            $output .= '<li class="'.(!empty($ticket['unread']) ? 'serviceDesk-unread' : '').'" style="border-left-color:'.$color.'">'
                .Format::link($url.'/tickets_view.php&gibbonServiceDeskTicketID='.intval($ticket['gibbonServiceDeskTicketID']), '#'.intval($ticket['gibbonServiceDeskTicketID']).' '.htmlspecialchars($ticket['subject']))
                .(!empty($ticket['unread']) ? ' <span class="serviceDesk-new">'.__m('Updated').'</span>' : '')
                .'<br/>'.Format::small(htmlspecialchars(__m($ticket['stageName'])).' &middot; '.Format::relativeTime($ticket['lastUpdate'], false))
                .'</li>';
        }
        $output .= '</ul>';

        $links = [];
        if ($total > count($tickets)) {
            $links[] = Format::link($url.'/tickets.php&status[]=Open&status[]=In Progress', __m('All {count} open tickets', ['count' => intval($total)]));
        }
        $links[] = Format::link($url.'/tickets.php', __m('Tickets'));
        $links[] = Format::link($url.'/request_new.php', __m('New Request'));
        $output .= '<p class="serviceDesk-boxLinks">'.implode(' &middot; ', $links).'</p></div>';

        return $output;
    }

    public function moduleURL(): string
    {
        return $this->session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk';
    }
}
