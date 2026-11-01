<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Data\Validator;
use Gibbon\Module\CustomerService\Domain\FieldGateway;
use Gibbon\Module\CustomerService\Domain\TicketTypeGateway;
use Gibbon\Module\CustomerService\Service\FormConfig;

$_POST['address'] = '/modules/CustomerService/cs_type_manage.php';

require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

csRequirePost($session);

$_POST = $container->get(Validator::class)->sanitize($_POST);
$csTicketTypeID = $_POST['csTicketTypeID'] ?? '';

if (!csAccess($guid, $connection2, 'cs_form_edit.php', 'Manage Ticket Types')) {
    csRedirect($session, 'cs_type_manage.php', [], 'error0');
}

$types = $container->get(TicketTypeGateway::class);
$fields = $container->get(FieldGateway::class);
if (!$types->exists($csTicketTypeID)) {
    csRedirect($session, 'cs_type_manage.php', [], 'error1');
}

$ok = $types->update($csTicketTypeID, ['formConfig' => FormConfig::fromPost($_POST)]);

// Custom fields: only fields of this type may be changed through this form.
foreach ($fields->selectByType($csTicketTypeID) as $field) {
    $id = 'f'.$field['csFieldID'];
    $ok = $fields->update($field['csFieldID'], [
        'showOnForm'     => ($_POST['fmShow_'.$id] ?? 'Y') == 'N' ? 'N' : 'Y',
        'required'       => ($_POST['fmRequired_'.$id] ?? 'N') == 'Y' ? 'Y' : 'N',
        'sequenceNumber' => (int)($_POST['fmOrder_'.$id] ?? $field['sequenceNumber']),
    ]) && $ok;
}

csRedirect($session, 'cs_form_edit.php', ['csTicketTypeID' => $csTicketTypeID], $ok ? 'success0' : 'error2');
