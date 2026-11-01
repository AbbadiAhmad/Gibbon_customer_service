<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Data\Validator;
use Gibbon\Module\administration\Domain\BoardGateway;
use Gibbon\Module\administration\Service\QuickCreate;

$_POST['address'] = '/modules/administration/cs_ticket_quick.php';

require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

csRequirePost($session);

$_POST = $container->get(Validator::class)->sanitize($_POST);

if (!csAccess($guid, $connection2, 'cs_ticket_quick.php')) {
    csRedirect($session, 'cs_dashboard.php', [], 'error0');
}

// Who may quick create, and whom they may assign, is decided by QuickCreate (space permissions), not by this script.
[$csTicketID, $return] = QuickCreate::create($container, csPersonID($session), csIsAdmin($guid, $connection2), $_POST);

// Back to where the form was: a board, or the quick create page.
$csBoardID = $_POST['csBoardID'] ?? '';
if (!empty($csBoardID) && !empty($container->get(BoardGateway::class)->getByID($csBoardID))) {
    csRedirect($session, 'cs_board_view.php', ['csBoardID' => $csBoardID] + ($csTicketID ? ['created' => $csTicketID] : []), $return);
}
csRedirect($session, 'cs_ticket_quick.php', $csTicketID ? ['created' => $csTicketID] : [], $return);
