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
$URL = $session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk/teams_manage_edit.php&gibbonServiceDeskTeamID='.$gibbonServiceDeskTeamID;

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/teams_manage_edit.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

$teamGateway = $container->get(TeamGateway::class);

$data = [
    'name'        => trim($_POST['name'] ?? ''),
    'description' => trim($_POST['description'] ?? ''),
    'active'      => ($_POST['active'] ?? 'Y') == 'N' ? 'N' : 'Y',
];

if ($data['name'] === '' || (!empty($gibbonServiceDeskTeamID) && !$teamGateway->exists($gibbonServiceDeskTeamID))) {
    header("Location: {$URL}&return=error1");
    exit;
}

if (!$teamGateway->unique($data, ['name'], $gibbonServiceDeskTeamID)) {
    header("Location: {$URL}&return=error7");
    exit;
}

if (empty($gibbonServiceDeskTeamID)) {
    $gibbonServiceDeskTeamID = $teamGateway->insert($data);
    if (empty($gibbonServiceDeskTeamID)) {
        header("Location: {$URL}&return=error2");
        exit;
    }
    $URL .= $gibbonServiceDeskTeamID;
} else {
    $teamGateway->update($gibbonServiceDeskTeamID, $data);
}

header("Location: {$URL}&return=success0");
