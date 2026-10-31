<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Services\Format;

require_once __DIR__ . '/moduleFunctions.php';

// Included by the Staff / Student / Parent dashboards: the returned HTML becomes the tab content.
$output = '<p>'.Format::link(csURL($session, 'cs_request_new.php'), __('Raise a Request')).'</p>';
$output .= csRenderMyRequests($container, $session, true, 20);

return $output;
