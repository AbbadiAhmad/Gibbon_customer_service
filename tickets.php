<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Module\ServiceDesk\Domain\TeamGateway;
use Gibbon\Module\ServiceDesk\Domain\StageGateway;
use Gibbon\Module\ServiceDesk\Domain\FilterGateway;
use Gibbon\Module\ServiceDesk\Domain\TicketGateway;
use Gibbon\Module\ServiceDesk\Domain\TicketTypeGateway;
use Gibbon\Module\ServiceDesk\Domain\ViewPreferenceGateway;
use Gibbon\Module\ServiceDesk\Service\TicketAccess;
use Gibbon\Module\ServiceDesk\Service\TicketFilters;
use Gibbon\Module\ServiceDesk\Service\TicketService;

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/tickets.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs->add(__m('Tickets'));
$page->stylesheets->add('serviceDesk', 'modules/ServiceDesk/css/module.css');

$access = $container->get(TicketAccess::class);
$isAdmin = $access->isAdmin();
$isAgent = $access->isAgent();
$gibbonPersonID = $session->get('gibbonPersonID');
$moduleURL = $session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk';

$ticketGateway = $container->get(TicketGateway::class);
$teamGateway = $container->get(TeamGateway::class);

// CRITERIA: from a saved filter, or from the filter form
$filters = $container->get(FilterGateway::class)->selectFiltersByPerson($gibbonPersonID, $isAdmin, $isAgent)->fetchAll();
$filters = array_combine(array_map('intval', array_column($filters, 'gibbonServiceDeskFilterID')), $filters);

$gibbonServiceDeskFilterID = intval($_GET['gibbonServiceDeskFilterID'] ?? 0);
$activeFilter = $filters[$gibbonServiceDeskFilterID] ?? [];
$values = !empty($activeFilter) ? TicketFilters::decode($activeFilter['criteria']) : TicketFilters::clean($_GET);

// VIEW: list or board. The board is for people who work on tickets. The last choice is remembered per person.
$viewPreferenceGateway = $container->get(ViewPreferenceGateway::class);
$view = 'List';
if ($isAgent) {
    $requested = ucfirst(strtolower($_GET['view'] ?? ''));
    if (in_array($requested, ['List', 'Board'], true)) {
        $viewPreferenceGateway->setTicketView($gibbonPersonID, $requested);
        $view = $requested;
    } else {
        $view = $viewPreferenceGateway->getTicketView($gibbonPersonID);
    }
}

// The criteria as URL parameters: to switch view and to come back to the same tickets after moving a card.
$criteriaQuery = !empty($activeFilter)
    ? 'gibbonServiceDeskFilterID='.intval($activeFilter['gibbonServiceDeskFilterID'])
    : TicketFilters::toQuery($values);

// SAVED FILTERS
echo $page->fetchFromTemplate('filters.twig.html', [
    'filters'  => $filters,
    'activeID' => $activeFilter['gibbonServiceDeskFilterID'] ?? '',
    'isCustom' => empty($activeFilter) && !empty($values),
    'url'      => $moduleURL.'/tickets.php',
    'view'     => $view,
    'isAgent'  => $isAgent,
    'viewURL'  => $isAgent ? $moduleURL.'/tickets.php'.($criteriaQuery !== '' ? '&'.$criteriaQuery : '').'&view=' : '',
]);

// FILTER FORM
$form = Form::createSearch();
$form->setClass('noIntBorder w-full');
$form->addHiddenValue('q', '/modules/ServiceDesk/tickets.php');

$row = $form->addRow();
    $row->addLabel('search', __('Search For'))->description(__m('Ticket number, subject, details, children, classes, dates, requester, assignee and comments. Several words must all be found.'));
    $row->addTextField('search')->setValue($values['search'] ?? '')->maxLength(60);

$types = [];
foreach ($container->get(TicketTypeGateway::class)->selectTypeOptions()->fetchKeyPair() as $id => $typeName) {
    $types[intval($id)] = __m($typeName);
}
$row = $form->addRow();
    $row->addLabel('type', __m('Ticket Type'));
    $row->addSelect('type')->fromArray($types)->selectMultiple()->selected($values['type'] ?? []);

$row = $form->addRow();
    $row->addLabel('status', __('Status'));
    $row->addCheckbox('status')->fromArray(array_combine(TicketFilters::STATUSES, array_map('__m', TicketFilters::STATUSES)))->checked($values['status'] ?? [])->inline();

$stages = $container->get(StageGateway::class)->selectStageNames();
$row = $form->addRow();
    $row->addLabel('stage', __m('Stage'));
    $row->addSelect('stage')->fromArray(array_combine($stages, array_map('__m', $stages)))->placeholder()->selected($values['stage'] ?? '');

if ($isAgent) {
    $assignees = ['me' => __m('Me'), 'none' => __m('Nobody (unassigned)')];
    foreach ($teamGateway->selectAllMembers()->fetchAll() as $person) {
        if ($person['gibbonPersonID'] != $gibbonPersonID) {
            $assignees[intval($person['gibbonPersonID'])] = Format::name('', $person['preferredName'], $person['surname'], 'Staff', true, true);
        }
    }
    $row = $form->addRow();
        $row->addLabel('assignee', __m('Assignee'));
        $row->addSelect('assignee')->fromArray($assignees)->placeholder()->selected($values['assignee'] ?? '');

    $row = $form->addRow();
        $row->addLabel('priority', __m('Priority'));
        $row->addSelect('priority')->fromArray(array_combine(TicketService::PRIORITIES, array_map('__m', TicketService::PRIORITIES)))->placeholder()->selected($values['priority'] ?? '');

    $row = $form->addRow();
        $row->addLabel('requester', __m('Requester'));
        $row->addSelect('requester')->fromArray(['me' => __m('Me')])->placeholder()->selected($values['requester'] ?? '');
}

$row = $form->addRow();
    $row->addSearchSubmit($session, __('Clear Filters'));

echo '<details class="serviceDesk-details" '.(empty($activeFilter) && !empty($values) && $view == 'List' ? 'open' : '').'>';
echo '<summary>'.__m('Filter tickets').'</summary>';
echo $form->getOutput();

// SAVE the current criteria as a filter
if (!empty($values) && empty($activeFilter)) {
    $form = Form::create('filterSave', $session->get('absoluteURL').'/modules/ServiceDesk/tickets_filterProcess.php');
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('action', 'save');
    $form->addHiddenValue('criteria', json_encode($values));

    $row = $form->addRow();
        $row->addLabel('name', __m('Save as Filter'))->description(__m('Keep these criteria under a name.'));
        $row->addTextField('name')->maxLength(60)->required();

    if ($isAdmin) {
        $shareWith = [
            ''           => __m('Only me'),
            'agents'     => __m('Staff working on tickets (administrators and team members)'),
            'requesters' => __m('Requesters (parents, students and staff who are in no team)'),
        ];
        foreach ($teamGateway->selectTeamOptions()->fetchKeyPair() as $gibbonServiceDeskTeamID => $teamName) {
            $shareWith[intval($gibbonServiceDeskTeamID)] = __m('Team: {team}', ['team' => $teamName]);
        }
        $row = $form->addRow();
            $row->addLabel('share', __m('Available To'))->description(__m('The audience of a shared filter. A filter never shows a person more tickets than they may see.'));
            $row->addSelect('share')->fromArray($shareWith);
    }

    $row = $form->addRow();
        $row->addSubmit(__m('Save Filter'));

    echo $form->getOutput();
}
echo '</details>';

// DELETE the saved filter being shown (own filters; shared ones for administrators)
if (!empty($activeFilter) && ($isAdmin || !empty($activeFilter['gibbonPersonID']))) {
    // A link to a confirmation page; the filter is only removed by the form there.
    echo '<p class="serviceDesk-filterDelete">'.Format::link(
        $moduleURL.'/tickets_filter_delete.php&gibbonServiceDeskFilterID='.intval($activeFilter['gibbonServiceDeskFilterID']),
        htmlspecialchars(__m('Delete filter "{name}"', ['name' => $activeFilter['name']]))
    ).'</p>';
}

// BOARD
if ($view == 'Board') {
    // How long closed tickets stay on the board, and how many cards are loaded at most.
    $boardClosedDays = 30;
    $boardMaxCards = 300;

    // TICKETS: same criteria and same access rules as the list, without paging; closed tickets only while recent.
    $criteria = $ticketGateway->newQueryCriteria(false)->pageSize($boardMaxCards);
    $criteria = TicketFilters::apply($criteria, $values)
        ->filterBy('closedSince', date('Y-m-d', strtotime("-{$boardClosedDays} days")));

    $tickets = $ticketGateway->queryTickets($criteria, $gibbonPersonID, $isAdmin, $access->getTeamTypeIDs())->toArray();

    // COLUMNS: one ticket type in the filter -> that type's workflow stages (cards can be moved);
    // otherwise the three statuses, which exist in every workflow (overview only: a status is a different stage per type).
    $singleType = count($values['type'] ?? []) == 1 ? intval($values['type'][0]) : 0;
    $columns = [];

    if (!empty($singleType)) {
        foreach ($container->get(StageGateway::class)->selectStagesByType($singleType)->fetchAll() as $stage) {
            $columns[intval($stage['gibbonServiceDeskStageID'])] = [
                'id'         => intval($stage['gibbonServiceDeskStageID']),
                'name'       => __m($stage['name']),
                'category'   => str_replace(' ', '', $stage['category']),
                'automation' => !empty($stage['automation']),
                'cards'      => [],
            ];
        }
    } else {
        foreach (TicketFilters::STATUSES as $status) {
            $columns[$status] = ['id' => '', 'name' => __m($status), 'category' => str_replace(' ', '', $status), 'automation' => false, 'cards' => []];
        }
    }

    foreach ($tickets as $ticket) {
        $key = !empty($singleType) ? intval($ticket['gibbonServiceDeskStageID']) : $ticket['stageCategory'];
        if (!isset($columns[$key])) continue;

        $columns[$key]['cards'][] = [
            'id'        => intval($ticket['gibbonServiceDeskTicketID']),
            'subject'   => $ticket['subject'],
            'type'      => __m($ticket['typeName']),
            'color'     => preg_match('/^#[0-9a-fA-F]{6}$/', $ticket['typeColor']) ? $ticket['typeColor'] : '#9ca3af',
            'stage'     => __m($ticket['stageName']),
            'requester' => Format::name('', $ticket['requesterPreferredName'], $ticket['requesterSurname'], 'Student', false),
            'assignee'  => !empty($ticket['gibbonPersonIDAssignee']) ? Format::name('', $ticket['assigneePreferredName'], $ticket['assigneeSurname'], 'Student', false) : '',
            'priority'  => $ticket['priority'],
            'updated'   => $ticket['lastUpdate'],
            'unread'    => !empty($ticket['unread']),
            // Only tickets the person works on can be moved (their own requests of other teams cannot).
            'movable'   => !empty($singleType) && $access->canManage($ticket),
        ];
    }

    echo $page->fetchFromTemplate('board.twig.html', [
        'title'      => !empty($activeFilter) ? __m($activeFilter['name']) : __m('Tickets'),
        'columns'    => $columns,
        'byStage'    => !empty($singleType),
        'ticketURL'  => $moduleURL.'/tickets_view.php&gibbonServiceDeskTicketID=',
        'newURL'     => $moduleURL.'/request_new.php',
        'closedDays' => $boardClosedDays,
        'truncated'  => count($tickets) >= $boardMaxCards,
        'confirm'    => __m('This stage runs an automation when a ticket enters it. Move the ticket?'),
    ]);

    // MOVE: dropping a card fills in and sends this form, so the change goes through the normal, CSRF-protected path.
    if (!empty($singleType)) {
        $form = Form::createBlank('boardMove', $session->get('absoluteURL').'/modules/ServiceDesk/tickets_boardProcess.php');
        $form->addHiddenValue('address', $session->get('address'));
        $form->addHiddenValue('gibbonServiceDeskTicketID', '');
        $form->addHiddenValue('gibbonServiceDeskStageID', '');
        $form->addHiddenValue('criteria', $criteriaQuery);

        echo $form->getOutput();

        $page->scripts->add('serviceDeskBoard', 'modules/ServiceDesk/js/board.js');
    }

    return;
}

// QUERY
// Without a chosen order the gateway lists tickets with news first, then the most recently updated. Clicking a
// column heading sorts by that column.
$criteria = $ticketGateway->newQueryCriteria(true);
$criteria = TicketFilters::apply($criteria, $values)->fromPOST();

$tickets = $ticketGateway->queryTickets($criteria, $gibbonPersonID, $isAdmin, $access->getTeamTypeIDs());

// TABLE
$table = DataTable::createPaginated('tickets', $criteria);
$table->setTitle(!empty($activeFilter) ? __m($activeFilter['name']) : __m('Tickets'));

$table->addHeaderAction('add', __m('New Request'))
    ->setURL('/modules/ServiceDesk/request_new.php')
    ->displayLabel();

$table->setDescription(__m('Tickets marked "Updated" have changed since you last opened them.'));

$table->modifyRows(function ($ticket, $row) {
    if (!empty($ticket['unread'])) $row->addClass('serviceDesk-unread');
    return $ticket['stageCategory'] == 'Closed' ? $row->addClass('dull') : $row;
});

$table->addColumn('gibbonServiceDeskTicketID', '#')
    ->sortable(['gibbonServiceDeskTicket.gibbonServiceDeskTicketID'])
    ->width('6%')
    ->format(function ($ticket) {
        return intval($ticket['gibbonServiceDeskTicketID']);
    });

$table->addColumn('subject', __m('Subject'))
    ->format(function ($ticket) use ($moduleURL) {
        $url = $moduleURL.'/tickets_view.php&gibbonServiceDeskTicketID='.$ticket['gibbonServiceDeskTicketID'];
        $dot = preg_match('/^#[0-9a-fA-F]{6}$/', $ticket['typeColor']) ? '<span class="serviceDesk-dot" style="background:'.$ticket['typeColor'].'"></span>' : '';
        $new = !empty($ticket['unread']) ? ' <span class="serviceDesk-new">'.__m('Updated').'</span>' : '';
        return Format::link($url, htmlspecialchars($ticket['subject'])).$new.'<br/>'.$dot.Format::small(htmlspecialchars(__m($ticket['typeName'])));
    });

if ($isAgent) {
    $table->addColumn('requester', __m('Requester'))
        ->sortable(['requester.surname', 'requester.preferredName'])
        ->format(function ($ticket) {
            return Format::name('', $ticket['requesterPreferredName'], $ticket['requesterSurname'], 'Student', false);
        });
}

$table->addColumn('stageName', __m('Stage'))
    ->format(function ($ticket) {
        $class = ['Open' => 'warning', 'In Progress' => 'message', 'Closed' => 'success'][$ticket['stageCategory']] ?? 'dull';
        return Format::tag(htmlspecialchars(__m($ticket['stageName'])), $class);
    });

$table->addColumn('assignee', __m('Assignee'))
    ->sortable(['assignee.surname', 'assignee.preferredName'])
    ->format(function ($ticket) {
        return !empty($ticket['gibbonPersonIDAssignee'])
            ? Format::name('', $ticket['assigneePreferredName'], $ticket['assigneeSurname'], 'Student', false)
            : Format::small(__m('Unassigned'));
    });

if ($isAgent) {
    $table->addColumn('priority', __m('Priority'))->translatable();
}

$table->addColumn('lastUpdate', __m('Last Update'))
    ->format(function ($ticket) {
        return Format::relativeTime($ticket['lastUpdate']).'<br/>'.Format::small(Format::dateTimeReadable($ticket['lastUpdate']));
    });

$table->addActionColumn()
    ->addParam('gibbonServiceDeskTicketID')
    ->format(function ($ticket, $actions) {
        $actions->addAction('view', __('View'))
            ->setURL('/modules/ServiceDesk/tickets_view.php');
    });

echo $table->render($tickets);
