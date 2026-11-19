<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/

use Gibbon\Data\Validator;
use Gibbon\Module\ServiceDesk\Domain\TeamGateway;
use Gibbon\Module\ServiceDesk\Domain\StageGateway;
use Gibbon\Module\ServiceDesk\Domain\TicketGateway;
use Gibbon\Module\ServiceDesk\Domain\TicketTypeGateway;
use Gibbon\Module\ServiceDesk\Domain\SubscriberGateway;
use Gibbon\Module\ServiceDesk\Service\TicketAccess;
use Gibbon\Module\ServiceDesk\Service\TicketService;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$gibbonServiceDeskTicketID = $_POST['gibbonServiceDeskTicketID'] ?? '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk/tickets_view.php&gibbonServiceDeskTicketID='.intval($gibbonServiceDeskTicketID);

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/tickets_view.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

$access = $container->get(TicketAccess::class);
$ticket = $container->get(TicketGateway::class)->getTicketByID($gibbonServiceDeskTicketID);

if (empty($ticket) || !$access->canView($ticket)) {
    header("Location: {$URL}&return=error0");
    exit;
}

$canManage = $access->canManage($ticket);
$gibbonPersonID = $session->get('gibbonPersonID');
$ticketService = $container->get(TicketService::class);

switch ($_POST['action'] ?? '') {
    // Everyone who can see the ticket can comment; only the team can write internal notes.
    case 'comment':
        $comment = trim($_POST['comment'] ?? '');
        if ($comment === '') {
            header("Location: {$URL}&return=error1");
            exit;
        }

        $type = $canManage && ($_POST['type'] ?? '') == 'Internal Note' ? 'Internal Note' : 'Comment';
        // The requester side follows the ticket by commenting; staff do not (see TicketService::addComment).
        $ticketService->addComment($ticket, $gibbonPersonID, $comment, $type, !$canManage);
        break;

    // Stage, assignee and priority: team members and administrators.
    case 'manage':
        if (!$canManage) {
            header("Location: {$URL}&return=error0");
            exit;
        }

        // The stage must belong to the workflow of the ticket's type.
        $stage = $container->get(StageGateway::class)->getByID($_POST['gibbonServiceDeskStageID'] ?? '');
        if (empty($stage) || intval($stage['gibbonServiceDeskTicketTypeID']) !== intval($ticket['gibbonServiceDeskTicketTypeID'])) {
            header("Location: {$URL}&return=error1");
            exit;
        }

        // The assignee must be in a team that handles the ticket's type (or stay who it was).
        $gibbonPersonIDAssignee = $_POST['gibbonPersonIDAssignee'] ?? '';
        if (!empty($gibbonPersonIDAssignee) && intval($gibbonPersonIDAssignee) !== intval($ticket['gibbonPersonIDAssignee'])) {
            $members = array_map('intval', array_column($container->get(TeamGateway::class)->selectMembersByTicketType($ticket['gibbonServiceDeskTicketTypeID'])->fetchAll(), 'gibbonPersonID'));
            if (!in_array(intval($gibbonPersonIDAssignee), $members, true)) {
                header("Location: {$URL}&return=error1");
                exit;
            }
        }

        $ticketService->setPriority($ticket, $_POST['priority'] ?? '', $gibbonPersonID);
        $ticketService->assign($ticket, $gibbonPersonIDAssignee, $gibbonPersonID);

        $ticket = $container->get(TicketGateway::class)->getTicketByID($gibbonServiceDeskTicketID);
        if (!$ticketService->changeStage($ticket, $stage, $gibbonPersonID)) {
            // The stage's automation failed: the ticket stayed where it was, the reason is in the history.
            header("Location: {$URL}&return=warning1");
            exit;
        }
        break;

    // Another ticket type: team members and administrators; the target must be an active type.
    case 'move':
        if (!$canManage) {
            header("Location: {$URL}&return=error0");
            exit;
        }

        $type = $container->get(TicketTypeGateway::class)->getByID($_POST['gibbonServiceDeskTicketTypeID'] ?? '');
        if (empty($type) || !$ticketService->changeType($ticket, $type, $gibbonPersonID)) {
            header("Location: {$URL}&return=error1");
            exit;
        }

        // The person may not be in a team of the new type: then the ticket is no longer theirs to open.
        $moved = $container->get(TicketGateway::class)->getTicketByID($gibbonServiceDeskTicketID);
        if (!$access->canView($moved)) {
            header('Location: '.$session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk/tickets.php&return=success0');
            exit;
        }
        break;

    // The user's own notification choice for this ticket.
    case 'notify':
        $container->get(SubscriberGateway::class)->setSubscription(
            $gibbonServiceDeskTicketID,
            $gibbonPersonID,
            ($_POST['notifyInApp'] ?? '') == 'Y' ? 'Y' : 'N',
            ($_POST['notifyEmail'] ?? '') == 'Y' ? 'Y' : 'N'
        );
        break;

    default:
        header("Location: {$URL}&return=error1");
        exit;
}

header("Location: {$URL}&return=success0");
