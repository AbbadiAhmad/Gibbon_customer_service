<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Module\administration\Domain\TicketTypeGateway;

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

// Only the request types open to the user's role category (administrators see every active type, to preview them).
$types = $container->get(TicketTypeGateway::class)->selectForAudience(csRoleCategory($session), csIsAdmin($guid, $connection2));
if ($search !== '') {
    $types = array_filter($types, function ($type) use ($search) {
        return stripos($type['name'].' '.$type['description'].' '.$type['groupName'], $search) !== false;
    });
}

if (empty($types)) {
    $page->addMessage($search !== '' ? __('No request type matches your search.') : __('No request types are available to you yet.'));
} else {
    // Group the types like the Jira portal does
    $groups = [];
    foreach ($types as $type) {
        $groups[$type['groupName'] ?: __('General')][] = $type;
    }
    ksort($groups);

    foreach ($groups as $group => $groupTypes) {
        echo '<h3>'.htmlspecialchars($group).'</h3><div class="cs-portal">';
        foreach ($groupTypes as $type) {
            echo '<a class="cs-portal-card" href="'.csURL($session, 'cs_request_new.php', ['csTicketTypeID' => $type['csTicketTypeID']]).'">';
            echo '<span class="cs-portal-icon">'.htmlspecialchars($type['icon'] ?: "\u{1F4DD}").'</span>';
            echo '<span class="cs-portal-name">'.htmlspecialchars($type['name']).'</span>';
            if (!empty($type['description'])) {
                echo '<span class="cs-portal-desc">'.htmlspecialchars($type['description']).'</span>';
            }
            echo '</a>';
        }
        echo '</div>';
    }
}

echo '<p class="mt-6">'.Format::link(csURL($session, 'cs_ticket_search.php', ['mine' => 'Y']), __('My requests'))
    .' &middot; '.Format::link(csURL($session, 'cs_dashboard.php'), __('My Dashboard')).'</p>';
