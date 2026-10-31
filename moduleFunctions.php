<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Domain\DataSet;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Module\CustomerService\Domain\BoardGateway;
use Gibbon\Module\CustomerService\Domain\SpaceGateway;
use Gibbon\Module\CustomerService\Domain\TeamGateway;
use Gibbon\Module\CustomerService\Domain\TicketGateway;
use Gibbon\Module\CustomerService\Service\Workflow;

const CS_MODULE = 'CustomerService';

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
    $ns = 'Gibbon\\Module\\CustomerService\\Domain\\';
    $person = csPersonID($session);
    $spaces = $container->get($ns.'SpaceGateway');

    switch ($entity) {
        case 'team':
            $gateway = $container->get($ns.'TeamGateway');
            return ['allowed' => $isAdmin && $gateway->exists($id), 'back' => ['cs_team_manage.php', []],
                'delete' => function () use ($gateway, $id) { return $gateway->deleteTeam($id); }];
        case 'type':
            $gateway = $container->get($ns.'TicketTypeGateway');
            return ['allowed' => $isAdmin && $gateway->exists($id), 'back' => ['cs_type_manage.php', []],
                'delete' => function () use ($gateway, $id) { return $gateway->deleteType($id); }];
        case 'role':
            $gateway = $container->get($ns.'RoleGateway');
            return ['allowed' => $isAdmin && $gateway->exists($id), 'back' => ['cs_role_manage.php', []],
                'delete' => function () use ($gateway, $id) { return $gateway->deleteRole($id); }];
        case 'field':
            $gateway = $container->get($ns.'FieldGateway');
            $field = $gateway->getByID($id);
            return ['allowed' => $isAdmin && !empty($field), 'back' => ['cs_type_edit.php', ['csTicketTypeID' => $field['csTicketTypeID'] ?? '']],
                'delete' => function () use ($gateway, $id) { return $gateway->delete($id); }];
        case 'rule':
            $gateway = $container->get($ns.'RuleGateway');
            return ['allowed' => $isAdmin && $gateway->exists($id), 'back' => ['cs_rule_manage.php', []],
                'delete' => function () use ($gateway, $id) { return $gateway->delete($id); }];
        case 'space':
            return ['allowed' => $isAdmin && $spaces->exists($id), 'back' => ['cs_space_manage.php', []],
                'delete' => function () use ($spaces, $id) { return $spaces->deleteSpace($id); }];
        case 'board':
            $gateway = $container->get($ns.'BoardGateway');
            $board = $gateway->getByID($id);
            $spaceID = $board['csSpaceID'] ?? 0;
            return ['allowed' => !empty($board) && ($isAdmin || $spaces->hasPermission($person, $spaceID, 'manage_boards')),
                'back' => ['cs_space_edit.php', ['csSpaceID' => $spaceID]],
                'delete' => function () use ($gateway, $id) { return $gateway->deleteBoard($id); }];
        case 'member':
            $member = $spaces->getMember($id);
            $spaceID = $member['csSpaceID'] ?? 0;
            return ['allowed' => !empty($member) && ($isAdmin || $spaces->hasPermission($person, $spaceID, 'manage_members')),
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
    if (empty($ticket['dateDue']) || \Gibbon\Module\CustomerService\Service\Workflow::isDone($ticket['status'])) {
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
