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
use Gibbon\Module\ServiceDesk\Service\People;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$gibbonServiceDeskTeamID = $_POST['gibbonServiceDeskTeamID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk/teams_manage_edit.php&gibbonServiceDeskTeamID='.$gibbonServiceDeskTeamID;

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/teams_manage_edit.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

$teamGateway = $container->get(TeamGateway::class);

if (!$teamGateway->exists($gibbonServiceDeskTeamID)) {
    header("Location: {$URL}&return=error1");
    exit;
}

switch ($_POST['action'] ?? '') {
    case 'add':
        // Only staff can be team members.
        $staff = $container->get(People::class)->getStaffOptions();
        $people = array_intersect(array_keys($staff), (array) ($_POST['people'] ?? []));
        if (empty($people)) {
            header("Location: {$URL}&return=error1");
            exit;
        }
        foreach ($people as $gibbonPersonID) {
            $teamGateway->insertMember($gibbonServiceDeskTeamID, $gibbonPersonID);
        }
        break;

    case 'remove':
        // The member row is removed only within the team named in the request.
        $teamGateway->deleteMember($gibbonServiceDeskTeamID, $_POST['gibbonServiceDeskTeamMemberID'] ?? '');
        break;

    default:
        header("Location: {$URL}&return=error1");
        exit;
}

header("Location: {$URL}&return=success0");
