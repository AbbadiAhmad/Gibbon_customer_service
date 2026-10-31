<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Module\CustomerService\Domain\AttachmentGateway;
use Gibbon\Module\CustomerService\Domain\TicketGateway;
use Gibbon\Module\CustomerService\Service\Attachments;

// Opened through export.php (no page layout): streams one attachment after checking the same access as the ticket page.
require_once __DIR__ . '/../../gibbon.php';
require_once __DIR__ . '/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/CustomerService/cs_ticket_view.php')) {
    header('HTTP/1.1 403 Forbidden');
    echo __('You do not have access to this action.');
    exit;
}

$attachment = $container->get(AttachmentGateway::class)->getWithVisibility($_GET['csTicketAttachmentID'] ?? '');
$ticket = empty($attachment) ? [] : $container->get(TicketGateway::class)->getTicket($attachment['csTicketID']);
$isAdmin = csIsAdmin($guid, $connection2);

if (empty($ticket) || !csCanViewTicket($container, $session, $ticket, $isAdmin)
    || ($attachment['visibility'] == 'Internal' && !csCanSeeInternal($container, $session, $ticket, $isAdmin))) {
    header('HTTP/1.1 403 Forbidden');
    echo __('You do not have access to this action.');
    exit;
}

$file = $session->get('absolutePath').'/'.ltrim($attachment['filePath'], '/');
if (!Attachments::isSafePath($attachment['filePath']) || !is_file($file)) {
    header('HTTP/1.1 404 Not Found');
    echo __('The specified record cannot be found.');
    exit;
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'.str_replace(['"', "\r", "\n"], '', $attachment['fileName']).'"');
header('Content-Length: '.filesize($file));
header('X-Content-Type-Options: nosniff');
readfile($file);
exit;
