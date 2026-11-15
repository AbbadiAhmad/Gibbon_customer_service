<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/

use Gibbon\Forms\Prefab\DeleteForm;
use Gibbon\Module\ServiceDesk\Domain\TicketGateway;
use Gibbon\Module\ServiceDesk\Domain\CommentGateway;
use Gibbon\Module\ServiceDesk\Service\TicketAccess;

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/tickets_comment_delete.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

$gibbonServiceDeskTicketID = $_GET['gibbonServiceDeskTicketID'] ?? '';
$gibbonServiceDeskTicketCommentID = $_GET['gibbonServiceDeskTicketCommentID'] ?? '';

$ticket = $container->get(TicketGateway::class)->getTicketByID($gibbonServiceDeskTicketID);
$comment = !empty($ticket) ? $container->get(CommentGateway::class)->getCommentByTicket($gibbonServiceDeskTicketCommentID, $gibbonServiceDeskTicketID) : [];

if (!$container->get(TicketAccess::class)->canEditComment($ticket, $comment)) {
    $page->breadcrumbs->add(__m('Tickets'), 'tickets.php');
    $page->addError(__('The specified record cannot be found.'));
    return;
}

$page->breadcrumbs
    ->add(__m('Tickets'), 'tickets.php')
    ->add('#'.intval($gibbonServiceDeskTicketID), 'tickets_view.php', ['gibbonServiceDeskTicketID' => $gibbonServiceDeskTicketID])
    ->add(__m('Delete Comment'));

echo '<blockquote>'.nl2br(htmlspecialchars($comment['comment'])).'</blockquote>';

$form = DeleteForm::createForm($session->get('absoluteURL').'/modules/ServiceDesk/tickets_comment_deleteProcess.php');
$form->addHiddenValue('gibbonServiceDeskTicketID', $gibbonServiceDeskTicketID);
$form->addHiddenValue('gibbonServiceDeskTicketCommentID', $gibbonServiceDeskTicketCommentID);
echo $form->getOutput();
