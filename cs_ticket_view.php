<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Module\CustomerService\Domain\ActivityGateway;
use Gibbon\Module\CustomerService\Domain\FieldGateway;
use Gibbon\Module\CustomerService\Domain\TeamGateway;
use Gibbon\Module\CustomerService\Domain\TicketGateway;
use Gibbon\Module\CustomerService\Service\CustomFields;
use Gibbon\Module\CustomerService\Service\Permissions;

require_once __DIR__ . '/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/CustomerService/cs_ticket_view.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$csTicketID = $_GET['csTicketID'] ?? '';
$ticket = $container->get(TicketGateway::class)->getTicket($csTicketID);
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

$page->breadcrumbs->add(__('My Dashboard'), 'cs_dashboard.php');
$page->breadcrumbs->add(csTicketRef($ticket['csTicketID']));

// ---- Details ---------------------------------------------------------------
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
];
foreach ($details as $label => $value) {
    echo '<tr><td class="w-1/4 py-1 text-gray-600">'.$label.'</td><td class="py-1">'.$value.'</td></tr>';
}
foreach ($container->get(FieldGateway::class)->selectByType($ticket['csTicketTypeID']) as $field) {
    $shown = CustomFields::format($field, $ticket['customData'][$field['fieldKey']] ?? null);
    echo '<tr><td class="w-1/4 py-1 text-gray-600">'.htmlspecialchars($field['label']).'</td><td class="py-1">'.nl2br(htmlspecialchars($shown)).'</td></tr>';
}
echo '</tbody></table>';

if (!empty($ticket['description'])) {
    echo '<h3>'.__('Description').'</h3><p>'.nl2br(htmlspecialchars($ticket['description'])).'</p>';
}

// ---- Triage ----------------------------------------------------------------
if ($can[Permissions::EDIT_TICKET] || $can[Permissions::TRANSITION] || $can[Permissions::ASSIGN]) {
    $form = Form::create('csTriage', $session->get('absoluteURL').'/modules/CustomerService/cs_ticket_process.php');
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('action', 'update');
    $form->addHiddenValue('csTicketID', $ticket['csTicketID']);
    $form->setTitle(__('Triage'));

    if ($can[Permissions::TRANSITION]) {
        $row = $form->addRow();
            $row->addLabel('status', __('Status'));
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
    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Save'));

    echo $form->getOutput();
}

// ---- Activity --------------------------------------------------------------
echo '<h3>'.__('Activity').'</h3>';
$activities = $container->get(ActivityGateway::class)->selectByTicket($ticket['csTicketID']);
if (empty($activities)) {
    echo '<p class="text-gray-600">'.__('No activity yet.').'</p>';
}
foreach ($activities as $a) {
    $who = htmlspecialchars(csPersonName($a['preferredName'], $a['surname']));
    $when = Format::dateTimeReadable($a['timestamp']);
    if ($a['kind'] == 'Event') {
        echo '<div class="text-sm text-gray-600 py-1">&bull; '.htmlspecialchars($a['body']).' <i>('.$who.', '.$when.')</i></div>';
    } else {
        echo '<div class="border rounded p-3 my-2"><div class="text-sm text-gray-600 mb-1"><b>'.$who.'</b> &middot; '.$when.'</div>'.nl2br(htmlspecialchars($a['body'])).'</div>';
    }
}

if ($isReporter || $can[Permissions::COMMENT]) {
    $form = Form::create('csComment', $session->get('absoluteURL').'/modules/CustomerService/cs_ticket_process.php');
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('action', 'comment');
    $form->addHiddenValue('csTicketID', $ticket['csTicketID']);
    $row = $form->addRow();
        $row->addLabel('body', __('Add a comment'));
        $row->addTextArea('body')->setRows(4)->required();
    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Comment'));
    echo $form->getOutput();
}

// ---- Delete ----------------------------------------------------------------
if ($can[Permissions::DELETE_TICKET]) {
    $form = Form::create('csDeleteTicket', $session->get('absoluteURL').'/modules/CustomerService/cs_ticket_process.php');
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
