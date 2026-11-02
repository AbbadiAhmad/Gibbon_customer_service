<?php
/**
 * migrate.php - upgrade WITH data: load a real 0.7.0 database (tests/fixtures/install-0.7.0.sql = the 0.7.0 installer + its default data),
 * run the 0.8.0 step (forms become separate from ticket types) and check that every old request form came out equivalent.
 * Usage: php tests/migrate.php   (database cs_data must exist; it is emptied first)
 */
namespace Gibbon\Services { class Format { public static function date($d) { return $d ? date('d/m/Y', strtotime($d)) : ''; } public static function dateConvert($d) { return $d; } } }
namespace {
    require __DIR__.'/bootstrap.php';
    function __($s, $p = []) { return $s; }
    spl_autoload_register(function ($c) { $p = 'Gibbon\\Module\\administration\\'; if (strpos($c, $p) === 0) { $f = dirname(__DIR__).'/src/'.str_replace('\\', '/', substr($c, strlen($p))).'.php'; if (is_file($f)) require $f; } });
    use Gibbon\Module\administration\Service\FormMapper;

    $fail = 0; $n = 0;
    function check(string $what, $ok, $detail = '') { global $fail, $n; $n++; if (!$ok) { $fail++; echo "  FAIL: $what $detail\n"; } }

    $pdo = csTestPdo('cs_data');
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) { $pdo->exec("DROP TABLE `$t`"); }
    $pdo->exec(file_get_contents(__DIR__.'/fixtures/install-0.7.0.sql'));
    // the Gibbon tables the step touches
    $pdo->exec("CREATE TABLE gibbonModule (gibbonModuleID int auto_increment primary key, name varchar(50), entryURL varchar(255))");
    $pdo->exec("CREATE TABLE gibbonAction (gibbonActionID int auto_increment primary key, gibbonModuleID int, name varchar(60), precedence int, category varchar(40), description text, URLList text, entryURL varchar(255), entrySidebar char(1), menuShow char(1), defaultPermissionAdmin char(1), defaultPermissionTeacher char(1), defaultPermissionStudent char(1), defaultPermissionParent char(1), defaultPermissionSupport char(1), categoryPermissionStaff char(1), categoryPermissionStudent char(1), categoryPermissionParent char(1), categoryPermissionOther char(1))");
    $pdo->exec("CREATE TABLE gibbonPermission (gibbonPermissionID int auto_increment primary key, gibbonRoleID int, gibbonActionID int)");
    $pdo->exec("INSERT INTO gibbonModule (name, entryURL) VALUES ('administration', 'cs_portal.php')");
    $pdo->exec("INSERT INTO gibbonAction (gibbonModuleID,name,URLList,entryURL,entrySidebar,menuShow,defaultPermissionAdmin,defaultPermissionTeacher,defaultPermissionStudent,defaultPermissionParent,defaultPermissionSupport,categoryPermissionStaff,categoryPermissionStudent,categoryPermissionParent,categoryPermissionOther) VALUES (1,'Manage Ticket Types','cs_type_manage.php, cs_form_edit.php','cs_type_manage.php','Y','Y','Y','N','N','N','N','Y','N','N','N')");
    $pdo->exec("INSERT INTO gibbonPermission (gibbonRoleID, gibbonActionID) VALUES (1, 1)");
    // a ticket made before the migration
    $pdo->exec("INSERT INTO gibbonCsTicket (csTicketTypeID, title, status, gibbonPersonIDReporter, customData) VALUES (1, 'old ticket', 'New', 1, '{\"child_name\":\"Anna\"}')");
    $before = ['types' => (int)$pdo->query('SELECT COUNT(*) FROM gibbonCsTicketType')->fetchColumn(), 'ticketCols' => count($pdo->query('SHOW COLUMNS FROM gibbonCsTicket')->fetchAll())];

    $sql = []; $count = 0;
    include dirname(__DIR__).'/CHANGEDB.php';
    $step = null;
    foreach ($sql as $s) { if ($s[0] === '0.8.0') { $step = $s; } }
    check('a 0.8.0 step exists', $step !== null);
    foreach (explode(';end', $step[1]) as $statement) {
        if (trim($statement) === '') continue;
        try { $pdo->exec($statement); } catch (Throwable $e) { check('statement runs', false, $e->getMessage().' :: '.substr(trim($statement), 0, 100)); }
    }

    $one = fn($q, $b = []) => (function () use ($pdo, $q, $b) { $st = $pdo->prepare($q); $st->execute($b); return $st->fetchColumn(); })();
    $rows = fn($q, $b = []) => (function () use ($pdo, $q, $b) { $st = $pdo->prepare($q); $st->execute($b); return $st->fetchAll(PDO::FETCH_ASSOC); })();
    $question = fn($form, $key) => $rows('SELECT * FROM gibbonCsFormField WHERE csFormID=? AND fieldKey=?', [$form, $key])[0] ?? null;

    echo "== migrated forms ==\n";
    check('one form per ticket type', (int)$one('SELECT COUNT(*) FROM gibbonCsForm') == $before['types'], $before['types']);
    $form1 = $rows('SELECT * FROM gibbonCsForm WHERE csFormID=1')[0];
    check('form keeps name, group, icon and audience of the type', $form1['name'] == 'Absence Report' && $form1['groupName'] == 'Families' && $form1['audience'] == 'Parent,Staff' && $form1['icon'] !== '', json_encode($form1));
    check('forms get a slug', $form1['slug'] == 'form-1');

    $keys = array_column($rows('SELECT fieldKey FROM gibbonCsFormField WHERE csFormID=1 ORDER BY sequenceNumber'), 'fieldKey');
    echo "  Absence form: ".implode(', ', $keys)."\n";
    check('absence: title is kept, required and mapped', ($q = $question(1, 'ticket_title')) && $q['required'] == 'Y' && $q['target'] == 'title' && $q['label'] == 'Summary');
    check('absence: description keeps its label and order', ($q = $question(1, 'ticket_description')) && $q['label'] == 'Additional information' && $q['sequenceNumber'] == 80 && $q['target'] == 'description');
    check('absence: hidden basic fields (priority, dates) were not migrated', !$question(1, 'ticket_priority') && !$question(1, 'ticket_start') && !$question(1, 'ticket_due'));
    check('absence: attachments keep label and help', ($q = $question(1, 'ticket_files')) && $q['label'] == "Doctor's note" && $q['helpText'] == 'Optional.' && $q['inputType'] == 'file' && $q['target'] == 'attachments');
    check('absence: the 6 form custom fields became questions mapped to themselves', count(array_filter($keys, fn($k) => $question(1, $k)['target'] == 'custom:'.$k)) == 6);
    check('absence: the agent-only field was not on the form', !$question(1, 'attendance_recorded'));
    check('leaving: due date is the required "Last day at school"', ($q = $question(3, 'ticket_due')) && $q['label'] == 'Last day at school' && $q['required'] == 'Y' && $q['inputType'] == 'date');
    check('registration: start date is required "Desired start date"', ($q = $question(2, 'ticket_start')) && $q['label'] == 'Desired start date' && $q['required'] == 'Y');
    check('material request: shown priority stays a priority choice', ($q = $question(6, 'ticket_priority')) && $q['inputType'] == 'select' && $q['target'] == 'priority');
    check('staff request: dates keep their custom labels', ($question(5, 'ticket_start')['label'] ?? '') == 'From' && ($question(5, 'ticket_due')['label'] ?? '') == 'Needed by');

    echo "== ticket fields and types ==\n";
    check('agent-only fields are now internal', $one("SELECT internal FROM gibbonCsField WHERE fieldKey='attendance_recorded'") == 'Y' && $one("SELECT internal FROM gibbonCsField WHERE fieldKey='child_name' AND csTicketTypeID=1") == 'N');
    check('showOnForm is gone', (int)$one("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='cs_data' AND TABLE_NAME='gibbonCsField' AND COLUMN_NAME='showOnForm'") == 0);
    check('the type lost its portal settings', (int)$one("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='cs_data' AND TABLE_NAME='gibbonCsTicketType' AND COLUMN_NAME IN ('groupName','icon','audience','formConfig')") == 0);
    check('tickets can remember their form', (int)$one("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='cs_data' AND TABLE_NAME='gibbonCsTicket' AND COLUMN_NAME='csFormID'") == 1);
    check('existing tickets are untouched', $one("SELECT title FROM gibbonCsTicket") == 'old ticket' && $one("SELECT csFormID FROM gibbonCsTicket") === null);
    check('workflow stayed on the type', !empty($one('SELECT workflow FROM gibbonCsTicketType WHERE csTicketTypeID=1')));
    check('Manage Forms action added with the permissions of Manage Ticket Types', $one("SELECT COUNT(*) FROM gibbonPermission p JOIN gibbonAction a ON a.gibbonActionID=p.gibbonActionID WHERE a.name='Manage Forms'") == 1);
    check('Manage Ticket Types no longer owns the old form pages', strpos($one("SELECT URLList FROM gibbonAction WHERE name='Manage Ticket Types'"), 'cs_form_edit.php') === false);

    echo "== every migrated question maps to something valid, and the migrated form builds a ticket ==\n";
    foreach ($rows('SELECT * FROM gibbonCsForm') as $f) {
        $typeFields = $rows('SELECT * FROM gibbonCsField WHERE csTicketTypeID=? ORDER BY sequenceNumber', [$f['csTicketTypeID']]);
        $questions = $rows('SELECT * FROM gibbonCsFormField WHERE csFormID=? ORDER BY sequenceNumber', [$f['csFormID']]);
        $bad = array_filter(array_map(fn($q) => FormMapper::validateMapping($q, $typeFields) ? $q['fieldKey'].': '.FormMapper::validateMapping($q, $typeFields) : '', $questions));
        check('form "'.$f['name'].'": all '.count($questions).' mappings valid', empty($bad), implode('; ', $bad));
    }
    $typeFields = $rows('SELECT * FROM gibbonCsField WHERE csTicketTypeID=1');
    $questions = $rows('SELECT * FROM gibbonCsFormField WHERE csFormID=1 ORDER BY sequenceNumber');
    $result = FormMapper::map($form1, $questions, $typeFields, ['ff_ticket_title' => 'Anna ill', 'ff_child_name' => 'Anna', 'ff_reason' => 'Illness', 'ff_absent_from' => '2026-11-05', 'ff_ticket_description' => 'Fever']);
    check('migrated absence form builds the ticket', $result['errors'] == [] && $result['title'] == 'Anna ill' && $result['description'] == 'Fever' && $result['customData'] == ['child_name' => 'Anna', 'reason' => 'Illness', 'absent_from' => '2026-11-05'], json_encode($result));

    echo "\n$n checks, $fail failed\n";
    exit($fail ? 1 : 0);
}
