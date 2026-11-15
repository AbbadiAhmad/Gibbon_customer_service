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
use Gibbon\Module\ServiceDesk\Domain\FilterGateway;
use Gibbon\Module\ServiceDesk\Domain\TeamGateway;
use Gibbon\Module\ServiceDesk\Service\TicketAccess;
use Gibbon\Module\ServiceDesk\Service\TicketFilters;

require_once '../../gibbon.php';

// The criteria arrive as JSON: read them before sanitising and clean them with the filter rules instead.
$criteria = TicketFilters::decode($_POST['criteria'] ?? '');
$_POST = $container->get(Validator::class)->sanitize($_POST);

$URL = $session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk/tickets.php';

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/tickets.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

$filterGateway = $container->get(FilterGateway::class);
$isAdmin = $container->get(TicketAccess::class)->isAdmin();
$gibbonPersonID = $session->get('gibbonPersonID');
$action = $_POST['action'] ?? '';

if ($action == 'save') {
    $name = trim($_POST['name'] ?? '');
    if ($name === '' || empty($criteria)) {
        header("Location: {$URL}&".TicketFilters::toQuery($criteria)."&return=error1");
        exit;
    }

    // Only administrators can share a filter, with everyone or with one team.
    $share = $isAdmin ? ($_POST['share'] ?? '') : '';
    $gibbonServiceDeskTeamID = null;
    if ($share !== '' && $share !== 'all') {
        $team = $container->get(TeamGateway::class)->getByID($share);
        if (empty($team)) {
            header("Location: {$URL}&return=error1");
            exit;
        }
        $gibbonServiceDeskTeamID = $team['gibbonServiceDeskTeamID'];
    }

    $gibbonServiceDeskFilterID = $filterGateway->insert([
        'gibbonPersonID'          => $share === '' ? $gibbonPersonID : null,
        'gibbonServiceDeskTeamID' => $gibbonServiceDeskTeamID,
        'name'                    => mb_substr($name, 0, 60),
        'criteria'                => json_encode($criteria),
        'sequenceNumber'          => 100,
    ]);

    header("Location: {$URL}".(!empty($gibbonServiceDeskFilterID) ? "&gibbonServiceDeskFilterID={$gibbonServiceDeskFilterID}&return=success0" : '&return=error2'));
    exit;
}

if ($action == 'delete') {
    // People delete their own filters; shared filters belong to the administrators.
    $filter = $filterGateway->getByID($_POST['gibbonServiceDeskFilterID'] ?? '');
    $own = !empty($filter) && intval($filter['gibbonPersonID']) === intval($gibbonPersonID);
    $shared = !empty($filter) && empty($filter['gibbonPersonID']);

    if (!$own && !($shared && $isAdmin)) {
        header("Location: {$URL}&return=error0");
        exit;
    }

    $deleted = $filterGateway->delete($filter['gibbonServiceDeskFilterID']);
    header("Location: {$URL}&return=".($deleted ? 'success0' : 'error2'));
    exit;
}

header("Location: {$URL}&return=error1");
