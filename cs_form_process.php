<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Data\Validator;
use Gibbon\Module\administration\Domain\FieldGateway;
use Gibbon\Module\administration\Domain\FormFieldGateway;
use Gibbon\Module\administration\Domain\FormGateway;
use Gibbon\Module\administration\Domain\TicketTypeGateway;
use Gibbon\Module\administration\Service\FormAccess;
use Gibbon\Module\administration\Service\FormMapper;

$_POST['address'] = '/modules/administration/cs_form_manage.php';

require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

csRequirePost($session);

$_POST = $container->get(Validator::class)->sanitize($_POST);
$action = $_POST['action'] ?? '';
$csFormID = $_POST['csFormID'] ?? '';

if (!csAccess($guid, $connection2, 'cs_form_edit.php', 'Manage Forms')) {
    csRedirect($session, 'cs_form_manage.php', [], 'error0');
}

$forms = $container->get(FormGateway::class);
$questions = $container->get(FormFieldGateway::class);
$types = $container->get(TicketTypeGateway::class);
$editParams = empty($csFormID) ? [] : ['csFormID' => $csFormID];

// ---- Add the questions for the ticket fields the form does not cover yet -------------------------
if ($action === 'sync') {
    $form = $forms->getByID($csFormID);
    if (empty($form)) {
        csRedirect($session, 'cs_form_manage.php', [], 'error1');
    }
    $typeFields = $container->get(FieldGateway::class)->selectByType($form['csTicketTypeID']);
    $questions->addQuestions($csFormID, FormMapper::suggest($typeFields, $questions->selectByForm($csFormID), false));
    csRedirect($session, 'cs_form_edit.php', $editParams, 'success0');
}

if ($action !== 'save') {
    csRedirect($session, 'cs_form_manage.php', [], 'error1');
}

// ---- Save the form ---------------------------------------------------------------------------------
$existing = empty($csFormID) ? [] : $forms->getByID($csFormID);
if (!empty($csFormID) && empty($existing)) {
    csRedirect($session, 'cs_form_manage.php', [], 'error1');
}

$name = trim($_POST['name'] ?? '');
// The ticket type is chosen once; changing it later would orphan the questions mapped to its fields.
$typeID = empty($existing) ? (int)($_POST['csTicketTypeID'] ?? 0) : (int)$existing['csTicketTypeID'];
$slug = FormMapper::slug(trim($_POST['slug'] ?? '') ?: $name);

$roleOptions = $forms->selectRoleOptions();
$data = [
    'name'             => mb_substr($name, 0, 100),
    'csTicketTypeID'   => $typeID,
    'slug'             => $slug,
    'description'      => trim($_POST['description'] ?? '') ?: null,
    'groupName'        => mb_substr(trim($_POST['groupName'] ?? ''), 0, 60) ?: null,
    'icon'             => mb_substr(trim($_POST['icon'] ?? ''), 0, 8) ?: null,
    'audience'         => implode(',', array_intersect((array)($_POST['audience'] ?? []), array_keys(FormAccess::audienceOptions()))) ?: null,
    'roles'            => implode(',', array_filter((array)($_POST['roles'] ?? []), function ($id) use ($roleOptions) { return isset($roleOptions[(int)$id]); })) ?: null,
    'titleTemplate'    => mb_substr(trim($_POST['titleTemplate'] ?? ''), 0, 200) ?: null,
    'confirmationText' => trim($_POST['confirmationText'] ?? '') ?: null,
    'sequenceNumber'   => (int)($_POST['sequenceNumber'] ?? 0),
    'active'           => ($_POST['active'] ?? 'Y') == 'N' ? 'N' : 'Y',
];

if ($name === '' || !$types->exists($typeID)) {
    csRedirect($session, 'cs_form_edit.php', $editParams, 'error1');
}
if ($forms->slugExists($slug, (int)$csFormID)) {
    csFlash($session, __('Another form already uses this link name.'));
    csRedirect($session, 'cs_form_edit.php', $editParams, 'error7');
}
if (!$forms->unique($data, ['name'], $csFormID)) {
    csRedirect($session, 'cs_form_edit.php', $editParams, 'error7');
}

if (empty($csFormID)) {
    $csFormID = $forms->insert($data);
    if (empty($csFormID)) {
        csRedirect($session, 'cs_form_edit.php', [], 'error2');
    }
    if (($_POST['startWith'] ?? 'all') === 'all') {
        $typeFields = $container->get(FieldGateway::class)->selectByType($typeID);
        $questions->addQuestions($csFormID, FormMapper::suggest($typeFields, [], true));
    }
    csRedirect($session, 'cs_form_edit.php', ['csFormID' => $csFormID], 'success0');
}

$ok = $forms->update($csFormID, $data);
csRedirect($session, 'cs_form_edit.php', $editParams, $ok ? 'success0' : 'error2');
