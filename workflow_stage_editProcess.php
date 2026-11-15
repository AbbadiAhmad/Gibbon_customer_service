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
use Gibbon\Module\ServiceDesk\Domain\TicketTypeGateway;
use Gibbon\Module\ServiceDesk\Service\TicketFilters;
use Gibbon\Module\ServiceDesk\Automation\AutomationRegistry;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$gibbonServiceDeskTicketTypeID = $_POST['gibbonServiceDeskTicketTypeID'] ?? '';
$gibbonServiceDeskStageID = $_POST['gibbonServiceDeskStageID'] ?? '';
$URLWorkflow = $session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk/workflow_manage.php&gibbonServiceDeskTicketTypeID='.$gibbonServiceDeskTicketTypeID;
$URL = $session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk/workflow_stage_edit.php&gibbonServiceDeskTicketTypeID='.$gibbonServiceDeskTicketTypeID.'&gibbonServiceDeskStageID='.$gibbonServiceDeskStageID;

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/workflow_stage_edit.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

$stageGateway = $container->get(StageGateway::class);

if (!empty($gibbonServiceDeskTicketTypeID) && !$container->get(TicketTypeGateway::class)->exists($gibbonServiceDeskTicketTypeID)) {
    header("Location: {$URL}&return=error1");
    exit;
}

// When editing, the stage must belong to the workflow named in the request.
$existing = [];
if (!empty($gibbonServiceDeskStageID)) {
    $existing = $stageGateway->getByID($gibbonServiceDeskStageID);
    if (empty($existing) || intval($existing['gibbonServiceDeskTicketTypeID']) !== intval($gibbonServiceDeskTicketTypeID)) {
        header("Location: {$URL}&return=error1");
        exit;
    }
}

$name = trim($_POST['name'] ?? '');
$category = $_POST['category'] ?? '';
// Only installed scripts, stored in the order of the list on the form.
$registry = $container->get(AutomationRegistry::class);
$posted = array_map('strval', (array) ($_POST['automation'] ?? []));
$automation = implode(',', array_intersect(array_keys($registry->options()), $posted));
$unknownScripts = array_diff(array_filter($posted), array_keys($registry->options()));
$nextStage = $_POST['nextStage'] ?? '';

// The automation must be an installed script; the next stage must be another stage of this workflow.
$siblings = [];
foreach ($stageGateway->selectStagesByType($gibbonServiceDeskTicketTypeID)->fetchAll() as $stage) {
    if ($stage['gibbonServiceDeskStageID'] != $gibbonServiceDeskStageID) {
        $siblings[] = $stage['name'];
    }
}

if ($name === '' || !in_array($category, TicketFilters::STATUSES, true)
    || !empty($unknownScripts)
    || (!empty($nextStage) && !in_array($nextStage, $siblings, true))) {
    header("Location: {$URL}&return=error1");
    exit;
}

if (in_array($name, $siblings, true)) {
    header("Location: {$URL}&return=error7");
    exit;
}

$data = [
    'gibbonServiceDeskTicketTypeID' => !empty($gibbonServiceDeskTicketTypeID) ? $gibbonServiceDeskTicketTypeID : null,
    'name'           => mb_substr($name, 0, 40),
    'category'       => $category,
    'sequenceNumber' => intval($_POST['sequenceNumber'] ?? 0),
    'automation'     => !empty($automation) ? $automation : null,
    'withdrawn'      => ($_POST['withdrawn'] ?? 'N') == 'Y' && $category == 'Closed' ? 'Y' : 'N',
    'nextStage'      => !empty($automation) && !empty($nextStage) ? $nextStage : null,
];

if (empty($gibbonServiceDeskStageID)) {
    $saved = $stageGateway->insert($data);
} else {
    $saved = $stageGateway->update($gibbonServiceDeskStageID, $data);
    if ($saved && $existing['name'] !== $data['name']) {
        $stageGateway->renameNextStage($gibbonServiceDeskTicketTypeID, $existing['name'], $data['name']);
    }
}

// Only one stage per workflow receives withdrawn tickets.
if ($saved && $data['withdrawn'] == 'Y') {
    $savedID = !empty($gibbonServiceDeskStageID) ? $gibbonServiceDeskStageID : $saved;
    $stageGateway->clearOtherWithdrawnStages($gibbonServiceDeskTicketTypeID, $savedID);
}

header("Location: ".($saved ? "{$URLWorkflow}&return=success0" : "{$URL}&return=error2"));
