<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Module\administration\Domain\ActivityGateway;
use Gibbon\Domain\DataSet;
use Gibbon\Module\administration\Domain\AttachmentGateway;
use Gibbon\Module\administration\Domain\ChecklistGateway;
use Gibbon\Module\administration\Domain\LinkGateway;
use Gibbon\Module\administration\Domain\TagGateway;
use Gibbon\Module\administration\Domain\WatcherGateway;
use Gibbon\Module\administration\Domain\FieldGateway;
use Gibbon\Module\administration\Domain\TeamGateway;
use Gibbon\Module\administration\Domain\TicketGateway;
use Gibbon\Module\administration\Service\Attachments;
use Gibbon\Module\administration\Service\CustomFields;
use Gibbon\Module\administration\Service\Permissions;
use Gibbon\Module\administration\Service\Workflow;

require_once __DIR__ . '/moduleFunctions.php';

csSanitizeRequest($container);

if (!isActionAccessible($guid, $connection2, '/modules/administration/cs_ticket_view.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$csTicketID = $_GET['csTicketID'] ?? '';
$tickets = $container->get(TicketGateway::class);
$ticket = $tickets->getTicket($csTicketID);
$isAdmin = csIsAdmin($guid, $connection2);

if (empty($ticket)) {
    $page->addError(__('The specified record cannot be found.'));
    return;
}
if (!csCanViewTicket($container, $session, $ticket, $isAdmin)) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$isReporter = (int)$ticket['gibbonPersonIDReporter'] === csPersonID($session);
$can = [];
foreach ([Permissions::COMMENT, Permissions::EDIT_TICKET, Permissions::TRANSITION, Permissions::ASSIGN, Permissions::DELETE_TICKET] as $permission) {
    $can[$permission] = csHasTicketPermission($container, $session, $ticket, $permission, $isAdmin);
}
$canInternal = csCanSeeInternal($container, $session, $ticket, $isAdmin);
$isDone = Workflow::isDone($ticket['status']);

$page->stylesheets->add('module-cs', 'modules/administration/css/module.css');
$page->breadcrumbs->add(__('My Dashboard'), 'cs_dashboard.php');
$page->breadcrumbs->add(csTicketRef($ticket['csTicketID']));

$attachmentGateway = $container->get(AttachmentGateway::class);
$attachments = $attachmentGateway->selectGroupedByTicket($ticket['csTicketID'], $canInternal);
$renderAttachments = function (array $list) use ($session) {
    return '<div class="text-sm mt-1">&#128206; '.implode(' &middot; ', array_map(function ($a) use ($session) {
        return csAttachmentLink($session, $a);
    }, $list)).'</div>';
};

// ---- Details ---------------------------------------------------------------
if (!empty($ticket['csTicketIDParent'])) {
    echo '<p class="text-gray-600">'.__('Subtask of').' '.Format::link(csURL($session, 'cs_ticket_view.php', ['csTicketID' => $ticket['csTicketIDParent']]), csTicketRef($ticket['csTicketIDParent']).' '.htmlspecialchars($ticket['parentTitle'])).'</p>';
}
echo '<h2>'.htmlspecialchars($ticket['title']).' <span class="text-gray-500 text-base">'.csTicketRef($ticket['csTicketID']).'</span></h2>';

echo '<table class="w-full mb-4"><tbody>';
$details = [
    __('Type')         => htmlspecialchars($ticket['typeName']),
    __('Status')       => '<b>'.htmlspecialchars($ticket['status']).'</b>',
    __('Priority')     => csPriorityLabel($ticket['priority']),
    __('Requested by') => htmlspecialchars(csPersonName($ticket['reporterPreferredName'], $ticket['reporterSurname'])),
    __('Assigned to')  => htmlspecialchars(csPersonName($ticket['assigneePreferredName'], $ticket['assigneeSurname']) ?: __('Unassigned')),
    __('Team')         => htmlspecialchars($ticket['teamName'] ?? ''),
    __('Raised')       => Format::dateTime($ticket['timestampCreated']),
    __('Start date')   => !empty($ticket['dateStart']) ? Format::date($ticket['dateStart']) : '',
    __('Due date')     => !empty($ticket['dateDue']) ? Format::date($ticket['dateDue']).' '.csDueBadge($ticket) : '',
];
if (!empty($ticket['timestampResolved']) && $isDone) {
    $details[__('Resolved')] = Format::dateTime($ticket['timestampResolved']);
}
foreach ($details as $label => $value) {
    echo '<tr><td class="w-1/4 py-1 text-gray-600">'.$label.'</td><td class="py-1">'.$value.'</td></tr>';
}
$typeFields = $container->get(FieldGateway::class)->selectByType($ticket['csTicketTypeID']);
foreach ($typeFields as $field) {
    // Fields kept for the agents (not on the request form) are not shown to the requester.
    if ($field['showOnForm'] == 'N' && !$canInternal) {
        continue;
    }
    $shown = CustomFields::format($field, $ticket['customData'][$field['fieldKey']] ?? null);
    echo '<tr><td class="w-1/4 py-1 text-gray-600">'.htmlspecialchars($field['label']).'</td><td class="py-1">'.nl2br(htmlspecialchars($shown)).'</td></tr>';
}
echo '</tbody></table>';

if (!empty($ticket['description'])) {
    echo '<h3>'.__('Description').'</h3><p>'.nl2br(htmlspecialchars($ticket['description'])).'</p>';
}
if (!empty($attachments[0])) {
    echo $renderAttachments($attachments[0]);
}
if ($isDone && !empty($ticket['resolution'])) {
    echo '<h3>'.__('Resolution').'</h3><p>'.nl2br(htmlspecialchars($ticket['resolution'])).'</p>';
}

// ---- Comments / History / All (tabs, like Jira) ---------------------------------------------------
$activities = $container->get(ActivityGateway::class)->selectByTicket($ticket['csTicketID'], $canInternal);
$comments = array_values(array_filter($activities, function ($a) { return $a['kind'] == 'Comment'; }));
$events = array_values(array_filter($activities, function ($a) { return $a['kind'] == 'Event'; }));

$renderComment = function (array $a) use ($renderAttachments, $attachments) {
    $who = htmlspecialchars(csPersonName($a['preferredName'], $a['surname']) ?: __('System'));
    $internal = $a['visibility'] == 'Internal';
    $html = '<div class="cs-comment'.($internal ? ' cs-internal' : '').'">';
    $html .= '<div class="cs-comment-head"><b>'.$who.'</b> &middot; '.Format::dateTimeReadable($a['timestamp']).($internal ? ' &middot; <b>'.__('Internal note').'</b>' : '').'</div>';
    $html .= nl2br(htmlspecialchars($a['body']));
    if (!empty($attachments[(int)$a['csTicketActivityID']])) {
        $html .= $renderAttachments($attachments[(int)$a['csTicketActivityID']]);
    }
    return $html.'</div>';
};
$renderEvent = function (array $a) {
    $who = htmlspecialchars(csPersonName($a['preferredName'], $a['surname']) ?: __('System'));
    return '<div class="cs-event">&bull; '.htmlspecialchars($a['body']).($a['visibility'] == 'Internal' ? ' <span class="cs-internal-tag">'.__('internal').'</span>' : '')
        .' <i>('.$who.', '.Format::dateTimeReadable($a['timestamp']).')</i></div>';
};

// Comments: oldest first, with the form to add one.
$commentsHtml = empty($comments) ? '<p class="text-gray-600">'.__('No comments yet.').'</p>' : implode('', array_map($renderComment, $comments));

if ($isReporter || $can[Permissions::COMMENT]) {
    $fileUploader = new Gibbon\FileUploader($pdo, $session);

    $form = Form::create('csComment', $session->get('absoluteURL').'/modules/administration/cs_ticket_process.php');
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('action', 'comment');
    $form->addHiddenValue('csTicketID', $ticket['csTicketID']);
    $row = $form->addRow();
        $row->addLabel('body', __('Add a comment'));
        $row->addTextArea('body')->setRows(4)->required();

    if ($can[Permissions::COMMENT]) {
        $row = $form->addRow();
            $row->addLabel('visibility', __('Visibility'))->description(__('Internal notes are only visible to the people working on the ticket, never to the requester.'));
            $row->addSelect('visibility')->fromArray(['Public' => __('Reply to the requester'), 'Internal' => __('Internal note')])->selected('Public');
    }

    $row = $form->addRow();
        $row->addLabel('attachments', __('Attachments'));
        $row->addFileUpload(Attachments::FIELD)->accepts($fileUploader->getFileExtensions())->uploadMultiple()->setMaxUpload(false);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Comment'));
    $commentsHtml .= $form->getOutput();
}

// History: every change of the ticket, newest first (status, assignee, priority, team, dates, fields, tags, links...).
if (empty($events)) {
    $historyHtml = '<p class="text-gray-600">'.__('No changes yet.').'</p>';
} else {
    $historyHtml = '<table class="cs-history"><thead><tr><th>'.__('When').'</th><th>'.__('Who').'</th><th>'.__('Change').'</th></tr></thead><tbody>';
    foreach (array_reverse($events) as $a) {
        $historyHtml .= '<tr><td>'.Format::dateTimeReadable($a['timestamp']).'</td>'
            .'<td>'.htmlspecialchars(csPersonName($a['preferredName'], $a['surname']) ?: __('System')).'</td>'
            .'<td>'.htmlspecialchars($a['body']).($a['visibility'] == 'Internal' ? ' <span class="cs-internal-tag">'.__('internal').'</span>' : '').'</td></tr>';
    }
    $historyHtml .= '</tbody></table>';
}

// All: comments and changes together, oldest first.
$allHtml = empty($activities) ? '<p class="text-gray-600">'.__('No activity yet.').'</p>' : implode('', array_map(function ($a) use ($renderComment, $renderEvent) {
    return $a['kind'] == 'Comment' ? $renderComment($a) : $renderEvent($a);
}, $activities));

echo '<div class="cs-tabs">'
    .'<input type="radio" name="csTab" id="csTabComments" checked><label for="csTabComments">'.__('Comments').' ('.count($comments).')</label>'
    .'<input type="radio" name="csTab" id="csTabHistory"><label for="csTabHistory">'.__('History').' ('.count($events).')</label>'
    .'<input type="radio" name="csTab" id="csTabAll"><label for="csTabAll">'.__('All activity').' ('.count($activities).')</label>'
    .'<div class="cs-panel" id="csPanelComments">'.$commentsHtml.'</div>'
    .'<div class="cs-panel" id="csPanelHistory">'.$historyHtml.'</div>'
    .'<div class="cs-panel" id="csPanelAll">'.$allHtml.'</div>'
    .'</div>';

// ---- Requester: confirm or reopen a finished ticket ----------------------------------
if ($isReporter && $isDone) {
    $form = Form::create('csReopen', $session->get('absoluteURL').'/modules/administration/cs_ticket_process.php');
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('action', 'reopen');
    $form->addHiddenValue('csTicketID', $ticket['csTicketID']);
    $form->setTitle($ticket['status'] == 'Closed' ? __('Not solved?') : __('Is this solved?'));
    $row = $form->addRow();
        $row->addLabel('body', __('Comment'))->description(__('Tell us what is still missing when you reopen the request.'));
        $row->addTextArea('body')->setRows(3);
    $row = $form->addRow();
        $col = $row->addColumn()->addClass('flex justify-end gap-2');
        $col->addSubmit(__('Reopen'))->setID('reopenSubmit');
    echo $form->getOutput();

    if ($ticket['status'] != 'Closed') {
        $form = Form::create('csConfirm', $session->get('absoluteURL').'/modules/administration/cs_ticket_process.php');
        $form->addHiddenValue('address', $session->get('address'));
        $form->addHiddenValue('action', 'confirm');
        $form->addHiddenValue('csTicketID', $ticket['csTicketID']);
        $row = $form->addRow();
            $row->addContent(__('Yes, it is solved: close the request.'));
            $row->addSubmit(__('Close Request'));
        echo $form->getOutput();
    }
}

// ---- Triage ----------------------------------------------------------------
if ($can[Permissions::EDIT_TICKET] || $can[Permissions::TRANSITION] || $can[Permissions::ASSIGN]) {
    $form = Form::create('csTriage', $session->get('absoluteURL').'/modules/administration/cs_ticket_process.php');
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('action', 'update');
    $form->addHiddenValue('csTicketID', $ticket['csTicketID']);
    $form->setTitle(__('Triage'));

    if ($can[Permissions::TRANSITION]) {
        $row = $form->addRow();
            $row->addLabel('status', __('Status'))->description(__('Only the moves allowed by the workflow of this ticket type are listed.'));
            $row->addSelect('status')->fromArray(csStatusOptions($container, $session, $ticket, $isAdmin))->selected($ticket['status'])->required();
    }
    if ($can[Permissions::ASSIGN]) {
        $row = $form->addRow();
            $row->addLabel('gibbonPersonIDAssignee', __('Assigned to'));
            $row->addSelect('gibbonPersonIDAssignee')->fromArray(csAssignableUsers($container, $session, $ticket, $isAdmin))
                ->selected((int)$ticket['gibbonPersonIDAssignee'])->placeholder(__('Unassigned'));
    }
    if ($can[Permissions::EDIT_TICKET]) {
        $row = $form->addRow();
            $row->addLabel('priority', __('Priority'));
            $row->addSelect('priority')->fromArray(['Low' => __('Low'), 'Medium' => __('Medium'), 'High' => __('High'), 'Urgent' => __('Urgent')])->selected($ticket['priority'])->required();

        $row = $form->addRow();
            $row->addLabel('csTeamID', __('Team'));
            $row->addSelect('csTeamID')->fromArray($container->get(TeamGateway::class)->selectOptions())->selected($ticket['csTeamID'])->placeholder();
    }
    if ($can[Permissions::EDIT_TICKET]) {
        $row = $form->addRow();
            $row->addLabel('dateStart', __('Start Date'));
            $row->addDate('dateStart')->setValue(Format::date($ticket['dateStart']));

        $row = $form->addRow();
            $row->addLabel('dateDue', __('Due Date'))->description(__('A new due date restarts the reminders.'));
            $row->addDate('dateDue')->setValue(Format::date($ticket['dateDue']));
    }
    if ($can[Permissions::TRANSITION]) {
        $row = $form->addRow();
            $row->addLabel('comment', __('Comment / Resolution Note'))
                ->description(sprintf(__('Required when moving to %1$s (the resolution note, visible to the requester) and to statuses the workflow marks as needing a comment.'), __('Done')));
            $row->addTextArea('comment')->setRows(3);
    }
    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Save'));

    echo $form->getOutput();
}


// ---- Watch -------------------------------------------------------------------------
$watchers = $container->get(WatcherGateway::class);
$watching = $watchers->isWatching($ticket['csTicketID'], csPersonID($session));
$form = Form::createBlank('csWatch', $session->get('absoluteURL').'/modules/administration/cs_ticket_extrasProcess.php');
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('action', $watching ? 'unwatch' : 'watch');
$form->addHiddenValue('csTicketID', $ticket['csTicketID']);
$form->addRow()->addSubmit($watching ? __('Stop watching') : __('Watch this ticket'));
echo $form->getOutput();

// Small POST form with one button (remove link, delete checklist...), CSRF-protected like every Gibbon form.
$miniForm = function (string $id, string $action, array $hidden, string $label) use ($session, $ticket) {
    $form = Form::createBlank($id, $session->get('absoluteURL').'/modules/administration/cs_ticket_extrasProcess.php');
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('action', $action);
    $form->addHiddenValue('csTicketID', $ticket['csTicketID']);
    foreach ($hidden as $key => $value) {
        $form->addHiddenValue($key, $value);
    }
    $form->addRow()->addSubmit($label);
    return $form->getOutput();
};
$canEdit = $can[Permissions::EDIT_TICKET];

if ($canInternal) {
    // ---- Blocked banner ------------------------------------------------------------
    $linkGateway = $container->get(LinkGateway::class);
    $blockers = $linkGateway->selectOpenBlockers($ticket['csTicketID']);
    if (!empty($blockers)) {
        echo '<div class="warning">'.__('This ticket is blocked by').' '.implode(', ', array_map(function ($b) use ($session) {
            return Format::link(csURL($session, 'cs_ticket_view.php', ['csTicketID' => $b['csTicketID']]), csTicketRef($b['csTicketID']).' ('.htmlspecialchars($b['status']).')');
        }, $blockers)).'. '.__('It cannot be resolved or closed until they are finished.').'</div>';
    }

    // ---- Subtasks ------------------------------------------------------------------
    $subtasks = $tickets->selectSubtasks($ticket['csTicketID']);
    if (!empty($subtasks) || ($canEdit && empty($ticket['csTicketIDParent']))) {
        $table = DataTable::create('csSubtasks');
        $done = count(array_filter($subtasks, function ($t) { return Workflow::isDone($t['status']); }));
        $table->setTitle(__('Subtasks').(empty($subtasks) ? '' : ' ('.$done.'/'.count($subtasks).')'));
        $table->addMetaData('blankSlate', __('No subtasks.'));
        $table->addColumn('ref', __('Reference'))->format(function ($t) use ($session) {
            return Format::link(csURL($session, 'cs_ticket_view.php', ['csTicketID' => $t['csTicketID']]), csTicketRef($t['csTicketID']));
        });
        $table->addColumn('title', __('Title'));
        $table->addColumn('status', __('Status'));
        $table->addColumn('assignee', __('Assigned to'))->format(function ($t) {
            return htmlspecialchars(csPersonName($t['assigneePreferredName'], $t['assigneeSurname']));
        });
        $table->addColumn('due', __('Due'))->format(function ($t) {
            return csDueBadge($t);
        });
        echo $table->render(new DataSet($subtasks));
    }
    if ($canEdit && empty($ticket['csTicketIDParent'])) {
        $form = Form::create('csSubtaskAdd', $session->get('absoluteURL').'/modules/administration/cs_ticket_extrasProcess.php');
        $form->addHiddenValue('address', $session->get('address'));
        $form->addHiddenValue('action', 'subtask_add');
        $form->addHiddenValue('csTicketID', $ticket['csTicketID']);
        $form->setTitle(__('Add Subtask'));
        $row = $form->addRow();
            $row->addLabel('title', __('Title'));
            $row->addTextField('title')->maxLength(200)->required();
        $row = $form->addRow();
            $row->addLabel('gibbonPersonIDAssignee', __('Assigned to'));
            $row->addSelect('gibbonPersonIDAssignee')->fromArray(csAssignableUsers($container, $session, $ticket, $isAdmin))->placeholder(__('Unassigned'));
        $row = $form->addRow();
            $row->addLabel('dateDue', __('Due Date'));
            $row->addDate('dateDue');
        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit(__('Add Subtask'));
        echo $form->getOutput();
    }

    // ---- Checklists ----------------------------------------------------------------
    $checklists = $container->get(ChecklistGateway::class)->selectByTicket($ticket['csTicketID']);
    foreach ($checklists as $list) {
        $suffix = '_'.$list['csChecklistID'];
        $items = array_column($list['items'], 'text', 'csChecklistItemID');
        $doneIDs = array_column(array_filter($list['items'], function ($i) { return $i['done'] == 'Y'; }), 'csChecklistItemID');

        $form = Form::create('csChecklist'.$list['csChecklistID'], $session->get('absoluteURL').'/modules/administration/cs_ticket_extrasProcess.php');
        $form->addHiddenValue('address', $session->get('address'));
        $form->addHiddenValue('action', 'checklist_save');
        $form->addHiddenValue('csTicketID', $ticket['csTicketID']);
        $form->addHiddenValue('csChecklistID', $list['csChecklistID']);
        $form->setTitle(sprintf(__('Checklist: %1$s'), $list['title']).' ('.count($doneIDs).'/'.count($items).')');
        if (!empty($items)) {
            $row = $form->addRow();
                $row->addLabel('done'.$suffix, __('Done'))->description(__('Tick what is finished.'));
                $row->addCheckbox('done'.$suffix)->fromArray($items)->checked($doneIDs);
        }
        if ($canEdit) {
            if (!empty($items)) {
                $row = $form->addRow();
                    $row->addLabel('remove'.$suffix, __('Remove Items'));
                    $row->addCheckbox('remove'.$suffix)->fromArray($items);
            }
            $row = $form->addRow();
                $row->addLabel('newItems'.$suffix, __('New Items'))->description(__('One per line.'));
                $row->addTextArea('newItems'.$suffix)->setRows(2);
        }
        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit(__('Save'));
        echo $form->getOutput();
        if ($canEdit) {
            echo $miniForm('csChecklistDelete'.$list['csChecklistID'], 'checklist_delete', ['csChecklistID' => $list['csChecklistID']], __('Delete this checklist'));
        }
    }
    if ($canEdit) {
        $form = Form::create('csChecklistAdd', $session->get('absoluteURL').'/modules/administration/cs_ticket_extrasProcess.php');
        $form->addHiddenValue('address', $session->get('address'));
        $form->addHiddenValue('action', 'checklist_add');
        $form->addHiddenValue('csTicketID', $ticket['csTicketID']);
        $form->setTitle(__('Add Checklist'));
        $row = $form->addRow();
            $row->addLabel('title', __('Title'));
            $row->addTextField('title')->maxLength(100)->required();
        $row = $form->addRow();
            $row->addLabel('items', __('Items'))->description(__('One per line.'));
            $row->addTextArea('items')->setRows(3);
        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit(__('Add Checklist'));
        echo $form->getOutput();
    }

    // ---- Linked tickets ------------------------------------------------------------
    $links = $linkGateway->selectForTicket($ticket['csTicketID']);
    echo '<h3>'.__('Linked Tickets').'</h3>';
    if (empty($links)) {
        echo '<p class="text-gray-600">'.__('No linked tickets.').'</p>';
    }
    $linkLabels = LinkGateway::labels();
    foreach ($links as $l) {
        echo '<div class="py-1"><b>'.$linkLabels[$l['linkType']][$l['direction']].'</b> '
            .Format::link(csURL($session, 'cs_ticket_view.php', ['csTicketID' => $l['otherID']]), csTicketRef($l['otherID']).' '.htmlspecialchars($l['otherTitle']))
            .' <span class="text-gray-600">('.htmlspecialchars($l['otherStatus']).')</span>';
        if ($canEdit) {
            echo $miniForm('csLinkRemove'.$l['csTicketLinkID'], 'link_remove', ['csTicketLinkID' => $l['csTicketLinkID']], __('Remove'));
        }
        echo '</div>';
    }
    if ($canEdit) {
        $form = Form::create('csLinkAdd', $session->get('absoluteURL').'/modules/administration/cs_ticket_extrasProcess.php');
        $form->addHiddenValue('address', $session->get('address'));
        $form->addHiddenValue('action', 'link_add');
        $form->addHiddenValue('csTicketID', $ticket['csTicketID']);
        $form->setTitle(__('Add Link'));
        $row = $form->addRow();
            $row->addLabel('linkType', __('This ticket...'));
            $row->addSelect('linkType')->fromArray(LinkGateway::formOptions())->required();
        $row = $form->addRow();
            $row->addLabel('target', __('Other Ticket'))->description(__('Reference such as CS-12.'));
            $row->addTextField('target')->maxLength(20)->required();
        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit(__('Add Link'));
        echo $form->getOutput();
    }

    // ---- Tags ----------------------------------------------------------------------
    $tagGateway = $container->get(TagGateway::class);
    $tags = $tagGateway->selectByTicket($ticket['csTicketID']);
    echo '<h3>'.__('Tags').'</h3>';
    echo empty($tags) ? '<p class="text-gray-600">'.__('No tags.').'</p>' : '<p>'.implode(' ', array_map(function ($tag) use ($session) {
        return '<span class="cs-tag">'.Format::link(csURL($session, 'cs_ticket_search.php', ['tag' => $tag]), htmlspecialchars($tag)).'</span>';
    }, $tags)).'</p>';
    if ($canEdit) {
        $form = Form::create('csTags', $session->get('absoluteURL').'/modules/administration/cs_ticket_extrasProcess.php');
        $form->addHiddenValue('address', $session->get('address'));
        $form->addHiddenValue('action', 'tags');
        $form->addHiddenValue('csTicketID', $ticket['csTicketID']);
        $row = $form->addRow();
            $row->addLabel('newTags', __('Add Tags'))->description(__('Comma separated. New tags are created as you type them.'));
            $row->addTextField('newTags')->maxLength(255);
        if (!empty($tags)) {
            $row = $form->addRow();
                $row->addLabel('removeTags', __('Remove Tags'));
                $row->addCheckbox('removeTags')->fromArray($tags);
        }
        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit(__('Save Tags'));
        echo $form->getOutput();
    }

    // ---- Watchers ------------------------------------------------------------------
    $watcherList = $watchers->selectWithNames($ticket['csTicketID']);
    echo '<h3>'.__('Watchers').'</h3>';
    if (empty($watcherList)) {
        echo '<p class="text-gray-600">'.__('Nobody is watching this ticket.').'</p>';
    }
    foreach ($watcherList as $watcherID => $watcherName) {
        echo '<div class="py-1">'.htmlspecialchars($watcherName);
        if ($canEdit) {
            echo $miniForm('csWatcherRemove'.$watcherID, 'watcher_remove', ['gibbonPersonID' => $watcherID], __('Remove'));
        }
        echo '</div>';
    }
    if ($canEdit) {
        $form = Form::create('csWatcherAdd', $session->get('absoluteURL').'/modules/administration/cs_ticket_extrasProcess.php');
        $form->addHiddenValue('address', $session->get('address'));
        $form->addHiddenValue('action', 'watcher_add');
        $form->addHiddenValue('csTicketID', $ticket['csTicketID']);
        $row = $form->addRow();
            $row->addLabel('gibbonPersonID', __('Add Watcher'))->description(__('Watchers are notified about status changes and public comments.'));
            $row->addSelectUsers('gibbonPersonID', $session->get('gibbonSchoolYearID'))->required()->placeholder();
        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit(__('Add'));
        echo $form->getOutput();
    }
}

// ---- Agents edit the custom fields (including the ones the requester never sees) ------------------------
if ($can[Permissions::EDIT_TICKET] && !empty($typeFields)) {
    $form = Form::create('csFields', $session->get('absoluteURL').'/modules/administration/cs_ticket_process.php');
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('action', 'fields');
    $form->addHiddenValue('csTicketID', $ticket['csTicketID']);
    $form->setTitle(__('Fields'));
    CustomFields::addToForm($form, $typeFields, $ticket['customData']);
    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Save Fields'));
    echo $form->getOutput();
}

// ---- Delete ----------------------------------------------------------------
if ($can[Permissions::DELETE_TICKET]) {
    $form = Form::create('csDeleteTicket', $session->get('absoluteURL').'/modules/administration/cs_ticket_process.php');
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('action', 'delete');
    $form->addHiddenValue('csTicketID', $ticket['csTicketID']);
    $form->setTitle(__('Delete Ticket'));
    $row = $form->addRow();
        $row->addLabel('confirm', __('Tick to confirm'))->description(__('This operation cannot be undone, and may lead to loss of vital data in your system. PROCEED WITH CAUTION!'));
        $row->addCheckbox('confirm')->setValue('Y')->description(__('Yes'))->required();
    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Delete'));
    echo $form->getOutput();
}
