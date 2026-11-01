<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Module\administration\Domain\SavedFilterGateway;
use Gibbon\Module\administration\Domain\StatusGateway;
use Gibbon\Module\administration\Domain\TicketGateway;
use Gibbon\Module\administration\Domain\TicketTypeGateway;

require_once __DIR__ . '/moduleFunctions.php';

csSanitizeRequest($container);

if (!isActionAccessible($guid, $connection2, '/modules/administration/cs_ticket_search.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs->add(__('Search Tickets'));
$page->stylesheets->add('module-cs', 'modules/administration/css/module.css');

$isAdmin = csIsAdmin($guid, $connection2);
$person = csPersonID($session);
$tickets = $container->get(TicketGateway::class);

// The filters of the current search (also what a saved filter stores).
$filters = [];
foreach (CS_SEARCH_PARAMS as $key) {
    $value = trim((string)($_GET[$key] ?? ''));
    if ($value !== '') {
        $filters[$key] = $value;
    }
}

// ---- Quick views and saved filters -------------------------------------------------
$quick = [
    __('My open requests')   => ['mine' => 'Y', 'open' => 'Y'],
    __('Assigned to me')     => ['assignee' => 'me', 'open' => 'Y'],
    __('Unassigned')         => ['assignee' => 'none', 'open' => 'Y'],
    __('Overdue')            => ['overdue' => 'Y'],
    __('Watched by me')      => ['watching' => 'Y', 'open' => 'Y'],
    __('All open tickets')   => ['open' => 'Y'],
    __('Resolved')           => ['status' => 'Resolved'],
];
echo '<div class="mb-4"><b>'.__('Quick views').':</b> ';
$links = [];
foreach ($quick as $label => $params) {
    $links[] = Format::link(csURL($session, 'cs_ticket_search.php', $params), $label);
}
echo implode(' &middot; ', $links).'</div>';

$saved = $container->get(SavedFilterGateway::class)->selectByPerson($person);
if (!empty($saved)) {
    echo '<div class="mb-4"><b>'.__('My filters').':</b> ';
    foreach ($saved as $s) {
        $params = json_decode($s['params'], true) ?: [];
        echo '<span class="mr-4">'.Format::link(csURL($session, 'cs_ticket_search.php', $params), htmlspecialchars($s['name']));

        $form = Form::createBlank('deleteFilter'.$s['csSavedFilterID'], $session->get('absoluteURL').'/modules/administration/cs_search_process.php');
        $form->addHiddenValue('address', $session->get('address'));
        $form->addHiddenValue('action', 'delete');
        $form->addHiddenValue('csSavedFilterID', $s['csSavedFilterID']);
        $form->addRow()->addSubmit(__('Delete'))->setClass('text-xs');
        echo '</span>'.$form->getOutput();
    }
    echo '</div>';
}

// ---- Search form ----------------------------------------------------------------------
$form = Form::createSearch();
$form->addHiddenValue('address', $session->get('address'));

$row = $form->addRow();
    $row->addLabel('search', __('Search For'))->description(__('Title, description or reference (e.g. CS-12)'));
    $row->addTextField('search')->setValue($filters['search'] ?? '');

$row = $form->addRow();
    $row->addLabel('status', __('Status'));
    $row->addSelect('status')->fromArray($container->get(StatusGateway::class)->selectGrouped())->selected($filters['status'] ?? '')->placeholder();

$row = $form->addRow();
    $row->addLabel('csTicketTypeID', __('Type'));
    $row->addSelect('csTicketTypeID')->fromArray($container->get(TicketTypeGateway::class)->selectOptions())->selected($filters['csTicketTypeID'] ?? '')->placeholder();

$row = $form->addRow();
    $row->addLabel('priority', __('Priority'));
    $row->addSelect('priority')->fromArray(['Low' => __('Low'), 'Medium' => __('Medium'), 'High' => __('High'), 'Urgent' => __('Urgent')])->selected($filters['priority'] ?? '')->placeholder();

$row = $form->addRow();
    $row->addLabel('assignee', __('Assigned to'));
    $row->addSelect('assignee')->fromArray(['me' => __('Me'), 'none' => __('Unassigned')])->selected($filters['assignee'] ?? '')->placeholder(__('Anyone'));

$row = $form->addRow();
    $row->addLabel('tag', __('Tag'));
    $row->addTextField('tag')->maxLength(50)->setValue($filters['tag'] ?? '');

$row = $form->addRow();
    $row->addLabel('overdue', __('Overdue only'));
    $row->addCheckbox('overdue')->setValue('Y')->checked($filters['overdue'] ?? '');

$row = $form->addRow();
    $row->addLabel('watching', __('Tickets I watch'));
    $row->addCheckbox('watching')->setValue('Y')->checked($filters['watching'] ?? '');

$row = $form->addRow();
    $row->addLabel('mine', __('Requested by me'));
    $row->addCheckbox('mine')->setValue('Y')->checked($filters['mine'] ?? '');

$row = $form->addRow();
    $row->addLabel('open', __('Open tickets only'))->description(__('Hide Resolved and Closed.'));
    $row->addCheckbox('open')->setValue('Y')->checked($filters['open'] ?? '');

$form->addRow()->addSearchSubmit($session, __('Clear Search'));
echo $form->getOutput();

// ---- Results --------------------------------------------------------------------------
$scopeBoards = $isAdmin ? null : csAccessibleBoards($container, $session, false);
$criteria = $tickets->newQueryCriteria(true)->sortBy(['gibbonCsTicket.csTicketID'], 'DESC')->fromPOST();
$results = $tickets->querySearch($criteria, $person, $scopeBoards, $filters);

$table = DataTable::createPaginated('csSearch', $criteria);
$table->setTitle(__('Tickets'));
$table->addMetaData('blankSlate', __('No tickets match.'));

$table->addColumn('ref', __('Reference'))->sortable(['gibbonCsTicket.csTicketID'])->format(function ($t) use ($session) {
    return Format::link(csURL($session, 'cs_ticket_view.php', ['csTicketID' => $t['csTicketID']]), csTicketRef($t['csTicketID']));
});
$table->addColumn('title', __('Title'))->sortable(['gibbonCsTicket.title']);
$table->addColumn('typeName', __('Type'));
$table->addColumn('status', __('Status'))->sortable(['gibbonCsTicket.status']);
$table->addColumn('priority', __('Priority'))->format(function ($t) {
    return csPriorityLabel($t['priority']);
});
$table->addColumn('reporter', __('Requested by'))->format(function ($t) {
    return htmlspecialchars(csPersonName($t['reporterPreferredName'], $t['reporterSurname']));
});
$table->addColumn('assignee', __('Assigned to'))->format(function ($t) {
    return htmlspecialchars(csPersonName($t['assigneePreferredName'], $t['assigneeSurname']));
});
$table->addColumn('dateDue', __('Due'))->sortable(['gibbonCsTicket.dateDue'])->format(function ($t) {
    return csDueBadge($t);
});
$table->addColumn('timestampCreated', __('Raised'))->sortable(['gibbonCsTicket.timestampCreated'])->format(function ($t) {
    return Format::dateTimeReadable($t['timestampCreated']);
});

echo $table->render($results);

// ---- Save the current search --------------------------------------------------------
if (!empty($filters)) {
    $form = Form::create('csSaveFilter', $session->get('absoluteURL').'/modules/administration/cs_search_process.php');
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('action', 'save');
    foreach ($filters as $key => $value) {
        $form->addHiddenValue('f_'.$key, $value);
    }
    $form->setTitle(__('Save this search'));
    $row = $form->addRow();
        $row->addLabel('name', __('Name'));
        $row->addTextField('name')->maxLength(100)->required();
    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Save'));
    echo $form->getOutput();
}
