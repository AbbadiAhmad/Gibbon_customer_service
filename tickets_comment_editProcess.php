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
use Gibbon\Module\ServiceDesk\Domain\TicketGateway;
use Gibbon\Module\ServiceDesk\Domain\CommentGateway;
use Gibbon\Module\ServiceDesk\Service\TicketAccess;
use Gibbon\Module\ServiceDesk\Service\TicketService;

require_once '../../gibbon.php';

$_POST = $container->get(Validator::class)->sanitize($_POST);

$gibbonServiceDeskTicketID = $_POST['gibbonServiceDeskTicketID'] ?? '';
$gibbonServiceDeskTicketCommentID = $_POST['gibbonServiceDeskTicketCommentID'] ?? '';
$URLTicket = $session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk/tickets_view.php&gibbonServiceDeskTicketID='.intval($gibbonServiceDeskTicketID);
$URL = $session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk/tickets_comment_edit.php&gibbonServiceDeskTicketID='.intval($gibbonServiceDeskTicketID).'&gibbonServiceDeskTicketCommentID='.intval($gibbonServiceDeskTicketCommentID);

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/tickets_comment_edit.php') == false) {
    header("Location: {$URLTicket}&return=error0");
    exit;
}

$access = $container->get(TicketAccess::class);
$ticket = $container->get(TicketGateway::class)->getTicketByID($gibbonServiceDeskTicketID);
$comment = !empty($ticket) ? $container->get(CommentGateway::class)->getCommentByTicket($gibbonServiceDeskTicketCommentID, $gibbonServiceDeskTicketID) : [];

// Each of the two changes needs its own permission for this very comment; what is not allowed is ignored.
$canEdit = $access->canEditComment($ticket, $comment);
$canChangeVisibility = $access->canChangeCommentVisibility($ticket, $comment);

if (!$canEdit && !$canChangeVisibility) {
    header("Location: {$URLTicket}&return=error0");
    exit;
}

$ticketService = $container->get(TicketService::class);
$gibbonPersonID = $session->get('gibbonPersonID');

if ($canEdit) {
    $text = trim($_POST['comment'] ?? '');
    if ($text === '') {
        header("Location: {$URL}&return=error1");
        exit;
    }
    $ticketService->updateComment($ticket, $comment, $text, $gibbonPersonID);
    $comment['comment'] = $text;
}

if ($canChangeVisibility && isset($_POST['type'])) {
    if (!in_array($_POST['type'], ['Comment', 'Internal Note'], true)) {
        header("Location: {$URL}&return=error1");
        exit;
    }
    $ticketService->setCommentVisibility($ticket, $comment, $_POST['type'], $gibbonPersonID);
}

header("Location: {$URLTicket}&return=success0");
