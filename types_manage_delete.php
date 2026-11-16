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
use Gibbon\Module\ServiceDesk\Domain\TicketTypeGateway;

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/types_manage_delete.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

$gibbonServiceDeskTicketTypeID = intval($_GET['gibbonServiceDeskTicketTypeID'] ?? 0) ?: '';

if (!$container->get(TicketTypeGateway::class)->exists($gibbonServiceDeskTicketTypeID)) {
    $page->addError(__('The specified record cannot be found.'));
    return;
}

$form = DeleteForm::createForm($session->get('absoluteURL').'/modules/ServiceDesk/types_manage_deleteProcess.php');
$form->addHiddenValue('gibbonServiceDeskTicketTypeID', $gibbonServiceDeskTicketTypeID);
echo $form->getOutput();
