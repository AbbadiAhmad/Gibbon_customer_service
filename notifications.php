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
use Gibbon\Module\ServiceDesk\Domain\SubscriberGateway;
use Gibbon\Module\ServiceDesk\Domain\TypeNotificationGateway;
use Gibbon\Module\ServiceDesk\Domain\TicketTypeGateway;
use Gibbon\Module\ServiceDesk\Service\TicketAccess;

if (isActionAccessible($guid, $connection2, '/modules/ServiceDesk/notifications.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs->add(__m('Notification Settings'));

$preference = $container->get(SubscriberGateway::class)->getPreference($session->get('gibbonPersonID'));

$form = Form::create('notifications', $session->get('absoluteURL').'/modules/ServiceDesk/notificationsProcess.php');
$form->setTitle(__m('Notification Settings'));
$form->setDescription(__m('How do you want to be told about updates on your tickets? Your choice is copied to every new ticket you are involved in; you can still change it on a single ticket.'));
$form->addHiddenValue('address', $session->get('address'));

$row = $form->addRow();
    $row->addLabel('notifyInApp', __m('Notification Centre'))->description(__m('The bell at the top of the page.'));
    $row->addYesNo('notifyInApp')->selected($preference['notifyInApp'])->required();

$row = $form->addRow();
    $row->addLabel('notifyEmail', __('Email'));
    $row->addYesNo('notifyEmail')->selected($preference['notifyEmail'])->required();

$row = $form->addRow();
    $row->addLabel('applyToOpen', __m('Apply to My Current Tickets'))->description(__m('Also change the tickets you already follow.'));
    $row->addYesNo('applyToOpen')->selected('N');

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

echo $form->getOutput();

// STAFF: administrators and team members are told about nothing unless they are assigned to a ticket, follow it, or
// choose here which ticket types they want to hear about.
$access = $container->get(TicketAccess::class);
if ($access->isAgent()) {
    $types = [];
    foreach ($container->get(TicketTypeGateway::class)->selectTypeOptions()->fetchKeyPair() as $typeID => $typeName) {
        // ids come zero-filled from the database: compare and key them as numbers
        if ($access->isAdmin() || in_array(intval($typeID), $access->getTeamTypeIDs(), true)) {
            $types[intval($typeID)] = $typeName;
        }
    }
    $mine = $container->get(TypeNotificationGateway::class)->selectByPerson($session->get('gibbonPersonID'));

    $form = Form::create('notificationTypes', $session->get('absoluteURL').'/modules/ServiceDesk/notificationsProcess.php');
    $form->setTitle(__m('Notifications by Ticket Type'));
    $form->setDescription(__m('Staff are not told about tickets just because they exist. You are always told about tickets assigned to you (following your choice above). Here you can also choose to be told about new tickets and about updates of a ticket type, in the notification centre and/or by email.'));
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('action', 'types');

    $options = [
        'newInApp'    => __m('New tickets: notification centre'),
        'newEmail'    => __m('New tickets: email'),
        'updateInApp' => __m('Updates: notification centre'),
        'updateEmail' => __m('Updates: email'),
    ];

    if (empty($types)) {
        $form->addRow()->addContent(Format::alert(__m('There are no ticket types you handle.'), 'message'));
    }
    foreach ($types as $typeID => $typeName) {
        $checked = array_keys(array_filter($mine[intval($typeID)] ?? [], function ($flag) { return $flag == 'Y'; }));
        $row = $form->addRow();
            $row->addLabel('typeNotify_'.intval($typeID), $typeName);
            $row->addCheckbox('typeNotify_'.intval($typeID))->fromArray($options)->checked($checked);
    }

    if (!empty($types)) {
        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit();
    }

    echo $form->getOutput();
}

// TEST: tries both channels for the current user and shows what happened
if ($session->has('serviceDeskNotificationTest')) {
    foreach ((array) $session->get('serviceDeskNotificationTest') as $channel) {
        echo Format::alert(htmlspecialchars($channel[1] ?? ''), !empty($channel[0]) ? 'success' : 'error');
    }
    $session->forget('serviceDeskNotificationTest');
}

$form = Form::create('notificationTest', $session->get('absoluteURL').'/modules/ServiceDesk/notificationsProcess.php');
$form->setTitle(__m('Test'));
$form->setDescription(__m('Sends a test notification to you through both channels, whatever you chose above, and tells you whether each one worked. Remember: you are never notified about your own comments and changes, only about those of other people.'));
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('action', 'test');

$row = $form->addRow();
    $row->addSubmit(__m('Send Me a Test Notification'));

echo $form->getOutput();
