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
