<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/

use Gibbon\Module\ServiceDesk\Service\DashboardWidget;
use Gibbon\Module\ServiceDesk\Service\TicketAccess;

/**
 * Staff dashboard tab "Service Desk": for team members and administrators the tickets nobody has picked up yet and
 * the tickets assigned to them; for other staff their own open requests. Included by Gibbon; it returns the HTML.
 */
global $container, $session, $page;

if (empty($container) || empty($session) || !$session->has('gibbonPersonID')) {
    return '';
}

// The dashboard is not a page of this module, so Gibbon has not registered the module's classes yet.
$container->get(\Gibbon\Services\ModuleLoader::class)->registerModuleNamespace('ServiceDesk');
if (!empty($page)) {
    $page->stylesheets->add('serviceDesk', 'modules/ServiceDesk/css/module.css');
}

$widget = $container->get(DashboardWidget::class);
$access = $container->get(TicketAccess::class);
$gibbonPersonID = $session->get('gibbonPersonID');
$url = $widget->moduleURL();
$output = '';

if ($access->isAgent()) {
    list($total, $tickets) = $widget->findOpenTickets($gibbonPersonID, $access->isAdmin(), ['assignee' => 'none']);
    $output .= $widget->render(
        __m('Unassigned tickets'),
        $total,
        $tickets,
        __m('Nothing is waiting for someone to pick it up.'),
        [__m('Open the board') => $url.'/tickets.php&view=board', __m('Unassigned') => $url.'/tickets.php&assignee=none&status[]=Open&status[]=In Progress'],
        true
    );

    list($total, $tickets) = $widget->findOpenTickets($gibbonPersonID, $access->isAdmin(), ['assignee' => 'me']);
    $output .= $widget->render(
        __m('Assigned to me'),
        $total,
        $tickets,
        __m('No open ticket is assigned to you.'),
        [__m('Assigned to me') => $url.'/tickets.php&assignee=me&status[]=Open&status[]=In Progress'],
        true
    );
} else {
    list($total, $tickets) = $widget->findOpenTickets($gibbonPersonID, false);
    $output .= $widget->render(
        __m('My open requests'),
        $total,
        $tickets,
        __m('You have no open requests.'),
        [__m('New Request') => $url.'/request_new.php', __m('All my requests') => $url.'/tickets.php']
    );
}

return $output;
