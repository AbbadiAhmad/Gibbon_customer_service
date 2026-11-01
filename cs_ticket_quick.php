<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

require_once __DIR__ . '/moduleFunctions.php';

csSanitizeRequest($container);

if (!csAccess($guid, $connection2, 'cs_ticket_quick.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs->add(__('Quick Create'));

// Only agents (edit_ticket in a space) and admins: the form is null for everybody else.
$form = csQuickCreateForm($container, $session, csIsAdmin($guid, $connection2));
if ($form === null) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

echo '<p class="text-gray-600">'.__('Create a ticket in one step: choose the type, give it a title and a short summary and, if you want, assign it right away. For the full form of a request type use the Customer Portal.').'</p>';
echo csQuickCreatedNotice($container, $session);
echo $form;
