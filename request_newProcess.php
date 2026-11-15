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
use Gibbon\Module\ServiceDesk\Domain\TicketTypeGateway;
use Gibbon\Module\ServiceDesk\Service\RequestFields;
use Gibbon\Module\ServiceDesk\Service\TicketService;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$gibbonServiceDeskTicketTypeID = intval($_POST['gibbonServiceDeskTicketTypeID'] ?? 0);
$URL = $session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk/request_new.php&gibbonServiceDeskTicketTypeID='.$gibbonServiceDeskTicketTypeID;

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/request_new.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

// The type must be one the user's role category may request: re-checked here, not only on the form.
$types = $container->get(TicketTypeGateway::class)->selectRequestableTypes($session->get('gibbonRoleIDCurrentCategory'))->fetchAll();
$types = array_combine(array_map('intval', array_column($types, 'gibbonServiceDeskTicketTypeID')), $types);
$type = $types[$gibbonServiceDeskTicketTypeID] ?? [];

if (empty($type)) {
    header("Location: {$URL}&return=error1");
    exit;
}

// Only the fields of the stored form are read, and every answer is validated against it.
$fields = $container->get(FieldGateway::class)->selectFieldsByType($gibbonServiceDeskTicketTypeID)->fetchAll();
list($answers, $valid) = $container->get(RequestFields::class)->collect($fields, $_POST, [
    'gibbonPersonID'     => $session->get('gibbonPersonID'),
    'roleCategory'       => $session->get('gibbonRoleIDCurrentCategory'),
    'gibbonSchoolYearID' => $session->get('gibbonSchoolYearID'),
]);

if (!$valid) {
    header("Location: {$URL}&return=error1");
    exit;
}

$subject = trim($_POST['subject'] ?? '');
if ($subject === '') {
    $people = RequestFields::firstPeopleText($answers);
    $subject = $type['name'].($people !== '' ? ': '.$people : '');
}

$gibbonServiceDeskTicketID = $container->get(TicketService::class)->create($type, $session->get('gibbonPersonID'), $session->get('gibbonSchoolYearID'), $subject, $answers);

if (empty($gibbonServiceDeskTicketID)) {
    header("Location: {$URL}&return=error2");
    exit;
}

$URL = $session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk/tickets_view.php&gibbonServiceDeskTicketID='.$gibbonServiceDeskTicketID;
header("Location: {$URL}&return=success0");
