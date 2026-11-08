<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Domain\DataSet;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Module\administration\Domain\BoardGateway;
use Gibbon\Module\administration\Domain\SpaceGateway;
use Gibbon\Module\administration\Domain\TeamGateway;
use Gibbon\Module\administration\Domain\TicketGateway;
use Gibbon\Module\administration\Service\Workflow;

const CS_MODULE = 'administration';

/** Parameters that make up a ticket search (and a saved filter). */
const CS_SEARCH_PARAMS = ['search', 'status', 'csTicketTypeID', 'priority', 'assignee', 'mine', 'open', 'overdue', 'tag', 'watching'];

/** URL of a module page, with optional query parameters. */
function csURL($session, string $page, array $params = []): string
{
    $url = $session->get('absoluteURL').'/index.php?q=/modules/'.CS_MODULE.'/'.$page;
    foreach ($params as $key => $value) {
        $url .= '&'.rawurlencode($key).'='.rawurlencode((string)$value);
    }
    return $url;
}

function csRedirect($session, string $page, array $params = [], string $return = ''): void
{
    if ($return !== '') {
        $params['return'] = $return;
    }
    header('Location: '.csURL($session, $page, $params));
    exit;
}

/**
 * Gibbon's own permission check (isActionAccessible), for a page of this module.
 * Pass $actionName to require that specific action (a page that is listed in several actions - e.g. the
 * shared delete page - is then only open to roles that hold that action, not to any of them).
 */
function csAccess($guid, $connection2, string $page, string $actionName = ''): bool
{
    return isActionAccessible($guid, $connection2, '/modules/'.CS_MODULE.'/'.$page, $actionName);
}

/** Same sanitising Gibbon pages apply to their input (strips HTML/scripts). */
function csSanitizeRequest($container): void
{
    $validator = $container->get(\Gibbon\Data\Validator::class);
    $_GET = $validator->sanitize($_GET);
}

/** Process scripts only act on a POST (the CSRF token / nonce check of gibbon.php applies to POSTs). */
function csRequirePost($session, string $page = 'cs_dashboard.php'): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        csRedirect($session, $page, [], 'error1');
    }
}

/** Gibbon role category of the current role: Staff, Student, Parent or Other. */
function csRoleCategory($session): string
{
    return (string)$session->get('gibbonRoleIDCurrentCategory');
}

function csPersonID($session): int
{
    return (int)$session->get('gibbonPersonID');
}

/**
 * A "global admin" may configure teams, ticket types, roles and every space and sees every ticket.
 * It is whoever has the Manage Ticket Types action (Administrator by default).
 */
function csIsAdmin($guid, $connection2): bool
{
    return isActionAccessible($guid, $connection2, '/modules/'.CS_MODULE.'/cs_type_manage.php');
}

function csPriorityLabel(string $priority): string
{
    $colors = ['Low' => 'text-gray-600', 'Medium' => 'text-blue-700', 'High' => 'text-orange-600', 'Urgent' => 'text-red-700 font-bold'];
    return '<span class="'.($colors[$priority] ?? '').'">'.__($priority).'</span>';
}

function csTicketRef($csTicketID): string
{
    return 'CS-'.(int)$csTicketID;
}

function csPersonName(?string $preferredName, ?string $surname): string
{
    return trim(($preferredName ?? '').' '.($surname ?? ''));
}

/** Boards the current user may open. */
function csAccessibleBoards($container, $session, bool $isAdmin): array
{
    return $container->get(BoardGateway::class)->selectAccessible(csPersonID($session), $isAdmin);
}

/** Boards (among the accessible ones) whose filter currently matches the ticket. */
function csMatchingBoards($container, array $boards, array $ticket): array
{
    $ticketGateway = $container->get(TicketGateway::class);
    return array_values(array_filter($boards, function ($board) use ($ticketGateway, $ticket) {
        return !empty($ticketGateway->selectByFilter($board['filter'], $ticket['csTicketID'], 1));
    }));
}

function csCanViewTicket($container, $session, array $ticket, bool $isAdmin): bool
{
    if ($isAdmin || (int)$ticket['gibbonPersonIDReporter'] === csPersonID($session)) {
        return true;
    }
    return !empty(csMatchingBoards($container, csAccessibleBoards($container, $session, false), $ticket));
}

/**
 * Does the user hold $permission on a space that has a board showing this ticket?
 * (The ticket's reporter has no staff permissions by virtue of being the reporter.)
 */
function csHasTicketPermission($container, $session, array $ticket, string $permission, bool $isAdmin): bool
{
    if ($isAdmin) {
        return true;
    }
    $spaces = $container->get(SpaceGateway::class);
    $person = csPersonID($session);
    foreach (csMatchingBoards($container, csAccessibleBoards($container, $session, false), $ticket) as $board) {
        if ($spaces->hasPermission($person, $board['csSpaceID'], $permission)) {
            return true;
        }
    }
    return false;
}

/** Users a ticket can be assigned to: members of the spaces that show it, plus the members of its team. */
function csAssignableUsers($container, $session, array $ticket, bool $isAdmin): array
{
    $boards = csMatchingBoards($container, csAccessibleBoards($container, $session, $isAdmin), $ticket);
    $people = $container->get(SpaceGateway::class)->selectPeopleInSpaces(array_unique(array_column($boards, 'csSpaceID')));
    if (!empty($ticket['csTeamID'])) {
        $people += $container->get(TeamGateway::class)->selectMembers($ticket['csTeamID']);
    }
    asort($people);
    return $people;
}

/**
 * Statuses a ticket can be moved to: the columns of the boards that show it, limited by the
 * workflow of its ticket type. The current status is always included.
 */
function csStatusOptions($container, $session, array $ticket, bool $isAdmin): array
{
    $boardGateway = $container->get(BoardGateway::class);
    $statuses = [];
    foreach (csMatchingBoards($container, csAccessibleBoards($container, $session, $isAdmin), $ticket) as $board) {
        foreach ($boardGateway->selectColumns($board['csBoardID']) as $column) {
            $statuses[$column['name']] = $column['name'];
        }
    }
    $statuses = Workflow::filterTargets($ticket['workflow'] ?? null, $ticket['status'], $statuses);
    $statuses[$ticket['status']] = $ticket['status'];
    return $statuses;
}

/** Can this user read internal notes and their attachments? (Anyone who works on the ticket, not the plain requester.) */
function csCanSeeInternal($container, $session, array $ticket, bool $isAdmin): bool
{
    return csHasTicketPermission($container, $session, $ticket, 'view_board', $isAdmin);
}

/** Download link of an attachment (served by cs_attachment.php after an access check). */
function csAttachmentLink($session, array $attachment): string
{
    $url = $session->get('absoluteURL').'/export.php?q=/modules/'.CS_MODULE.'/cs_attachment.php&csTicketAttachmentID='.(int)$attachment['csTicketAttachmentID'];
    return '<a href="'.$url.'">'.htmlspecialchars($attachment['fileName']).'</a>';
}

/** Spaces the user may administrate (global admin: all spaces). */
function csManageableSpaceIDs($container, $session, bool $isAdmin, string $permission): ?array
{
    if ($isAdmin) {
        return null; // null = no restriction
    }
    return $container->get(SpaceGateway::class)->selectSpaceIDsWithPermission(csPersonID($session), $permission);
}

/** HTML table of the user's own requests (used by the dashboard page and the dashboard hook). */
function csRenderMyRequests($container, $session, bool $openOnly = false, int $limit = 100): string
{
    $tickets = $container->get(TicketGateway::class)->selectByReporter(csPersonID($session), $openOnly, $limit);

    $table = DataTable::create('csMyRequests');
    $table->setTitle(__('My Requests'));
    $table->addMetaData('blankSlate', __('You have not raised any requests yet.'));

    $table->addColumn('ref', __('Reference'))->format(function ($t) use ($session) {
        return Format::link(csURL($session, 'cs_ticket_view.php', ['csTicketID' => $t['csTicketID']]), csTicketRef($t['csTicketID']));
    });
    $table->addColumn('title', __('Title'));
    $table->addColumn('typeName', __('Type'));
    $table->addColumn('status', __('Status'));
    $table->addColumn('due', __('Due'))->format(function ($t) {
        return csDueBadge($t);
    });
    $table->addColumn('timestampCreated', __('Raised'))->format(function ($t) {
        return Format::dateTimeReadable($t['timestampCreated']);
    });

    return $table->render(new DataSet($tickets));
}

/**
 * Describe what a generic delete request (cs_delete.php) is allowed to do.
 *
 * @return array|null [allowed => bool, back => [page, params], delete => callable returning bool] or null if unknown
 */
function csDeleteTarget($container, $session, bool $isAdmin, string $entity, $id): ?array
{
    $ns = 'Gibbon\\Module\\administration\\Domain\\';
    $person = csPersonID($session);
    $spaces = $container->get($ns.'SpaceGateway');

    switch ($entity) {
        case 'team':
            $action = 'Manage Teams';
            $gateway = $container->get($ns.'TeamGateway');
            return ['action' => $action, 'allowed' => $isAdmin && $gateway->exists($id), 'back' => ['cs_team_manage.php', []],
                'delete' => function () use ($gateway, $id) { return $gateway->deleteTeam($id); }];
        case 'type':
            $action = 'Manage Ticket Types';
            $gateway = $container->get($ns.'TicketTypeGateway');
            return ['action' => $action, 'allowed' => $isAdmin && $gateway->exists($id), 'back' => ['cs_type_manage.php', []],
                'delete' => function () use ($gateway, $id) { return $gateway->deleteType($id); }];
        case 'role':
            $action = 'Manage Roles';
            $gateway = $container->get($ns.'RoleGateway');
            return ['action' => $action, 'allowed' => $isAdmin && $gateway->exists($id), 'back' => ['cs_role_manage.php', []],
                'delete' => function () use ($gateway, $id) { return $gateway->deleteRole($id); }];
        case 'field':
            $action = 'Manage Ticket Types';
            $gateway = $container->get($ns.'FieldGateway');
            $field = $gateway->getByID($id);
            return ['action' => $action, 'allowed' => $isAdmin && !empty($field), 'back' => ['cs_type_edit.php', ['csTicketTypeID' => $field['csTicketTypeID'] ?? '']],
                'delete' => function () use ($gateway, $id) { return $gateway->delete($id); }];
        case 'rule':
            $action = 'Manage Automation';
            $gateway = $container->get($ns.'RuleGateway');
            return ['action' => $action, 'allowed' => $isAdmin && $gateway->exists($id), 'back' => ['cs_rule_manage.php', []],
                'delete' => function () use ($gateway, $id) { return $gateway->delete($id); }];
        case 'form':
            $action = 'Manage Forms';
            $gateway = $container->get($ns.'FormGateway');
            return ['action' => $action, 'allowed' => $isAdmin && $gateway->exists($id), 'back' => ['cs_form_manage.php', []],
                'delete' => function () use ($gateway, $id) { return $gateway->deleteForm($id); }];
        case 'formfield':
            $action = 'Manage Forms';
            $gateway = $container->get($ns.'FormFieldGateway');
            $question = $gateway->getByID($id);
            return ['action' => $action, 'allowed' => $isAdmin && !empty($question), 'back' => ['cs_form_edit.php', ['csFormID' => $question['csFormID'] ?? '']],
                'delete' => function () use ($gateway, $id) { return $gateway->delete($id); }];
        case 'space':
            $action = 'Manage Spaces';
            return ['action' => $action, 'allowed' => $isAdmin && $spaces->exists($id), 'back' => ['cs_space_manage.php', []],
                'delete' => function () use ($spaces, $id) { return $spaces->deleteSpace($id); }];
        case 'board':
            $action = 'Manage Spaces';
            $gateway = $container->get($ns.'BoardGateway');
            $board = $gateway->getByID($id);
            $spaceID = $board['csSpaceID'] ?? 0;
            return ['action' => $action, 'allowed' => !empty($board) && ($isAdmin || $spaces->hasPermission($person, $spaceID, 'manage_boards')),
                'back' => ['cs_space_edit.php', ['csSpaceID' => $spaceID]],
                'delete' => function () use ($gateway, $id) { return $gateway->deleteBoard($id); }];
        case 'member':
            $action = 'Manage Spaces';
            $member = $spaces->getMember($id);
            $spaceID = $member['csSpaceID'] ?? 0;
            return ['action' => $action, 'allowed' => !empty($member) && ($isAdmin || $spaces->hasPermission($person, $spaceID, 'manage_members')),
                'back' => ['cs_space_edit.php', ['csSpaceID' => $spaceID]],
                'delete' => function () use ($spaces, $id) { return $spaces->removeMember($id); }];
    }

    return null;
}

/**
 * Read a date typed in a Gibbon date input.
 *
 * @return string|null|false 'Y-m-d', null when empty, false when it is not a valid date
 */
function csParseDate($input)
{
    $input = trim((string)$input);
    if ($input === '') {
        return null;
    }
    $date = \Gibbon\Services\Format::dateConvert($input);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string)$date, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
        return false;
    }
    return $date;
}

/** Overdue / due soon marker for a ticket row (empty string when there is nothing to flag). */
function csDueBadge(array $ticket): string
{
    if (empty($ticket['dateDue']) || \Gibbon\Module\administration\Service\Workflow::isDone($ticket['status'])) {
        return '';
    }
    $today = date('Y-m-d');
    if ($ticket['dateDue'] < $today) {
        return '<span class="text-red-700 font-bold">'.__('Overdue').' ('.\Gibbon\Services\Format::date($ticket['dateDue']).')</span>';
    }
    if ($ticket['dateDue'] == $today) {
        return '<span class="text-orange-600 font-bold">'.__('Due today').'</span>';
    }
    return '<span class="text-gray-600">'.sprintf(__('Due %1$s'), \Gibbon\Services\Format::date($ticket['dateDue'])).'</span>';
}

/**
 * The quick create form (type, title, summary, assignee) as HTML, for the quick create page and the board.
 * Null when the user may not quick create. $back is carried to the process script (e.g. ['csBoardID' => 3] to return to a board).
 */
function csQuickCreateForm($container, $session, bool $isAdmin, array $back = []): ?string
{
    $person = csPersonID($session);
    if (!\Gibbon\Module\administration\Service\QuickCreate::canUse($container, $person, $isAdmin)) {
        return null;
    }

    $form = \Gibbon\Forms\Form::create('csQuickCreate'.(isset($back['csBoardID']) ? 'Board' : ''), $session->get('absoluteURL').'/modules/'.CS_MODULE.'/cs_ticket_quickProcess.php');
    $form->addHiddenValue('address', $session->get('address'));
    foreach ($back as $key => $value) {
        $form->addHiddenValue($key, $value);
    }

    $row = $form->addRow();
        $row->addLabel('csTicketTypeID', __('Type'));
        $row->addSelect('csTicketTypeID')->fromArray(\Gibbon\Module\administration\Service\QuickCreate::typeOptions($container))->required()->placeholder();

    $row = $form->addRow();
        $row->addLabel('title', __('Title'));
        $row->addTextField('title')->maxLength(200)->required();

    $row = $form->addRow();
        $row->addLabel('summary', __('Summary'))->description(__('Optional. A few words on what is needed; it becomes the description of the ticket.'));
        $row->addTextArea('summary')->setRows(2);

    $assignees = \Gibbon\Module\administration\Service\QuickCreate::assignees($container, $person, $isAdmin);
    if (!empty($assignees)) {
        $row = $form->addRow();
            $row->addLabel('gibbonPersonIDAssignee', __('Assignee'))->description(__('Leave empty to let the team of the type pick it up.'));
            $row->addSelect('gibbonPersonIDAssignee')->fromArray($assignees)->placeholder(__('Unassigned'));
    }

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Create'));

    return $form->getOutput();
}

/** "Created CS-12 Title" notice after a quick create (only for the person who created it). */
function csQuickCreatedNotice($container, $session): string
{
    $ticket = empty($_GET['created']) ? [] : $container->get(\Gibbon\Module\administration\Domain\TicketGateway::class)->getTicket((int)$_GET['created']);
    if (empty($ticket) || (int)$ticket['gibbonPersonIDReporter'] !== csPersonID($session)) {
        return '';
    }
    return '<div class="success">'.__('Created').' '.\Gibbon\Services\Format::link(csURL($session, 'cs_ticket_view.php', ['csTicketID' => $ticket['csTicketID']]), csTicketRef($ticket['csTicketID']).' '.htmlspecialchars($ticket['title'])).'</div>';
}

/** A one-time message for the next page (e.g. why a form field was refused): Gibbon's return codes are too generic for that. */
function csFlash($session, string $message): void
{
    $session->set('csFlash', $message);
}

/** The message set by csFlash() as an error box (and forgets it). */
function csFlashOutput($session): string
{
    $message = $session->get('csFlash');
    if (empty($message)) {
        return '';
    }
    $session->forget('csFlash');
    return '<div class="error">'.htmlspecialchars($message).'</div>';
}
