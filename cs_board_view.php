<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Module\administration\Domain\BoardGateway;
use Gibbon\Module\administration\Domain\LinkGateway;
use Gibbon\Module\administration\Domain\SpaceGateway;
use Gibbon\Module\administration\Domain\TagGateway;
use Gibbon\Module\administration\Domain\TicketGateway;
use Gibbon\Module\administration\Service\BoardFilter;
use Gibbon\Module\administration\Service\Permissions;
use Gibbon\Module\administration\Service\Workflow;

require_once __DIR__ . '/moduleFunctions.php';

csSanitizeRequest($container);

if (!isActionAccessible($guid, $connection2, '/modules/administration/cs_board_view.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs->add(__('Boards'));
$page->stylesheets->add('module-cs', 'modules/administration/css/module.css');

$isAdmin = csIsAdmin($guid, $connection2);
$boards = csAccessibleBoards($container, $session, $isAdmin);

if (empty($boards)) {
    $page->addMessage(__('You are not a member of any space with a board yet. Ask a space administrator to add you.'));
    return;
}

// Pick the board
$boardsByID = array_column($boards, null, 'csBoardID');
$csBoardID = $_GET['csBoardID'] ?? array_key_first($boardsByID);
if (!isset($boardsByID[$csBoardID])) {
    $page->addError(__('The specified record cannot be found.'));
    return;
}
$board = $boardsByID[$csBoardID];

echo '<div class="cs-board-picker">';
foreach ($boards as $b) {
    $label = htmlspecialchars($b['spaceName'].' / '.$b['name']);
    echo $b['csBoardID'] == $csBoardID
        ? '<b>'.$label.'</b>'
        : Format::link(csURL($session, 'cs_board_view.php', ['csBoardID' => $b['csBoardID']]), $label);
}
echo '</div>';

echo '<p class="text-gray-600">'.__('Filter').': '.htmlspecialchars(BoardFilter::describe($board['filter'])).'</p>';

// Quick create without leaving the board (needs the Quick Create action and edit_ticket in a space, like the page).
echo csQuickCreatedNotice($container, $session);
if (csAccess($guid, $connection2, 'cs_ticket_quick.php')) {
    $quickForm = csQuickCreateForm($container, $session, $isAdmin, ['csBoardID' => $csBoardID]);
    if ($quickForm !== null) {
        echo '<details class="cs-quick"><summary>+ '.__('Quick create').'</summary>'.$quickForm.'</details>';
    }
}

$columns = $container->get(BoardGateway::class)->selectColumns($csBoardID);
$tickets = $container->get(TicketGateway::class)->selectByFilter($board['filter']);
$canMove = $isAdmin || $container->get(SpaceGateway::class)->hasPermission(csPersonID($session), $board['csSpaceID'], Permissions::TRANSITION);

// Extras shown on the cards, fetched once for the whole board.
$ticketIDs = array_column($tickets, 'csTicketID');
$tagsByTicket = $container->get(TagGateway::class)->selectByTickets($ticketIDs);
$progressByTicket = $container->get(TicketGateway::class)->selectSubtaskProgress($ticketIDs);
$blockedIDs = $container->get(LinkGateway::class)->selectBlockedIDs($ticketIDs);

// Group tickets by column; tickets whose status matches no column go to an extra lane so none disappear.
$lanes = [];
$laneCategory = [];
foreach ($columns as $column) {
    $lanes[$column['name']] = [];
    $laneCategory[$column['name']] = $column['category'];
}
$unmapped = [];
foreach ($tickets as $ticket) {
    if (array_key_exists($ticket['status'], $lanes)) {
        $lanes[$ticket['status']][] = $ticket;
    } else {
        $unmapped[] = $ticket;
    }
}
if (!empty($unmapped)) {
    $lanes[__('Other statuses')] = $unmapped;
}

$columnNames = array_column($columns, 'name');

echo '<div class="cs-board">';
foreach ($lanes as $laneName => $laneTickets) {
    $category = $laneCategory[$laneName] ?? '';
    echo '<div class="cs-lane'.($category ? ' cs-cat-'.strtolower(str_replace(' ', '', $category)) : '').'"><h3>'.htmlspecialchars($laneName)
        .' <span class="cs-count">'.count($laneTickets).'</span>'
        .($category ? ' <span class="cs-category">'.htmlspecialchars(__($category)).'</span>' : '').'</h3>';

    foreach ($laneTickets as $t) {
        echo '<div class="cs-card">';
        echo '<div>'.Format::link(csURL($session, 'cs_ticket_view.php', ['csTicketID' => $t['csTicketID']]), csTicketRef($t['csTicketID']).' '.htmlspecialchars($t['title'])).'</div>';
        echo '<div class="cs-meta">'.htmlspecialchars($t['typeName']).' &middot; '.csPriorityLabel($t['priority']).'</div>';
        echo '<div class="cs-meta">'.htmlspecialchars(csPersonName($t['assigneePreferredName'], $t['assigneeSurname']) ?: __('Unassigned')).'</div>';

        $badges = [];
        if ($due = csDueBadge($t)) {
            $badges[] = $due;
        }
        if (in_array((int)$t['csTicketID'], $blockedIDs, true)) {
            $badges[] = '<span class="text-red-700">&#128274; '.__('Blocked').'</span>';
        }
        if (isset($progressByTicket[(int)$t['csTicketID']])) {
            $badges[] = sprintf(__('Subtasks %1$s/%2$s'), ...$progressByTicket[(int)$t['csTicketID']]);
        }
        if (!empty($t['csTicketIDParent'])) {
            $badges[] = sprintf(__('Subtask of %1$s'), csTicketRef($t['csTicketIDParent']));
        }
        if (!empty($badges)) {
            echo '<div class="cs-meta">'.implode(' &middot; ', $badges).'</div>';
        }
        if (!empty($tagsByTicket[(int)$t['csTicketID']])) {
            echo '<div class="cs-meta">'.implode(' ', array_map(function ($tag) {
                return '<span class="cs-tag">'.htmlspecialchars($tag).'</span>';
            }, $tagsByTicket[(int)$t['csTicketID']])).'</div>';
        }

        if ($canMove) {
            $form = Form::createBlank('move'.$t['csTicketID'], $session->get('absoluteURL').'/modules/administration/cs_ticket_process.php');
            $form->addHiddenValue('address', $session->get('address'));
            $form->addHiddenValue('action', 'move');
            $form->addHiddenValue('csTicketID', $t['csTicketID']);
            $form->addHiddenValue('csBoardID', $csBoardID);
            // Only moves the workflow of the ticket's type allows, and only to statuses that need no note
            // (Resolved / Closed are set from the ticket page where the resolution note is entered).
            $moveOptions = [];
            foreach ($columnNames as $name) {
                if ($name === $t['status'] || (Workflow::isAllowed($t['workflow'], $t['status'], $name) && !Workflow::requiresComment($t['workflow'], $name))) {
                    $moveOptions[$name] = $name;
                }
            }
            $row = $form->addRow();
            $row->addSelect('status')->fromArray($moveOptions)->selected($t['status'])->placeholder(__('Move to...'))
                ->setAttribute('onchange', 'if (this.value) this.form.submit()');
            echo $form->getOutput();
        }
        echo '</div>';
    }
    echo '</div>';
}
echo '</div>';
