<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/

use Gibbon\Forms\Prefab\DeleteForm;
use Gibbon\Module\ServiceDesk\Domain\FilterGateway;
use Gibbon\Module\ServiceDesk\Service\TicketAccess;

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/tickets_filter_delete.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs
    ->add(__m('Tickets'), 'tickets.php')
    ->add(__m('Delete Filter'));

// People delete their own filters; shared filters belong to the administrators. Anything else is "not found".
$gibbonServiceDeskFilterID = $_GET['gibbonServiceDeskFilterID'] ?? '';
$filter = $container->get(FilterGateway::class)->getByID($gibbonServiceDeskFilterID);
$own = !empty($filter) && intval($filter['gibbonPersonID']) === intval($session->get('gibbonPersonID'));
$shared = !empty($filter) && empty($filter['gibbonPersonID']);

if (!$own && !($shared && $container->get(TicketAccess::class)->isAdmin())) {
    $page->addError(__('The specified record cannot be found.'));
    return;
}

echo '<h3>'.htmlspecialchars($filter['name']).'</h3>';
if ($shared) {
    echo '<p>'.__m('This is a shared filter: it will disappear for everyone who uses it.').'</p>';
}

$form = DeleteForm::createForm($session->get('absoluteURL').'/modules/ServiceDesk/tickets_filterProcess.php');
$form->addHiddenValue('action', 'delete');
$form->addHiddenValue('gibbonServiceDeskFilterID', $gibbonServiceDeskFilterID);
echo $form->getOutput();
