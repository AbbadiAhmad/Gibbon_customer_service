<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Data\Validator;
use Gibbon\Module\CustomerService\Domain\FieldGateway;
use Gibbon\Module\CustomerService\Domain\TicketTypeGateway;
use Gibbon\Module\CustomerService\Service\BoardFilter;
use Gibbon\Module\CustomerService\Service\CustomFields;

$_POST['address'] = '/modules/CustomerService/cs_type_manage.php';

require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);
$csTicketTypeID = $_POST['csTicketTypeID'] ?? '';
$csFieldID = $_POST['csFieldID'] ?? '';

if (!isActionAccessible($guid, $connection2, '/modules/CustomerService/cs_field_edit.php')) {
    csRedirect($session, 'cs_type_manage.php', [], 'error0');
}

$gateway = $container->get(FieldGateway::class);
if (!$container->get(TicketTypeGateway::class)->exists($csTicketTypeID)) {
    csRedirect($session, 'cs_type_manage.php', [], 'error1');
}

$editParams = ['csTicketTypeID' => $csTicketTypeID] + (empty($csFieldID) ? [] : ['csFieldID' => $csFieldID]);
$label = trim($_POST['label'] ?? '');
$fieldType = $_POST['fieldType'] ?? '';

if ($label === '' || !isset(CustomFields::TYPES[$fieldType])) {
    csRedirect($session, 'cs_field_edit.php', $editParams, 'error1');
}

$data = [
    'csTicketTypeID' => $csTicketTypeID,
    'label'          => mb_substr($label, 0, 100),
    'fieldType'      => $fieldType,
    'options'        => trim($_POST['options'] ?? ''),
    'description'    => trim($_POST['description'] ?? ''),
    'required'       => ($_POST['required'] ?? 'N') == 'Y' ? 'Y' : 'N',
    'sequenceNumber' => (int)($_POST['sequenceNumber'] ?? 0),
];

if (empty($csFieldID)) {
    $key = trim($_POST['fieldKey'] ?? '') ?: CustomFields::slug($label);
    // The key may not collide with a core field name used in board filters.
    if (!BoardFilter::isValidKey($key) || isset(BoardFilter::coreFields()[$key]) || $gateway->keyExists($csTicketTypeID, $key)) {
        csRedirect($session, 'cs_field_edit.php', $editParams, 'error7');
    }
    $data['fieldKey'] = $key;
    $ok = !empty($gateway->insert($data));
} else {
    $existing = $gateway->getByID($csFieldID);
    $ok = !empty($existing) && $existing['csTicketTypeID'] == $csTicketTypeID && $gateway->update($csFieldID, $data);
}

csRedirect($session, 'cs_type_edit.php', ['csTicketTypeID' => $csTicketTypeID], $ok ? 'success0' : 'error2');
