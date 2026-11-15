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
use Gibbon\Module\ServiceDesk\Domain\TicketGateway;
use Gibbon\Module\ServiceDesk\Domain\CommentGateway;
use Gibbon\Module\ServiceDesk\Service\TicketAccess;

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/tickets_comment_edit.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

$gibbonServiceDeskTicketID = $_GET['gibbonServiceDeskTicketID'] ?? '';
$gibbonServiceDeskTicketCommentID = $_GET['gibbonServiceDeskTicketCommentID'] ?? '';
$access = $container->get(TicketAccess::class);

// The ticket must be visible to the user, the comment must belong to it, and one of the two comment permissions
// must cover this comment (own / any). Everything else answers "not found".
$ticket = $container->get(TicketGateway::class)->getTicketByID($gibbonServiceDeskTicketID);
$comment = !empty($ticket) ? $container->get(CommentGateway::class)->getCommentByTicket($gibbonServiceDeskTicketCommentID, $gibbonServiceDeskTicketID) : [];
$canEdit = $access->canEditComment($ticket, $comment);
$canChangeVisibility = $access->canChangeCommentVisibility($ticket, $comment);

if (!$canEdit && !$canChangeVisibility) {
    $page->breadcrumbs->add(__m('Tickets'), 'tickets.php');
    $page->addError(__('The specified record cannot be found.'));
    return;
}

$page->breadcrumbs
    ->add(__m('Tickets'), 'tickets.php')
    ->add('#'.intval($gibbonServiceDeskTicketID), 'tickets_view.php', ['gibbonServiceDeskTicketID' => $gibbonServiceDeskTicketID])
    ->add(__m('Edit Comment'));

$form = Form::create('commentEdit', $session->get('absoluteURL').'/modules/ServiceDesk/tickets_comment_editProcess.php');
$form->setTitle(__m('Edit Comment'));
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('gibbonServiceDeskTicketID', $gibbonServiceDeskTicketID);
$form->addHiddenValue('gibbonServiceDeskTicketCommentID', $gibbonServiceDeskTicketCommentID);

$row = $form->addRow();
    $row->addLabel('comment', __m('Comment'));
    $textArea = $row->addTextArea('comment')->setRows(6)->setValue($comment['comment']);
    $canEdit ? $textArea->required() : $textArea->readonly();

if ($canChangeVisibility) {
    $row = $form->addRow();
        $row->addLabel('type', __m('Visibility'))->description(__m('Internal notes are only seen by the team.'));
        $row->addSelect('type')->fromArray(['Comment' => __m('Comment (visible to the requester)'), 'Internal Note' => __m('Internal note')])->selected($comment['type'])->required();
}

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

echo $form->getOutput();
