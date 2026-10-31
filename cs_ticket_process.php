<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Data\Validator;
use Gibbon\Module\CustomerService\Domain\ActivityGateway;
use Gibbon\Module\CustomerService\Domain\BoardGateway;
use Gibbon\Module\CustomerService\Domain\TeamGateway;
use Gibbon\Module\CustomerService\Domain\TicketGateway;
use Gibbon\Module\CustomerService\Service\Permissions;

$_POST['address'] = '/modules/CustomerService/cs_ticket_view.php';

require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);
$action = $_POST['action'] ?? '';
$csTicketID = $_POST['csTicketID'] ?? '';
$csBoardID = $_POST['csBoardID'] ?? '';
$person = csPersonID($session);

// Where to go afterwards: the board when the move came from the board.
$back = function (string $return) use ($session, $csTicketID, $csBoardID) {
    if (!empty($csBoardID)) {
        csRedirect($session, 'cs_board_view.php', ['csBoardID' => $csBoardID], $return);
    }
    csRedirect($session, 'cs_ticket_view.php', ['csTicketID' => $csTicketID], $return);
};

if (!isActionAccessible($guid, $connection2, '/modules/CustomerService/cs_ticket_view.php')) {
    csRedirect($session, 'cs_dashboard.php', [], 'error0');
}

$tickets = $container->get(TicketGateway::class);
$activity = $container->get(ActivityGateway::class);
$isAdmin = csIsAdmin($guid, $connection2);
$ticket = $tickets->getTicket($csTicketID);

if (empty($ticket)) {
    csRedirect($session, 'cs_dashboard.php', [], 'error2');
}
if (!csCanViewTicket($container, $session, $ticket, $isAdmin)) {
    csRedirect($session, 'cs_dashboard.php', [], 'error0');
}

$can = function (string $permission) use ($container, $session, $ticket, $isAdmin) {
    return csHasTicketPermission($container, $session, $ticket, $permission, $isAdmin);
};

switch ($action) {
    case 'comment':
        $body = trim($_POST['body'] ?? '');
        $isReporter = (int)$ticket['gibbonPersonIDReporter'] === $person;
        if (!$isReporter && !$can(Permissions::COMMENT)) {
            $back('error0');
        }
        if ($body === '') {
            $back('error1');
        }
        $activity->log($csTicketID, $person, $body, 'Comment');
        $back('success0');
        break;

    case 'move':
        // Drag-free move from a board: only the status changes.
        $board = $container->get(BoardGateway::class)->getByID($csBoardID);
        $status = trim($_POST['status'] ?? '');
        if (empty($board) || !in_array($status, array_column($container->get(BoardGateway::class)->selectColumns($csBoardID), 'name'), true)) {
            $back('error1');
        }
        $matches = !empty($tickets->selectByFilter($board['filter'], $csTicketID, 1));
        $allowed = $isAdmin || $container->get(\Gibbon\Module\CustomerService\Domain\SpaceGateway::class)->hasPermission($person, $board['csSpaceID'], Permissions::TRANSITION);
        if (!$matches || !$allowed) {
            $back('error0');
        }
        if ($status !== $ticket['status']) {
            $tickets->updateTicket($csTicketID, ['status' => $status]);
            $activity->log($csTicketID, $person, sprintf(__('Status changed from %1$s to %2$s'), $ticket['status'], $status));
        }
        $back('success0');
        break;

    case 'update':
        $changes = [];
        $log = [];

        if ($can(Permissions::TRANSITION) && isset($_POST['status'])) {
            $status = trim($_POST['status']);
            if ($status !== '' && $status !== $ticket['status'] && in_array($status, csStatusOptions($container, $session, $ticket, $isAdmin), true)) {
                $changes['status'] = $status;
                $log[] = sprintf(__('Status changed from %1$s to %2$s'), $ticket['status'], $status);
            }
        }
        if ($can(Permissions::ASSIGN) && array_key_exists('gibbonPersonIDAssignee', $_POST)) {
            $assignee = (int)$_POST['gibbonPersonIDAssignee'];
            $people = csAssignableUsers($container, $session, $ticket, $isAdmin);
            if ($assignee === 0 || isset($people[$assignee])) {
                if ($assignee !== (int)$ticket['gibbonPersonIDAssignee']) {
                    $changes['gibbonPersonIDAssignee'] = $assignee ?: null;
                    $log[] = $assignee ? sprintf(__('Assigned to %1$s'), $people[$assignee]) : __('Unassigned');
                }
            }
        }
        if ($can(Permissions::EDIT_TICKET)) {
            $priority = $_POST['priority'] ?? '';
            if (in_array($priority, ['Low', 'Medium', 'High', 'Urgent'], true) && $priority !== $ticket['priority']) {
                $changes['priority'] = $priority;
                $log[] = sprintf(__('Priority changed from %1$s to %2$s'), $ticket['priority'], $priority);
            }
            $team = (int)($_POST['csTeamID'] ?? 0);
            $teams = $container->get(TeamGateway::class)->selectOptions();
            if (($team === 0 || isset($teams[$team])) && $team !== (int)$ticket['csTeamID']) {
                $changes['csTeamID'] = $team ?: null;
                $log[] = $team ? sprintf(__('Team changed to %1$s'), $teams[$team]) : __('Team removed');
            }
        }

        if (!empty($changes)) {
            $tickets->updateTicket($csTicketID, $changes);
            foreach ($log as $line) {
                $activity->log($csTicketID, $person, $line);
            }
        }
        $back('success0');
        break;

    case 'delete':
        if (!$can(Permissions::DELETE_TICKET) || ($_POST['confirm'] ?? '') !== 'Y') {
            $back('error0');
        }
        $tickets->deleteTicket($csTicketID);
        csRedirect($session, 'cs_dashboard.php', [], 'success0');
        break;

    default:
        $back('error1');
}
