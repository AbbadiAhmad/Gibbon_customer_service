<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Domain\DataSet;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Module\CustomerService\Domain\TicketGateway;

require_once __DIR__ . '/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/CustomerService/cs_dashboard.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs->add(__('My Dashboard'));

$isAdmin = csIsAdmin($guid, $connection2);

echo '<p>'.Format::link(csURL($session, 'cs_request_new.php'), __('Raise a Request')).' &middot; '
    .Format::link(csURL($session, 'cs_ticket_search.php'), __('Search Tickets')).' &middot; '
    .Format::link(csURL($session, 'cs_ticket_search.php', ['assignee' => 'me', 'open' => 'Y']), __('Assigned to me')).'</p>';

// Tickets waiting for triage on the boards the user can work on.
$boards = csAccessibleBoards($container, $session, $isAdmin);
if (!empty($boards)) {
    $ticketGateway = $container->get(TicketGateway::class);
    $incoming = [];
    foreach ($boards as $board) {
        foreach ($ticketGateway->selectByFilter($board['filter'], null, 200) as $ticket) {
            if ($ticket['status'] === 'New') {
                $incoming[$ticket['csTicketID']] = $ticket + ['boardID' => $board['csBoardID'], 'boardName' => $board['name']];
            }
        }
    }
    krsort($incoming);

    $table = DataTable::create('csIncoming');
    $table->setTitle(__('Incoming Requests (status New)'));
    $table->addMetaData('blankSlate', __('Nothing is waiting for triage.'));
    $table->addColumn('ref', __('Reference'))->format(function ($t) use ($session) {
        return Format::link(csURL($session, 'cs_ticket_view.php', ['csTicketID' => $t['csTicketID']]), csTicketRef($t['csTicketID']));
    });
    $table->addColumn('title', __('Title'));
    $table->addColumn('typeName', __('Type'));
    $table->addColumn('priority', __('Priority'))->format(function ($t) {
        return csPriorityLabel($t['priority']);
    });
    $table->addColumn('reporter', __('Requested by'))->format(function ($t) {
        return csPersonName($t['reporterPreferredName'], $t['reporterSurname']);
    });
    $table->addColumn('boardName', __('Board'))->format(function ($t) use ($session) {
        return Format::link(csURL($session, 'cs_board_view.php', ['csBoardID' => $t['boardID']]), $t['boardName']);
    });
    echo $table->render(new DataSet(array_slice(array_values($incoming), 0, 50)));
}

echo csRenderMyRequests($container, $session);
