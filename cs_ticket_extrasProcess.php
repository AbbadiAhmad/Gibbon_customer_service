<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Data\Validator;
use Gibbon\Module\CustomerService\Automation\AutomationEngine;
use Gibbon\Module\CustomerService\Domain\ActivityGateway;
use Gibbon\Module\CustomerService\Domain\ChecklistGateway;
use Gibbon\Module\CustomerService\Domain\LinkGateway;
use Gibbon\Module\CustomerService\Domain\TagGateway;
use Gibbon\Module\CustomerService\Domain\TicketGateway;
use Gibbon\Module\CustomerService\Domain\WatcherGateway;
use Gibbon\Module\CustomerService\Service\Notifier;
use Gibbon\Module\CustomerService\Service\Permissions;

/**
 * Everything on the ticket page that is not the main triage form: subtasks, checklists, tags, links and watchers.
 * Needs the same access as viewing the ticket; changing things needs the edit_ticket permission
 * (except following / unfollowing the ticket yourself).
 */
$_POST['address'] = '/modules/CustomerService/cs_ticket_view.php';

require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

csRequirePost($session);

$_POST = $container->get(Validator::class)->sanitize($_POST);
$action = $_POST['action'] ?? '';
$csTicketID = $_POST['csTicketID'] ?? '';
$person = csPersonID($session);

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

$back = function (string $return) use ($session, $csTicketID) {
    csRedirect($session, 'cs_ticket_view.php', ['csTicketID' => $csTicketID], $return);
};
// These are working notes: the requester does not see them in the activity.
$note = function (string $line) use ($activity, $csTicketID, $person) {
    $activity->log($csTicketID, $person, $line, 'Event', 'Internal');
};

$watchers = $container->get(WatcherGateway::class);

// Following a ticket yourself is open to everybody who can see it.
if ($action === 'watch' || $action === 'unwatch') {
    $action === 'watch' ? $watchers->add($csTicketID, $person) : $watchers->remove($csTicketID, $person);
    $back('success0');
}

// Everything else changes the ticket: edit_ticket permission (or an admin).
if (!csHasTicketPermission($container, $session, $ticket, Permissions::EDIT_TICKET, $isAdmin)) {
    $back('error0');
}

switch ($action) {
    case 'subtask_add':
        $title = mb_substr(trim($_POST['title'] ?? ''), 0, 200);
        $due = csParseDate($_POST['dateDue'] ?? '');
        $assignee = (int)($_POST['gibbonPersonIDAssignee'] ?? 0);
        if ($title === '' || $due === false) {
            $back('error1');
        }
        if (!empty($ticket['csTicketIDParent'])) {
            $back('error1'); // one level only: a subtask cannot have subtasks
        }
        if ($assignee && !isset(csAssignableUsers($container, $session, $ticket, $isAdmin)[$assignee])) {
            $assignee = 0;
        }

        $csSubtaskID = $tickets->createTicket([
            'csTicketTypeID'         => $ticket['csTicketTypeID'],
            'csTicketIDParent'       => $ticket['csTicketID'],
            'title'                  => $title,
            'description'            => $_POST['description'] ?? '',
            'status'                 => 'New',
            'priority'               => $ticket['priority'],
            'gibbonPersonIDReporter' => $person,
            'gibbonPersonIDAssignee' => $assignee ?: null,
            'csTeamID'               => $ticket['csTeamID'] ?: null,
            'dateDue'                => $due ?: null,
        ]);
        if (empty($csSubtaskID)) {
            $back('error2');
        }
        $activity->log($csSubtaskID, $person, sprintf(__('Subtask of %1$s'), csTicketRef($ticket['csTicketID'])), 'Event', 'Internal');
        $note(sprintf(__('Subtask %1$s added: %2$s'), csTicketRef($csSubtaskID), $title));
        if ($assignee) {
            (new Notifier($container))->assigned($tickets->getTicket($csSubtaskID), $assignee, $person);
        }
        (new AutomationEngine($container))->fire('ticket_created', $csSubtaskID);
        $back('success0');
        break;

    case 'checklist_add':
        $title = mb_substr(trim($_POST['title'] ?? ''), 0, 100);
        if ($title === '') {
            $back('error1');
        }
        $lists = $container->get(ChecklistGateway::class);
        $csChecklistID = $lists->addChecklist($csTicketID, $title);
        foreach (preg_split('/\R/', (string)($_POST['items'] ?? '')) as $item) {
            if (trim($item) !== '' && $csChecklistID) {
                $lists->addItem($csChecklistID, trim($item));
            }
        }
        $note(sprintf(__('Checklist "%1$s" added'), $title));
        $back($csChecklistID ? 'success0' : 'error2');
        break;

    case 'checklist_save':
        $lists = $container->get(ChecklistGateway::class);
        $list = $lists->getForTicket($_POST['csChecklistID'] ?? '', $csTicketID);   // the list must belong to this ticket
        if (empty($list)) {
            $back('error1');
        }
        // Field names carry the checklist id because several checklist forms share a page.
        $suffix = '_'.$list['csChecklistID'];
        $lists->removeItems($list['csChecklistID'], (array)($_POST['remove'.$suffix] ?? []));
        $lists->setDone($list['csChecklistID'], (array)($_POST['done'.$suffix] ?? []), $person);
        $newItems = array_filter(array_map('trim', preg_split('/\R/', (string)($_POST['newItems'.$suffix] ?? ''))), 'strlen');
        foreach ($newItems as $item) {
            $lists->addItem($list['csChecklistID'], $item);
        }
        $back('success0');
        break;

    case 'checklist_delete':
        $lists = $container->get(ChecklistGateway::class);
        $list = $lists->getForTicket($_POST['csChecklistID'] ?? '', $csTicketID);
        if (empty($list)) {
            $back('error1');
        }
        $lists->deleteChecklist($list['csChecklistID']);
        $note(sprintf(__('Checklist "%1$s" removed'), $list['title']));
        $back('success0');
        break;

    case 'tags':
        $tags = $container->get(TagGateway::class);
        $removed = $tags->removeFromTicket($csTicketID, (array)($_POST['removeTags'] ?? []));
        $added = $tags->addToTicket($csTicketID, TagGateway::parse($_POST['newTags'] ?? ''));
        if (!empty($added)) {
            $note(sprintf(__('Tags added: %1$s'), implode(', ', $added)));
        }
        if (!empty($removed)) {
            $note(sprintf(__('Tags removed: %1$s'), implode(', ', $removed)));
        }
        $back('success0');
        break;

    case 'link_add':
        [$type, $direction] = array_pad(explode('|', (string)($_POST['linkType'] ?? '')), 2, '');
        if (!isset(LinkGateway::formOptions()[$type.'|'.$direction])) {
            $back('error1');
        }
        if (!preg_match('/^(?:CS-?)?(\d+)$/i', trim($_POST['target'] ?? ''), $m)) {
            $back('error1');
        }
        $other = $tickets->getTicket((int)$m[1]);
        if (empty($other) || !csCanViewTicket($container, $session, $other, $isAdmin)) {
            $back('error1'); // not found, or not visible to you: same answer
        }
        $from = $direction === 'in' ? $other['csTicketID'] : $ticket['csTicketID'];
        $to = $direction === 'in' ? $ticket['csTicketID'] : $other['csTicketID'];

        $ok = $container->get(LinkGateway::class)->addLink($from, $to, $type, $person);
        if ($ok) {
            $labels = LinkGateway::formOptions();
            $note(sprintf(__('%1$s %2$s %3$s'), csTicketRef($ticket['csTicketID']), $labels[$type.'|'.$direction], csTicketRef($other['csTicketID'])));
        }
        $back($ok ? 'success0' : 'error7');
        break;

    case 'link_remove':
        $ok = $container->get(LinkGateway::class)->removeLink($_POST['csTicketLinkID'] ?? '', $csTicketID);
        if ($ok) {
            $note(__('A link was removed'));
        }
        $back($ok ? 'success0' : 'error1');
        break;

    case 'watcher_add':
        $watcher = (int)($_POST['gibbonPersonID'] ?? 0);
        $valid = $watcher > 0 && !empty($container->get(\Gibbon\Domain\User\UserGateway::class)->getByID($watcher, ['gibbonPersonID']));
        if (!$valid) {
            $back('error1');
        }
        $watchers->add($csTicketID, $watcher);
        $back('success0');
        break;

    case 'watcher_remove':
        $watchers->remove($csTicketID, (int)($_POST['gibbonPersonID'] ?? 0));
        $back('success0');
        break;

    default:
        $back('error1');
}
