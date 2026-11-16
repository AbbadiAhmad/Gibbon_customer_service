<?php
/**
 * Rebuilds i18n/ServiceDesk.pot from every __m('...') call in the PHP files and Twig templates.
 * Usage: php tests/make-pot.php     Run it after adding or changing user-facing text.
 *
 * Names kept in the database (ticket types, field labels, stage names) are also shown through __m(), so a translator
 * can add them to a .po file by hand, e.g. msgid "Absence Report".
 */
$root = dirname(__DIR__);
$strings = [];

$files = array_merge(glob($root.'/*.php'), glob($root.'/src/*/*.php'), glob($root.'/src/*/*/*.php'), glob($root.'/templates/*.twig.html'));
foreach ($files as $file) {
    $code = file_get_contents($file);
    if (preg_match_all('/__m\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'/', $code, $matches)) {
        foreach ($matches[1] as $text) {
            $strings[stripslashes($text)][] = substr($file, strlen($root) + 1);
        }
    }
}
// Text kept in the database by the installer (default ticket types, fields, stages, filters, teams). It is shown
// through __m(), so it is added here by hand; keep this list in step with manifest.php.
$seedStrings = ['Absence Report', 'Course Unenrolment', 'School De-registration', 'Complaint', 'General Request',
    'Tell the school that your child will be (or was) absent.', 'Ask to take your child out of one or more classes.',
    'Ask to withdraw your child from the school.', 'Raise a complaint. The school will reply on the ticket.',
    'Anything else you need from the school office.', 'Children', 'Absence Type', 'First Day of Absence',
    'Last Day of Absence', 'Leave empty for a single day.', 'Details', 'Classes', 'The classes your child should leave.',
    'Effective From', 'Reason', 'Last Day at School', 'Reason for leaving and anything the office should know.',
    'Children Concerned', 'Only if the complaint is about one of your children.', 'Complaint Details',
    'New', 'In Progress', 'Done', 'Rejected', 'Withdrawn', 'Approved', 'Open', 'Closed',
    'Medical', 'Family', 'Education', 'Other', 'My Requests', 'Open Tickets', 'Assigned to Me', 'Unassigned',
    'Absence Intake', 'Administration', 'Management',
    'School office: attendance, enrolment, registration and general requests.', 'School management: complaints.'];
foreach ($seedStrings as $text) {
    $strings[$text][] = 'manifest.php';
}
ksort($strings);

$pot = "# Service Desk module for Gibbon - translation template.\n"
    ."# Copy to i18n/<locale>/LC_MESSAGES/ServiceDesk.po (e.g. ar_SA, fr_FR), translate, and compile to ServiceDesk.mo.\n"
    ."msgid \"\"\nmsgstr \"\"\n\"Project-Id-Version: ServiceDesk\\n\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\"Content-Transfer-Encoding: 8bit\\n\"\n\n";
foreach ($strings as $text => $where) {
    $pot .= '#: '.implode(' ', array_unique($where))."\n";
    $pot .= 'msgid "'.addcslashes($text, "\"\\\n").'"'."\nmsgstr \"\"\n\n";
}

file_put_contents($root.'/i18n/ServiceDesk.pot', $pot);
echo count($strings)." strings written to i18n/ServiceDesk.pot\n";
