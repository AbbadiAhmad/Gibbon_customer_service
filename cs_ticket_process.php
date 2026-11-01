<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Data\Validator;
use Gibbon\Services\Format;
use Gibbon\Module\CustomerService\Domain\ActivityGateway;
use Gibbon\Module\CustomerService\Domain\AttachmentGateway;
use Gibbon\Module\CustomerService\Automation\AutomationEngine;
use Gibbon\Module\CustomerService\Domain\BoardGateway;
use Gibbon\Module\CustomerService\Domain\FieldGateway;
use Gibbon\Module\CustomerService\Domain\LinkGateway;
use Gibbon\Module\CustomerService\Domain\SpaceGateway;
use Gibbon\Module\CustomerService\Domain\TeamGateway;
use Gibbon\Module\CustomerService\Domain\TicketGateway;
use Gibbon\Module\CustomerService\Service\Attachments;
use Gibbon\Module\CustomerService\Service\CustomFields;
use Gibbon\Module\CustomerService\Service\Notifier;
use Gibbon\Module\CustomerService\Service\Permissions;
use Gibbon\Module\CustomerService\Service\Workflow;

$_POST['address'] = '/modules/CustomerService/cs_ticket_view.php';

require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

csRequirePost($session);

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
$notifier = new Notifier($container);
$engine = new AutomationEngine($container);
$isAdmin = csIsAdmin($guid, $connection2);
$ticket = $tickets->getTicket($csTicketID);

if (empty($ticket)) {
    csRedirect($session, 'cs_dashboard.php', [], 'error2');
}
if (!csCanViewTicket($container, $session, $ticket, $isAdmin)) {
    csRedirect($session, 'cs_dashboard.php', [], 'error0');
}

$isReporter = (int)$ticket['gibbonPersonIDReporter'] === $person;
$can = function (string $permission) use ($container, $session, $ticket, $isAdmin) {
    return csHasTicketPermission($container, $session, $ticket, $permission, $isAdmin);
};

/**
 * Change the status, enforcing the ticket type workflow and the required comment / resolution note.
 * $enforceWorkflow is false for the requester's own confirm / reopen buttons.
 *
 * @return string|null null on success, otherwise the return code to redirect with
 */
$changeStatus = function (string $status, string $comment, bool $enforceWorkflow = true) use ($tickets, $activity, $notifier, $engine, $container, &$ticket, $person) {
    if ($status === $ticket['status']) {
        return null;
    }
    if ($enforceWorkflow) {
        if (!Workflow::isAllowed($ticket['workflow'], $ticket['status'], $status)) {
            return 'error1';
        }
        if (Workflow::requiresComment($ticket['workflow'], $status) && $comment === '') {
            return 'error1';
        }
        // Work is not finished while subtasks are open or another ticket still blocks this one.
        if (Workflow::isDone($status)
            && ($tickets->countOpenSubtasks($ticket['csTicketID']) > 0 || !empty($container->get(LinkGateway::class)->selectOpenBlockers($ticket['csTicketID'])))) {
            return 'error1';
        }
    }

    $changes = Workflow::statusChanges($ticket, $status, $comment);

    $tickets->updateTicket($ticket['csTicketID'], $changes);
    $activity->log($ticket['csTicketID'], $person, sprintf(__('Status changed from %1$s to %2$s'), $ticket['status'], $status));
    if ($comment !== '') {
        $activity->log($ticket['csTicketID'], $person, (Workflow::isDone($status) ? __('Resolution').': ' : '').$comment, 'Comment');
    }

    $from = $ticket['status'];
    $notifier->statusChanged($ticket, $status, $person);
    $ticket = array_merge($ticket, $changes);

    $engine->fire('status_changed', $ticket['csTicketID'], ['from' => $from, 'to' => $status]);
    $ticket = $tickets->getTicket($ticket['csTicketID']) ?: $ticket;

    return null;
};

switch ($action) {
    case 'comment':
        $body = trim($_POST['body'] ?? '');
        $internal = ($_POST['visibility'] ?? 'Public') === 'Internal';
        if ($internal ? !$can(Permissions::COMMENT) : (!$isReporter && !$can(Permissions::COMMENT))) {
            $back('error0');
        }
        if ($body === '') {
            $back('error1');
        }

        $activityID = $activity->log($csTicketID, $person, $body, 'Comment', $internal ? 'Internal' : 'Public');
        $fileUploader = new Gibbon\FileUploader($pdo, $session);
        $failedFiles = Attachments::save($container->get(AttachmentGateway::class), $fileUploader, $csTicketID, $activityID, $person);

        if (!$internal) {
            $notifier->commented($ticket, $person, $body);
            $engine->fire('comment_added', $csTicketID, ['byRequester' => $isReporter]);
        }
        $back($failedFiles > 0 ? 'warning1' : 'success0');
        break;

    case 'move':
        // Move from a board: only the status changes, and only to a column of that board.
        $boards = $container->get(BoardGateway::class);
        $board = $boards->getByID($csBoardID);
        $status = trim($_POST['status'] ?? '');
        if (empty($board) || !in_array($status, array_column($boards->selectColumns($csBoardID), 'name'), true)) {
            $back('error1');
        }
        $matches = !empty($tickets->selectByFilter($board['filter'], $csTicketID, 1));
        $allowed = $isAdmin || $container->get(SpaceGateway::class)->hasPermission($person, $board['csSpaceID'], Permissions::TRANSITION);
        if (!$matches || !$allowed) {
            $back('error0');
        }
        // Statuses that need a note (Resolved, Closed, ...) have to be set from the ticket page.
        $back($changeStatus($status, '') ?? 'success0');
        break;

    case 'update':
        $return = 'success0';

        if ($can(Permissions::TRANSITION) && isset($_POST['status'])) {
            $status = trim($_POST['status']);
            if ($status !== '' && $status !== $ticket['status']) {
                if (!in_array($status, csStatusOptions($container, $session, $ticket, $isAdmin), true)) {
                    $back('error1');
                }
                $result = $changeStatus($status, trim($_POST['comment'] ?? ''));
                if ($result !== null) {
                    $back($result); // nothing else is saved when the status change is refused
                }
            }
        }

        $changes = [];
        $log = [];
        if ($can(Permissions::ASSIGN) && array_key_exists('gibbonPersonIDAssignee', $_POST)) {
            $assignee = (int)$_POST['gibbonPersonIDAssignee'];
            $people = csAssignableUsers($container, $session, $ticket, $isAdmin);
            if (($assignee === 0 || isset($people[$assignee])) && $assignee !== (int)$ticket['gibbonPersonIDAssignee']) {
                $changes['gibbonPersonIDAssignee'] = $assignee ?: null;
                $log[] = $assignee ? sprintf(__('Assigned to %1$s'), $people[$assignee]) : __('Unassigned');
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

        if ($can(Permissions::EDIT_TICKET)) {
            foreach (['dateStart' => __('Start date'), 'dateDue' => __('Due date')] as $column => $label) {
                if (!array_key_exists($column, $_POST)) {
                    continue;
                }
                $date = csParseDate($_POST[$column]);
                if ($date === false) {
                    $back('error1');
                }
                if ($date !== ($ticket[$column] ?: null)) {
                    $changes[$column] = $date;
                    $log[] = $date ? sprintf(__('%1$s set to %2$s'), $label, Format::date($date)) : sprintf(__('%1$s removed'), $label);
                }
            }
            $start = $changes['dateStart'] ?? $ticket['dateStart'];
            $due = $changes['dateDue'] ?? $ticket['dateDue'];
            if (!empty($start) && !empty($due) && $due < $start) {
                $back('error1');
            }
        }

        if (!empty($changes)) {
            $tickets->updateTicket($csTicketID, $changes);
            foreach ($log as $line) {
                $activity->log($csTicketID, $person, $line);
            }
            if (array_key_exists('gibbonPersonIDAssignee', $changes)) {
                $notifier->assigned($ticket, $changes['gibbonPersonIDAssignee'], $person);
                $engine->fire('ticket_assigned', $csTicketID);
            }
        }
        $back($return);
        break;

    case 'fields':
        if (!$can(Permissions::EDIT_TICKET)) {
            $back('error0');
        }
        $fields = $container->get(FieldGateway::class)->selectByType($ticket['csTicketTypeID']);
        [$values, $missing] = CustomFields::collect($fields, $_POST);
        if (!empty($missing)) {
            $back('error1');
        }
        $changed = [];
        foreach ($fields as $field) {
            if (($ticket['customData'][$field['fieldKey']] ?? '') != $values[$field['fieldKey']]) {
                $changed[] = $field['label'];
            }
        }
        if (!empty($changed)) {
            $tickets->updateTicket($csTicketID, ['customData' => array_merge($ticket['customData'], $values)]);
            $activity->log($csTicketID, $person, sprintf(__('Fields updated: %1$s'), implode(', ', $changed)), 'Event', 'Internal');
        }
        $back('success0');
        break;

    case 'reopen':
        if (!$isReporter || !Workflow::isDone($ticket['status'])) {
            $back('error0');
        }
        $body = trim($_POST['body'] ?? '');
        $changeStatus(Workflow::REOPEN_STATUS, '', false);
        $activity->log($csTicketID, $person, __('Reopened by the requester'));
        if ($body !== '') {
            $activity->log($csTicketID, $person, $body, 'Comment');
        }
        $notifier->reopened($ticket, $person);
        $engine->fire('ticket_reopened', $csTicketID);
        $back('success0');
        break;

    case 'confirm':
        if (!$isReporter || $ticket['status'] !== 'Resolved') {
            $back('error0');
        }
        $changeStatus('Closed', '', false);
        $back('success0');
        break;

    case 'delete':
        if (!$can(Permissions::DELETE_TICKET) || ($_POST['confirm'] ?? '') !== 'Y') {
            $back('error0');
        }
        // Remove the uploaded files too (the rows go with the ticket).
        foreach ($container->get(AttachmentGateway::class)->selectGroupedByTicket($csTicketID, true) as $group) {
            foreach ($group as $attachment) {
                $file = $session->get('absolutePath').'/'.ltrim($attachment['filePath'], '/');
                if (Attachments::isSafePath($attachment['filePath']) && is_file($file)) {
                    @unlink($file);
                }
            }
        }
        $tickets->deleteTicket($csTicketID);
        csRedirect($session, 'cs_dashboard.php', [], 'success0');
        break;

    default:
        $back('error1');
}
