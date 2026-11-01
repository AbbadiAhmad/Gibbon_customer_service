<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Services\Format;

// The dashboards include this file from a method that defines $session, $guid and $connection2 but not $container;
// the container is the global one created in gibbon.php.
global $container;

// Only Gibbon includes this file (dashboard hook). Requested directly there is no session / container: stop.
if (!isset($session, $container, $guid, $connection2)) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/moduleFunctions.php';

if (!csAccess($guid, $connection2, 'cs_dashboard.php')) {
    return '';
}

// Included by the Staff / Student / Parent dashboards: the returned HTML becomes the tab content.
$output = '<p>'.Format::link(csURL($session, 'cs_request_new.php'), __('Raise a Request')).'</p>';
$output .= csRenderMyRequests($container, $session, true, 20);

return $output;
