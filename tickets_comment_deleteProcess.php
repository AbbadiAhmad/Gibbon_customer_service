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
$URL = $session->get('absoluteURL').'/index.php?q=/modules/ServiceDesk/tickets_view.php&gibbonServiceDeskTicketID='.intval($gibbonServiceDeskTicketID);

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/tickets_comment_delete.php') == false) {
    header("Location: {$URL}&return=error0");
    exit;
}

$ticket = $container->get(TicketGateway::class)->getTicketByID($gibbonServiceDeskTicketID);
$comment = !empty($ticket) ? $container->get(CommentGateway::class)->getCommentByTicket($gibbonServiceDeskTicketCommentID, $gibbonServiceDeskTicketID) : [];

if (!$container->get(TicketAccess::class)->canEditComment($ticket, $comment)) {
    header("Location: {$URL}&return=error0");
    exit;
}

$deleted = $container->get(TicketService::class)->deleteComment($ticket, $comment, $session->get('gibbonPersonID'));

header("Location: {$URL}&return=".($deleted ? 'success0' : 'error2'));
