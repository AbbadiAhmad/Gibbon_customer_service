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
use Gibbon\Module\ServiceDesk\Domain\StageGateway;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$gibbonServiceDeskStageID = $_POST['gibbonServiceDeskStageID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk/workflow_manage.php';

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/workflow_stage_delete.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

$stageGateway = $container->get(StageGateway::class);
$stage = $stageGateway->getByID($gibbonServiceDeskStageID);

if (empty($stage)) {
    header("Location: {$URL}&return=error1");
    exit;
}

$URL .= '&gibbonServiceDeskTicketTypeID='.$stage['gibbonServiceDeskTicketTypeID'];

// A stage that holds tickets stays; and a workflow keeps at least one stage for new tickets to start in.
if ($stageGateway->countTickets($gibbonServiceDeskStageID) > 0) {
    header("Location: {$URL}&return=error4");
    exit;
}
if (count($stageGateway->selectStagesByType($stage['gibbonServiceDeskTicketTypeID'])->fetchAll()) <= 1) {
    header("Location: {$URL}&return=error5");
    exit;
}

$deleted = $stageGateway->delete($gibbonServiceDeskStageID);
if ($deleted) {
    // Other stages no longer point to the removed one.
    $stageGateway->renameNextStage($stage['gibbonServiceDeskTicketTypeID'], $stage['name'], null);
}

header("Location: {$URL}&return=".($deleted ? 'success0' : 'error2'));
