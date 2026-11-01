<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Prefab\DeleteForm;

require_once __DIR__ . '/moduleFunctions.php';

csSanitizeRequest($container);

// Generic delete confirmation for teams, ticket types, fields, roles, spaces, boards and space members.
if (!isActionAccessible($guid, $connection2, '/modules/CustomerService/cs_delete.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$entity = $_GET['entity'] ?? '';
$id = $_GET['id'] ?? '';
$target = csDeleteTarget($container, $session, csIsAdmin($guid, $connection2), $entity, $id);

if ($target === null || empty($id)) {
    $page->addError(__('You have not specified one or more required parameters.'));
    return;
}
// The shared delete page is listed in several actions: require the one that owns this kind of record.
if (!$target['allowed'] || !csAccess($guid, $connection2, 'cs_delete.php', $target['action'])) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs->add(__('Delete'));

$form = DeleteForm::createForm($session->get('absoluteURL').'/modules/CustomerService/cs_delete_process.php');
echo $form->getOutput();
