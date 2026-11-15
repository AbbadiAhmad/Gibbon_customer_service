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

/**
 * Parental dashboard tab "Service Desk": the parent's open requests (and those of the other adults of the family for
 * services shared with the family), and a link to send a new one. Included by Gibbon; it returns the HTML.
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
list($total, $tickets) = $widget->findOpenTickets($session->get('gibbonPersonID'), false);
$url = $widget->moduleURL();

return $widget->render(
    __m('My open requests'),
    $total,
    $tickets,
    __m('You have no open requests.'),
    [__m('New Request') => $url.'/request_new.php', __m('All my requests') => $url.'/tickets.php']
);
