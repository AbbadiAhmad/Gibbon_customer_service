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

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$gibbonServiceDeskTeamID = $_POST['gibbonServiceDeskTeamID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk/teams_manage.php';

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/teams_manage_delete.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

$teamGateway = $container->get(TeamGateway::class);

if (!$teamGateway->exists($gibbonServiceDeskTeamID)) {
    header("Location: {$URL}&return=error1");
    exit;
}

// Tickets are not touched: they belong to their type. Members, type links and team-only filters go with the team.
$deleted = $teamGateway->deleteTeam($gibbonServiceDeskTeamID);

header("Location: {$URL}&return=".($deleted ? 'success0' : 'error2'));
