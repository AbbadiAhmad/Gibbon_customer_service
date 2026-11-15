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
use Gibbon\Module\ServiceDesk\Domain\TeamGateway;
use Gibbon\Module\ServiceDesk\Domain\StageGateway;
use Gibbon\Module\ServiceDesk\Domain\TicketTypeGateway;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$gibbonServiceDeskTicketTypeID = $_POST['gibbonServiceDeskTicketTypeID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk/types_manage_edit.php&gibbonServiceDeskTicketTypeID='.$gibbonServiceDeskTicketTypeID;

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/types_manage_edit.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

$ticketTypeGateway = $container->get(TicketTypeGateway::class);

$data = [
    'name'           => trim($_POST['name'] ?? ''),
    'description'    => trim($_POST['description'] ?? ''),
    'roleCategories' => implode(',', array_intersect(['Staff', 'Student', 'Parent', 'Other'], (array) ($_POST['roleCategories'] ?? []))),
    'color'          => preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '',
    'sequenceNumber' => intval($_POST['sequenceNumber'] ?? 0),
    'active'         => ($_POST['active'] ?? 'Y') == 'N' ? 'N' : 'Y',
];

if ($data['name'] === '') {
    header("Location: {$URL}&return=error1");
    exit;
}

if (!empty($gibbonServiceDeskTicketTypeID) && !$ticketTypeGateway->exists($gibbonServiceDeskTicketTypeID)) {
    header("Location: {$URL}&return=error1");
    exit;
}

if (!$ticketTypeGateway->unique($data, ['name'], $gibbonServiceDeskTicketTypeID)) {
    header("Location: {$URL}&return=error7");
    exit;
}

if (empty($gibbonServiceDeskTicketTypeID)) {
    $gibbonServiceDeskTicketTypeID = $ticketTypeGateway->insert($data);
    if (empty($gibbonServiceDeskTicketTypeID)) {
        header("Location: {$URL}&return=error2");
        exit;
    }

    // Every new ticket type starts with its own copy of the default workflow.
    $container->get(StageGateway::class)->copyDefaultWorkflow($gibbonServiceDeskTicketTypeID);
    $URL .= $gibbonServiceDeskTicketTypeID;
} else {
    $ticketTypeGateway->update($gibbonServiceDeskTicketTypeID, $data);
}

// Only existing teams can be linked.
$teams = array_keys($container->get(TeamGateway::class)->selectTeamOptions()->fetchKeyPair());
$ticketTypeGateway->updateTeams($gibbonServiceDeskTicketTypeID, array_intersect($teams, (array) ($_POST['teams'] ?? [])));

header("Location: {$URL}&return=success0");
