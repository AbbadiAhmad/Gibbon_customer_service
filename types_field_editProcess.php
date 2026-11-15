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

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$gibbonServiceDeskTicketTypeID = $_POST['gibbonServiceDeskTicketTypeID'] ?? '';
$gibbonServiceDeskFieldID = $_POST['gibbonServiceDeskFieldID'] ?? '';
$URLType = $session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk/types_manage_edit.php&gibbonServiceDeskTicketTypeID='.$gibbonServiceDeskTicketTypeID;
$URL = $session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk/types_field_edit.php&gibbonServiceDeskTicketTypeID='.$gibbonServiceDeskTicketTypeID.'&gibbonServiceDeskFieldID='.$gibbonServiceDeskFieldID;

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/types_field_edit.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

$fieldGateway = $container->get(FieldGateway::class);

// The type comes from the stored field when editing, never from the request alone.
if (!$container->get(TicketTypeGateway::class)->exists($gibbonServiceDeskTicketTypeID)) {
    header("Location: {$URL}&return=error1");
    exit;
}
if (!empty($gibbonServiceDeskFieldID)) {
    $field = $fieldGateway->getByID($gibbonServiceDeskFieldID);
    if (empty($field) || $field['gibbonServiceDeskTicketTypeID'] != $gibbonServiceDeskTicketTypeID) {
        header("Location: {$URL}&return=error1");
        exit;
    }
}

$label = trim($_POST['label'] ?? '');
$type = $_POST['type'] ?? '';

// Field name: given, or derived from the label ("First Day" -> firstDay).
$name = preg_replace('/[^A-Za-z0-9 ]/', '', trim($_POST['name'] ?? '') ?: $label);
$name = lcfirst(str_replace(' ', '', ucwords($name)));

$data = [
    'gibbonServiceDeskTicketTypeID' => $gibbonServiceDeskTicketTypeID,
    'name'           => mb_substr($name, 0, 40),
    'label'          => $label,
    'description'    => trim($_POST['description'] ?? ''),
    'type'           => $type,
    'options'        => $type == 'Select' ? implode(',', RequestFields::parseOptions($_POST['options'] ?? '')) : null,
    'required'       => ($_POST['required'] ?? 'N') == 'Y' ? 'Y' : 'N',
    'sequenceNumber' => intval($_POST['sequenceNumber'] ?? 0),
];

if ($label === '' || $data['name'] === '' || !in_array($type, RequestFields::TYPES, true) || ($type == 'Select' && empty($data['options']))) {
    header("Location: {$URL}&return=error1");
    exit;
}

if (!$fieldGateway->unique($data, ['gibbonServiceDeskTicketTypeID', 'name'], $gibbonServiceDeskFieldID)) {
    header("Location: {$URL}&return=error7");
    exit;
}

$saved = empty($gibbonServiceDeskFieldID)
    ? $fieldGateway->insert($data)
    : $fieldGateway->update($gibbonServiceDeskFieldID, $data);

header("Location: ".($saved ? "{$URLType}&return=success0" : "{$URL}&return=error2"));
