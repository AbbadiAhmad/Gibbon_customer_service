<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Data\Validator;
use Gibbon\Module\administration\Domain\BoardGateway;
use Gibbon\Module\administration\Domain\SpaceGateway;
use Gibbon\Module\administration\Domain\StatusGateway;
use Gibbon\Module\administration\Service\FilterForm;
use Gibbon\Module\administration\Service\Workflow;

$_POST['address'] = '/modules/administration/cs_space_manage.php';

require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

csRequirePost($session);

$_POST = $container->get(Validator::class)->sanitize($_POST);
$csBoardID = $_POST['csBoardID'] ?? '';
$csSpaceID = $_POST['csSpaceID'] ?? '';

if (!isActionAccessible($guid, $connection2, '/modules/administration/cs_board_edit.php')) {
    csRedirect($session, 'cs_space_manage.php', [], 'error0');
}

$boards = $container->get(BoardGateway::class);
$spaces = $container->get(SpaceGateway::class);
$isAdmin = csIsAdmin($guid, $connection2);

// The board's space comes from the stored board when editing, never from the request.
if (!empty($csBoardID)) {
    $existing = $boards->getByID($csBoardID);
    if (empty($existing)) {
        csRedirect($session, 'cs_space_manage.php', [], 'error1');
    }
    $csSpaceID = $existing['csSpaceID'];
}
if (!$spaces->exists($csSpaceID)) {
    csRedirect($session, 'cs_space_manage.php', [], 'error1');
}
if (!$isAdmin && !$spaces->hasPermission(csPersonID($session), $csSpaceID, 'manage_boards')) {
    csRedirect($session, 'cs_space_manage.php', [], 'error0');
}

$editParams = empty($csBoardID) ? ['csSpaceID' => $csSpaceID] : ['csBoardID' => $csBoardID];

$conditions = FilterForm::collect($_POST);

// Columns: status name + category, in the order of the rows; blank rows are skipped.
$columns = [];
for ($i = 0; $i < 12; $i++) {
    $name = trim($_POST['colName'.$i] ?? '');
    if ($name !== '') {
        $category = $_POST['colCategory'.$i] ?? '';
        $columns[] = ['name' => $name, 'category' => in_array($category, Workflow::CATEGORIES, true) ? $category : Workflow::DEFAULT_CATEGORY];
    }
}

$data = [
    'csSpaceID'   => $csSpaceID,
    'name'        => trim($_POST['name'] ?? ''),
    'description' => $_POST['description'] ?? '',
    'filter'      => empty($conditions) ? null : json_encode($conditions, JSON_UNESCAPED_UNICODE),
    'active'      => ($_POST['active'] ?? 'Y') == 'N' ? 'N' : 'Y',
];

if ($data['name'] === '' || empty($columns)) {
    csRedirect($session, 'cs_board_edit.php', $editParams, 'error1');
}

if (empty($csBoardID)) {
    $csBoardID = $boards->insert($data);
    $ok = !empty($csBoardID);
} else {
    unset($data['csSpaceID']);
    $ok = $boards->update($csBoardID, $data);
}
if (!$ok) {
    csRedirect($session, 'cs_board_edit.php', $editParams, 'error2');
}

$boards->replaceColumns($csBoardID, $columns, $container->get(StatusGateway::class));
csRedirect($session, 'cs_space_edit.php', ['csSpaceID' => $csSpaceID], 'success0');
