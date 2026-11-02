<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Module\administration\Domain\FormGateway;

require_once __DIR__ . '/moduleFunctions.php';

csSanitizeRequest($container);

if (!csAccess($guid, $connection2, 'cs_portal.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$page->breadcrumbs->add(__('Customer Portal'));
$page->stylesheets->add('module-cs', 'modules/administration/css/module.css');

echo '<h2>'.__('How can we help you?').'</h2>';

// Search the request types
$search = trim($_GET['search'] ?? '');
$form = Form::createSearch();
$row = $form->addRow();
    $row->addLabel('search', __('Search'))->description(__('Type what you need, e.g. "password" or "laptop".'));
    $row->addTextField('search')->setValue($search);
$form->addRow()->addSearchSubmit($session, __('Clear Search'));
echo $form->getOutput();

// Only the forms this user may use: each form decides that itself (role categories / roles). Administrators see all active forms, to preview them.
$forms = $container->get(FormGateway::class)->selectVisible(csRoleCategory($session), $session->get('gibbonRoleIDCurrent'), csIsAdmin($guid, $connection2));
if ($search !== '') {
    $forms = array_filter($forms, function ($form) use ($search) {
        return stripos($form['name'].' '.$form['description'].' '.$form['groupName'], $search) !== false;
    });
}

if (empty($forms)) {
    $page->addMessage($search !== '' ? __('No form matches your search.') : __('No forms are available to you yet.'));
} else {
    // Group the forms like the Jira portal does
    $groups = [];
    foreach ($forms as $form) {
        $groups[$form['groupName'] ?: __('General')][] = $form;
    }
    ksort($groups);

    foreach ($groups as $group => $groupForms) {
        echo '<h3>'.htmlspecialchars($group).'</h3><div class="cs-portal">';
        foreach ($groupForms as $form) {
            echo '<a class="cs-portal-card" href="'.csURL($session, 'cs_request_new.php', ['csFormID' => $form['csFormID']]).'">';
            echo '<span class="cs-portal-icon">'.htmlspecialchars($form['icon'] ?: "\u{270E}").'</span>';
            echo '<span class="cs-portal-name">'.htmlspecialchars($form['name']).'</span>';
            if (!empty($form['description'])) {
                echo '<span class="cs-portal-desc">'.htmlspecialchars($form['description']).'</span>';
            }
            echo '</a>';
        }
        echo '</div>';
    }
}

echo '<p class="mt-6">'.Format::link(csURL($session, 'cs_ticket_search.php', ['mine' => 'Y']), __('My requests'))
    .' &middot; '.Format::link(csURL($session, 'cs_dashboard.php'), __('My Dashboard')).'</p>';
