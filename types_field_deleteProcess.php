<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/

use Gibbon\Data\Validator;
use Gibbon\Module\ServiceDesk\Domain\FieldGateway;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$gibbonServiceDeskFieldID = $_POST['gibbonServiceDeskFieldID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk/types_manage.php';

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/types_field_delete.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

$fieldGateway = $container->get(FieldGateway::class);
$field = $fieldGateway->getByID($gibbonServiceDeskFieldID);

if (empty($field)) {
    header("Location: {$URL}&return=error1");
    exit;
}

// Tickets keep their own copy of the answers, so removing a question does not change existing tickets.
$deleted = $fieldGateway->delete($gibbonServiceDeskFieldID);

$URL = $session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk/types_manage_edit.php&gibbonServiceDeskTicketTypeID='.$field['gibbonServiceDeskTicketTypeID'];
header("Location: {$URL}&return=".($deleted ? 'success0' : 'error2'));
