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
 * Dashboard hook for parents, staff and students. Its real work is the box "My open tickets" in the sidebar of the
 * home page (below the Message Wall); the box is left out when the person has no open ticket.
 *
 * Gibbon only runs a module on the home page through a dashboard tab - for parents once per child. The tab itself is
 * not wanted, so the returned content hides it; see DashboardWidget::hook().
 */
global $container, $session, $page;

if (empty($container) || empty($session) || empty($page) || !$session->has('gibbonPersonID')) {
    return '';
}

// The dashboard is not a page of this module, so Gibbon has not registered the module's classes yet.
$container->get(\Gibbon\Services\ModuleLoader::class)->registerModuleNamespace('ServiceDesk');

return $container->get(DashboardWidget::class)->hook($page);
