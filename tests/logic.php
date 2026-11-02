<?php
/**
 * Runs the module's real Gateway / Service / Automation classes against the seeded MariaDB (cs_test, run tests/install.php first).
 * Usage: php tests/logic.php
 * Only Gibbon's own base classes are stubbed: QueryableGateway + TableAware (thin PDO versions), NotificationSender,
 * UserGateway. Page/process scripts are NOT exercised (they need Gibbon's page, form and session objects).
 */
namespace Gibbon\Domain { class QueryCriteria {} abstract class QueryableGateway { protected function db() { return \Stub\Db::get(); } } }
namespace Gibbon\Domain\Traits {
    trait TableAware {
        public function getTableName() { return static::$tableName; }
        public function getPrimaryKey() { return static::$primaryKey; }
        public function getByID($id, $cols = []) : array { return $this->db()->select("SELECT * FROM ".static::$tableName." WHERE ".static::$primaryKey."=:id", ['id' => $id])->fetch() ?: []; }
        public function exists($id) : bool { return !empty($this->getByID($id)); }
        public function insert(array $data) { $c = array_keys($data); return $this->db()->insert("INSERT INTO ".static::$tableName." (".implode(',', $c).") VALUES (:".implode(',:', $c).")", $data); }
        public function update($id, array $data) : bool { $set = implode(',', array_map(fn($c) => "$c=:$c", array_keys($data))); return (bool)$this->db()->update("UPDATE ".static::$tableName." SET $set WHERE ".static::$primaryKey."=:__id", $data + ['__id' => $id]); }
        public function delete($id) : bool { return $this->db()->delete("DELETE FROM ".static::$tableName." WHERE ".static::$primaryKey."=:id", ['id' => $id]) > 0; }
        public function unique(array $data, array $keys, $id = '') : bool { return true; }
    }
}
namespace Gibbon\Comms { class NotificationSender { public static $sent = []; private $q = [];
    public function addNotification($p, $t, $m, $l, $d = []) { $this->q[] = [(int)$p, $t]; return $this; }
    public function sendNotifications() { foreach ($this->q as $n) { self::$sent[] = $n; } $this->q = []; } } }
namespace Gibbon\Domain\User { class UserGateway { public function getByID($id, $cols = []) { return \Stub\Db::get()->select("SELECT * FROM gibbonPerson WHERE gibbonPersonID=:id", ['id' => $id])->fetch() ?: []; } } }
namespace Gibbon\Services { class Format { public static function date($d) { return $d ? date('d/m/Y', strtotime($d)) : ''; } public static function dateConvert($d) { return $d; } } }
namespace Stub {
    class Db { private static $i; public $pdo;
        public static function get() { return self::$i ??= new self(); }
        public function __construct() { $this->pdo = \csTestPdo('cs_test'); }
        private function run($sql, $b) { $st = $this->pdo->prepare($sql); $st->execute($b); return $st; }
        public function select($sql, $b = []) { return $this->run($sql, $b); }
        public function selectOne($sql, $b = []) { $st = $this->run($sql, $b); $v = $st->columnCount() == 1 ? $st->fetchColumn() : $st->fetch(); return $v !== false ? $v : ($st->columnCount() == 1 ? 0 : []); }
        public function insert($sql, $b = []) { $this->run($sql, $b); return $this->pdo->lastInsertId(); }
        public function update($sql, $b = []) { $this->run($sql, $b); return true; }
        public function delete($sql, $b = []) { return $this->run($sql, $b)->rowCount(); }
    }
    class Container { public function get($c) { return new $c(); } }
}
namespace {
    require __DIR__.'/bootstrap.php';
    function __($s, $p = []) { return $s; }
    spl_autoload_register(function ($c) { $p = 'Gibbon\\Module\\administration\\'; if (strpos($c, $p) === 0) { $f = dirname(__DIR__).'/src/'.str_replace('\\', '/', substr($c, strlen($p))).'.php'; if (is_file($f)) require $f; } });
    require dirname(__DIR__).'/moduleFunctions.php';

    use Gibbon\Module\administration\Domain\{TicketGateway, TicketTypeGateway, FieldGateway, FormGateway, FormFieldGateway, BoardGateway, StatusGateway, SpaceGateway, RoleGateway, TeamGateway, LinkGateway, TagGateway, WatcherGateway, ChecklistGateway, ActivityGateway, RuleGateway};
    use Gibbon\Module\administration\Service\{Workflow, FormMapper, FormAccess, BoardFilter, CustomFields, QuickCreate};
    use Gibbon\Module\administration\Automation\AutomationEngine;

    $fail = 0; $n = 0;
    function check(string $what, $ok, $detail = '') { global $fail, $n; $n++; if (!$ok) { $fail++; echo "  FAIL: $what $detail\n"; } }
    $c = new Stub\Container(); $db = Stub\Db::get()->pdo;
    $GLOBALS['container'] = $c;                       // Workflow::statusMap() reads the registry through it
    $db->exec("SET FOREIGN_KEY_CHECKS=0"); foreach (['gibbonCsTicket','gibbonCsTicketActivity','gibbonCsChecklist','gibbonCsChecklistItem','gibbonCsTicketTag','gibbonCsTag','gibbonCsTicketWatcher','gibbonCsTicketLink','gibbonCsSpaceMember','gibbonCsTeamMember'] as $t) { $db->exec("TRUNCATE $t"); }
    $db->exec("DROP TABLE IF EXISTS gibbonPerson");
    $db->exec("CREATE TABLE gibbonPerson (gibbonPersonID int(10) unsigned zerofill primary key, preferredName varchar(40), surname varchar(40), username varchar(20), status varchar(10))");
    $db->exec("INSERT INTO gibbonPerson VALUES (1,'Ada','Admin','ada','Full'),(2,'Olga','Office','olga','Full'),(3,'Paula','Parent','paula','Full'),(4,'Sam','Staff','sam','Full')");

    echo "== defaults ==\n";
    $statuses = $c->get(StatusGateway::class)->selectMap();
    check('10 statuses', count($statuses) == 10, count($statuses));
    check('Declined is Done', ($statuses['Declined'] ?? '') == 'Done');
    check('New is To Do', ($statuses['New'] ?? '') == 'To Do');
    check('Workflow::isDone uses the registry', Workflow::isDone('Declined') && Workflow::isDone('Closed') && !Workflow::isDone('Ordered') && !Workflow::isDone('New'));
    check('unknown status is In Progress', Workflow::category('Whatever') == 'In Progress');

    $types = $c->get(TicketTypeGateway::class);
    $fieldGateway = $c->get(FieldGateway::class);
    echo "== ticket types ==\n";
    check('6 active ticket types', count($types->selectActive()) == 6);
    foreach ($types->selectActive() as $t) {
        check('type '.$t['name'].': workflow decodes', !empty(Workflow::decode($t['workflow'])['transitions']));
        check('type '.$t['name'].': no portal settings any more (forms own them)', !array_key_exists('groupName', $t) && !array_key_exists('audience', $t) && !array_key_exists('formConfig', $t));
    }
    check('agent-only fields are internal', array_column(array_filter($fieldGateway->selectByType(1), fn($f) => $f['internal'] == 'Y'), 'fieldKey') == ['attendance_recorded']);

    echo "== forms: visibility (a property of the form) ==\n";
    $forms = $c->get(FormGateway::class);
    $formNames = fn($list) => implode(', ', array_map(fn($f) => $f['name'], $list));
    echo "  parent sees: ".$formNames($forms->selectVisible('Parent', 3))."\n";
    check('6 default forms', count($forms->selectAllWithCounts()) == 6);
    check('parents see the 3 family forms and the general question', count($forms->selectVisible('Parent', 3)) == 4);
    check('staff see all 6', count($forms->selectVisible('Staff', 2)) == 6);
    check('students see only the question form (open to everybody)', $formNames($forms->selectVisible('Student', 5)) == 'Ask a question');
    check('empty audience and roles = every registered user', FormAccess::allows(['audience' => null, 'roles' => null], 'Other', 99));
    $db->exec("UPDATE gibbonCsForm SET audience=NULL, roles='7,8' WHERE csFormID=6");
    check('specific roles: a listed role may use it whatever its category', in_array('Order material', array_column($forms->selectVisible('Parent', 7), 'name')));
    check('specific roles: an unlisted role may not, even in the right category', !in_array('Order material', array_column($forms->selectVisible('Staff', 9), 'name')));
    $db->exec("UPDATE gibbonCsForm SET audience='Parent', roles='9' WHERE csFormID=6");
    check('category OR role', FormAccess::allows($forms->getByID(6), 'Parent', 1) && FormAccess::allows($forms->getByID(6), 'Staff', 9) && !FormAccess::allows($forms->getByID(6), 'Staff', 1));
    $db->exec("UPDATE gibbonCsForm SET audience='Staff', roles=NULL WHERE csFormID=6");
    $db->exec("UPDATE gibbonCsForm SET active='N' WHERE csFormID=4");
    check('an inactive form is hidden', !in_array('Ask a question', array_column($forms->selectVisible('Parent', 3), 'name')));
    $db->exec("UPDATE gibbonCsForm SET active='Y' WHERE csFormID=4");
    $db->exec("UPDATE gibbonCsTicketType SET active='N' WHERE csTicketTypeID=1");
    check('a form of an inactive ticket type is hidden', !in_array('Report an absence', array_column($forms->selectVisible('Parent', 3), 'name')));
    $db->exec("UPDATE gibbonCsTicketType SET active='Y' WHERE csTicketTypeID=1");
    check('summary text', FormAccess::summary(['audience' => 'Staff', 'roles' => '7'], [7 => 'Teacher']) == 'Staff, Role: Teacher' && FormAccess::summary([], []) == 'Every registered user');
    check('shareable link: by slug and by number', $forms->getByIdOrSlug('', 'absence')['csFormID'] == 1 && $forms->getByIdOrSlug(2, '')['slug'] == 'registration' && $forms->getByIdOrSlug('', 'nope') == []);

    echo "== forms: questions and their mapping to the ticket ==\n";
    $questionsOf = fn($id) => $c->get(FormFieldGateway::class)->selectByForm($id);
    foreach ($forms->selectAllWithCounts() as $f) {
        $typeFields = $fieldGateway->selectByType($f['csTicketTypeID']);
        $questions = $questionsOf($f['csFormID']);
        $problems = array_filter(array_map(fn($q) => FormMapper::validateMapping($q, $typeFields) ? $q['fieldKey'] : '', $questions));
        $keys = array_column($questions, 'fieldKey');
        check('form '.$f['name'].': '.count($questions).' questions, every mapping can hold its answer', empty($problems), implode(',', $problems));
        check('form '.$f['name'].': a title source (a question or the template)', in_array('title', array_column($questions, 'target'), true) || !empty($f['titleTemplate']));
        check('form '.$f['name'].': unique keys', count($keys) == count(array_unique($keys)));
        echo "  {$f['name']} ({$f['typeName']}): ".implode(' | ', array_map(fn($q) => $q['label'].($q['required'] == 'Y' ? '*' : '').' -> '.FormMapper::targetLabel($q['target'], $typeFields), $questions))."\n";
    }
    $absType = $fieldGateway->selectByType(1);
    $absQuestions = $questionsOf(1);
    $absForm = $forms->getByID(1);

    $r = FormMapper::map($absForm, $absQuestions, $absType, ['ff_child_name' => 'Anna Meier', 'ff_class_name' => '3b', 'ff_absent_from' => '2026-11-05', 'ff_reason' => 'Illness', 'ff_details' => 'Fever since Monday'], ['doctors_note']);
    check('absence: no errors', $r['errors'] == [], json_encode($r['errors']));
    check('absence: the title comes from the template', $r['title'] == 'Absence Anna Meier from 05/11/2026', $r['title']);
    check('absence: one long text alone becomes the description as it is', $r['description'] == 'Fever since Monday');
    check('absence: answers land in the ticket fields they are mapped to', $r['customData'] == ['child_name' => 'Anna Meier', 'class_name' => '3b', 'absent_from' => '2026-11-05', 'reason' => 'Illness'], json_encode($r['customData']));
    check('absence: priority defaults to Medium, no dates', $r['priority'] == 'Medium' && $r['dateStart'] === null && $r['dateDue'] === null);
    $r = FormMapper::map($absForm, $absQuestions, $absType, ['ff_child_name' => 'Anna', 'ff_absent_from' => '2026-11-05', 'ff_reason' => 'Hacker']);
    check('a value that is not an option counts as empty (required -> error)', in_array('Reason', $r['errors']), json_encode($r['errors']));
    $r = FormMapper::map($absForm, $absQuestions, $absType, ['ff_absent_from' => '2026-11-05', 'ff_reason' => 'Illness']);
    check('required questions are reported by label', $r['errors'] == ["Child's name"], json_encode($r['errors']));
    $r = FormMapper::map($absForm, $absQuestions, $absType, ['ff_child_name' => 'A', 'ff_absent_from' => '2026-13-45', 'ff_reason' => 'Illness']);
    check('an invalid date is refused', in_array('Absent from', $r['errors']));
    $r = FormMapper::map($absForm, $absQuestions, $absType, ['ff_child_name' => 'A', 'ff_absent_from' => '2026-11-05', 'ff_reason' => 'Illness'], []);
    check('an optional file question may stay empty', $r['errors'] == []);

    // Several questions mapped to the description; choices and checkboxes are written as text
    $regForm = $forms->getByID(2); $regType = $fieldGateway->selectByType(2); $regQuestions = $questionsOf(2);
    $r = FormMapper::map($regForm, $regQuestions, $regType, ['ff_child_first_name' => 'Ben', 'ff_child_last_name' => 'Mayer', 'ff_birth_date' => '2020-02-02', 'ff_year_group' => '1', 'ff_parent_phone' => '555',
        'ff_desired_start' => '2027-08-15', 'ff_interests' => ['After-school care', 'School bus', 'Hacker'], 'ff_remarks' => 'Needs a ramp', 'ff_siblings_at_school' => 'Y']);
    check('registration: no errors', $r['errors'] == [], json_encode($r['errors']));
    check('registration: title from the template', $r['title'] == 'Registration Ben Mayer', $r['title']);
    check('registration: two questions go to the description as "Question: answer" lines, invalid choice dropped', $r['description'] == "Interested in: After-school care, School bus\nAnything we should know?: Needs a ramp", json_encode($r['description']));
    check('registration: start date and yes/no field', $r['dateStart'] == '2027-08-15' && $r['customData']['siblings_at_school'] == 'Y');
    $legForm = $forms->getByID(3); $legType = $fieldGateway->selectByType(3);
    $r = FormMapper::map($legForm, $questionsOf(3), $legType, ['ff_child_name' => 'Carl', 'ff_reason' => 'Moving away', 'ff_documents_needed' => ['Report card', 'Transfer form'], 'ff_last_day' => '2027-06-30']);
    check('leaving: checkboxes into a checkboxes ticket field, last day into the due date', $r['customData']['documents_needed'] == ['Report card', 'Transfer form'] && $r['dateDue'] == '2027-06-30' && $r['title'] == 'Leaving Carl', json_encode($r));
    $genForm = $forms->getByID(4); $genType = $fieldGateway->selectByType(4);
    $r = FormMapper::map($genForm, $questionsOf(4), $genType, ['ff_subject' => 'School bus times', 'ff_topic' => 'Schedule and calendar', 'ff_question' => "Line 1\nLine 2"]);
    check('general question: subject -> title, question -> description, topic -> ticket field', $r['title'] == 'School bus times' && $r['description'] == "Line 1\nLine 2" && $r['customData'] == ['topic' => 'Schedule and calendar'], json_encode($r));
    $matForm = $forms->getByID(6); $matType = $fieldGateway->selectByType(6);
    $r = FormMapper::map($matForm, $questionsOf(6), $matType, ['ff_item' => 'Chalk', 'ff_quantity' => '3', 'ff_needed_by' => '2026-12-01', 'ff_urgency' => 'High', 'ff_reason' => 'Run out']);
    check('material: priority question, number field, template with a number', $r['priority'] == 'High' && $r['customData']['quantity'] === 3 && $r['title'] == 'Material: Chalk (3)', json_encode($r));
    $r = FormMapper::map($matForm, $questionsOf(6), $matType, ['ff_item' => 'Chalk', 'ff_quantity' => 'many', 'ff_needed_by' => '2026-12-01', 'ff_urgency' => 'Urgent!!']);
    check('material: text in a number question is refused, a wrong priority falls back to Medium', in_array('Quantity', $r['errors']) && $r['priority'] == 'Medium', json_encode($r));
    $r = FormMapper::map(['name' => 'X'] , [['fieldKey' => 'a', 'label' => 'From', 'inputType' => 'date', 'options' => '', 'required' => 'N', 'target' => 'dateStart'], ['fieldKey' => 'b', 'label' => 'To', 'inputType' => 'date', 'options' => '', 'required' => 'N', 'target' => 'dateDue']], [], ['ff_a' => '2026-12-10', 'ff_b' => '2026-12-01']);
    check('a due date before the start date is refused', in_array('Dates', $r['errors']));

    // Grouping: several questions into one ticket field
    $qs = [
        ['fieldKey' => 'q1', 'label' => 'First name', 'inputType' => 'varchar', 'options' => '', 'required' => 'N', 'target' => 'title'],
        ['fieldKey' => 'q2', 'label' => 'Last name', 'inputType' => 'varchar', 'options' => '', 'required' => 'N', 'target' => 'title'],
        ['fieldKey' => 'q3', 'label' => 'Colour', 'inputType' => 'radio', 'options' => 'Red,Blue', 'required' => 'N', 'target' => 'description'],
        ['fieldKey' => 'q4', 'label' => 'Happy?', 'inputType' => 'yesno', 'options' => '', 'required' => 'N', 'target' => 'description'],
        ['fieldKey' => 'q5', 'label' => 'Note A', 'inputType' => 'varchar', 'options' => '', 'required' => 'N', 'target' => 'custom:child_name'],
        ['fieldKey' => 'q6', 'label' => 'Note B', 'inputType' => 'varchar', 'options' => '', 'required' => 'N', 'target' => 'custom:child_name'],
        ['fieldKey' => 'q7', 'label' => 'Unknown', 'inputType' => 'varchar', 'options' => '', 'required' => 'N', 'target' => 'custom:does_not_exist'],
        ['fieldKey' => 'q8', 'label' => 'Agents only', 'inputType' => 'varchar', 'options' => '', 'required' => 'N', 'target' => 'custom:attendance_recorded'],
        ['fieldKey' => 'q9', 'label' => 'Read this', 'inputType' => 'info', 'options' => 'Some text', 'required' => 'N', 'target' => ''],
    ];
    $r = FormMapper::map(['name' => 'Group test'], $qs, $absType, ['ff_q1' => 'Ada', 'ff_q2' => 'Lovelace', 'ff_q3' => 'Blue', 'ff_q4' => 'Y', 'ff_q5' => 'one', 'ff_q6' => 'two', 'ff_q7' => 'x', 'ff_q8' => 'y']);
    check('grouping: two questions into the title', $r['title'] == 'Ada - Lovelace', $r['title']);
    check('grouping: a radio and a yes/no into the description as text', $r['description'] == "Colour: Blue\nHappy?: Yes", json_encode($r['description']));
    check('grouping: two text answers into one ticket field, one under the other', $r['customData']['child_name'] == "one\ntwo", json_encode($r['customData']));
    check('answers for a missing or agents-only ticket field are dropped, information text is skipped', array_keys($r['customData']) == ['child_name'], json_encode($r['customData']));
    $r = FormMapper::map(['name' => 'Fallback form', 'titleTemplate' => ''], [], [], []);
    check('no title source: the name of the form', $r['title'] == 'Fallback form');

    // Validation of a mapping when a question is saved
    $v = fn($input, $options, $target) => FormMapper::validateMapping(['inputType' => $input, 'options' => $options, 'target' => $target], $absType);
    check('mapping: text to description is fine', $v('varchar', '', 'description') === '');
    check('mapping: a date input to a date field', $v('date', '', 'dateDue') === '' && $v('varchar', '', 'dateDue') !== '');
    check('mapping: a date input to a custom date field, not to a select field', $v('date', '', 'custom:absent_from') === '' && $v('date', '', 'custom:reason') !== '');
    check('mapping: options must be a subset of the ticket field options', $v('select', 'Illness,Other', 'custom:reason') === '' && $v('select', 'Illness,Mystery', 'custom:reason') !== '');
    check('mapping: priority needs priority options', $v('select', 'Low,High', 'priority') === '' && $v('select', 'Low,Pink', 'priority') !== '' && $v('varchar', '', 'priority') !== '');
    check('mapping: a file only fills in the attachments, and attachments only a file', $v('file', '', 'attachments') === '' && $v('file', '', 'description') !== '' && $v('varchar', '', 'attachments') !== '');
    check('mapping: information text is mapped to nothing', $v('info', '', '') === '' && $v('info', '', 'description') !== '');
    check('mapping: a choice needs options', $v('select', '', 'description') !== '');
    check('mapping: an internal or unknown ticket field cannot be chosen', $v('yesno', '', 'custom:attendance_recorded') !== '' && $v('varchar', '', 'custom:nope') !== '');

    // Starting a form from the ticket type, and bringing it up to date
    $suggestAll = FormMapper::suggest($absType, [], true);
    check('suggest for a new form: 6 basic fields + the 6 non-internal ticket fields', count($suggestAll) == 12 && !in_array('custom:attendance_recorded', array_column($suggestAll, 'target')), count($suggestAll));
    check('suggest: nothing missing when the form covers the ticket type', FormMapper::suggest($absType, $absQuestions, false) == []);
    $fieldGateway->insert(['csTicketTypeID' => 1, 'fieldKey' => 'transport', 'label' => 'Transport', 'fieldType' => 'select', 'options' => 'Bus,Car', 'required' => 'N', 'internal' => 'N', 'sequenceNumber' => 80]);
    $sync = FormMapper::suggest($fieldGateway->selectByType(1), $absQuestions, false);
    check('suggest: a field added to the type later is offered', count($sync) == 1 && $sync[0]['target'] == 'custom:transport' && $sync[0]['inputType'] == 'select' && $sync[0]['options'] == 'Bus,Car');
    check('the suggested question is a valid mapping', FormMapper::validateMapping($sync[0], $fieldGateway->selectByType(1)) === '');
    $c->get(FormFieldGateway::class)->addQuestions(1, $sync);
    check('added to the form with the next order number', count($questionsOf(1)) == count($absQuestions) + 1 && FormMapper::suggest($fieldGateway->selectByType(1), $questionsOf(1), false) == []);

    // A ticket built by a form remembers the form; deleting the form keeps the ticket
    $r = FormMapper::map($absForm, $absQuestions, $absType, ['ff_child_name' => 'Zoe', 'ff_absent_from' => '2026-11-06', 'ff_reason' => 'Other']);
    $built = (int)$c->get(TicketGateway::class)->createTicket(['csTicketTypeID' => 1, 'csFormID' => 1, 'title' => $r['title'], 'description' => $r['description'], 'status' => 'New', 'priority' => $r['priority'], 'gibbonPersonIDReporter' => 3, 'customData' => $r['customData']]);
    check('the ticket stores the form it came from', (int)$c->get(TicketGateway::class)->getTicket($built)['csFormID'] == 1);
    $db->exec("INSERT INTO gibbonCsForm (csFormID, csTicketTypeID, name, slug) VALUES (99, 1, 'Temp', 'temp')");
    $db->exec("INSERT INTO gibbonCsFormField (csFormID, fieldKey, label, target) VALUES (99, 'a', 'A', 'description')");
    $db->exec("UPDATE gibbonCsTicket SET csFormID=99 WHERE csTicketID=$built");
    check('deleting a form removes its questions, keeps the tickets', $forms->deleteForm(99) && (int)$db->query("SELECT COUNT(*) FROM gibbonCsFormField WHERE csFormID=99")->fetchColumn() == 0
        && $c->get(TicketGateway::class)->getTicket($built)['csFormID'] === null && !empty($c->get(TicketGateway::class)->getTicket($built)));
    $db->exec("DELETE FROM gibbonCsTicket WHERE csTicketID=$built");
    $db->exec("DELETE FROM gibbonCsField WHERE fieldKey='transport'"); $db->exec("DELETE FROM gibbonCsFormField WHERE csFormID=1 AND fieldKey='transport'");

    echo "== tickets, boards, categories ==\n";
    $tickets = $c->get(TicketGateway::class);
    $mk = fn($type, $title, $status, $extra = []) => $tickets->createTicket($extra + ['csTicketTypeID' => $type, 'title' => $title, 'status' => $status, 'gibbonPersonIDReporter' => 3, 'csTeamID' => $types->getByID($type)['csTeamID'], 'customData' => ['child_name' => 'Anna']]);
    $t1 = $mk(1, 'Anna ill', 'New');
    $t2 = $mk(2, 'Registration Ben', 'Documents Requested', ['dateStart' => date('Y-m-d', strtotime('+30 days'))]);
    $t3 = $mk(3, 'Leaving Carl', 'New', ['dateDue' => date('Y-m-d', strtotime('-2 days'))]);
    $t4 = $mk(4, 'Question', 'Declined');
    $t5 = $mk(6, 'Chalk', 'Ordered', ['gibbonPersonIDReporter' => 4]);
    $boards = $c->get(BoardGateway::class);
    $expect = [1 => 5, 2 => 1, 3 => 1, 4 => 1, 5 => 1, 6 => 1];
    foreach ($boards->selectAccessible(1, true) as $b) {
        $got = $tickets->selectByFilter($b['filter']);
        echo "  board {$b['name']}: ".count($got)." ticket(s)\n";
        check('board '.$b['name'], count($got) == $expect[$b['csBoardID']], count($got));
        $cols = $boards->selectColumns($b['csBoardID']);
        check('board '.$b['name'].' columns have categories', count($cols) > 0 && !array_filter($cols, fn($x) => !in_array($x['category'], Workflow::CATEGORIES)));
    }
    $all = $boards->selectColumns(1);
    echo "  All requests columns: ".implode(', ', array_map(fn($x) => $x['name'].' ['.$x['category'].']', $all))."\n";
    check('generic board shows every status', count($all) == 10);
    check('category filter: Done', count($tickets->selectByFilter([['field'=>'category','op'=>'equals','value'=>'Done']])) == 1);
    check('category filter: not Done', count($tickets->selectByFilter([['field'=>'category','op'=>'not_equals','value'=>'Done']])) == 4);
    check('category filter: To Do', count($tickets->selectByFilter([['field'=>'category','op'=>'equals','value'=>'To Do']])) == 2);
    check('overdue flag = leaving ticket', array_column($tickets->selectByFilter([['field'=>'overdue','op'=>'equals','value'=>'Y']]), 'csTicketID') == [$t3]);
    check('custom field filter on a type that has it', count($tickets->selectByFilter([['field'=>'child_name','op'=>'equals','value'=>'Anna']])) == 5);
    check('custom field a type lacks never errors', is_array($tickets->selectByFilter([['field'=>'no_such_field','op'=>'equals','value'=>'x']])));
    check('team filter', count($tickets->selectByFilter([['field'=>'team','op'=>'equals','value'=>'Staff Services']])) == 1);
    check('due before +3 (leaving is overdue)', in_array($t3, array_column($tickets->selectByFilter([['field'=>'due','op'=>'before','value'=>'+3']]), 'csTicketID')));

    echo "== queries ==\n";
    check('open subtasks counts by category', $tickets->countOpenSubtasks($t1) == 0);
    $sub = $mk(1, 'sub', 'Declined', ['csTicketIDParent' => $t1]); $sub2 = $mk(1, 'sub2', 'In Progress', ['csTicketIDParent' => $t1]);
    check('1 open subtask (Declined counts as done)', $tickets->countOpenSubtasks($t1) == 1, $tickets->countOpenSubtasks($t1));
    check('subtask progress 1/2', $tickets->selectSubtaskProgress([$t1])[$t1] == [1, 2], json_encode($tickets->selectSubtaskProgress([$t1])));
    $links = $c->get(LinkGateway::class);
    check('link blocks', $links->addLink($t2, $t3, 'blocks', 1) && !$links->addLink($t3, $t2, 'blocks', 1));
    check('t3 is blocked by the open t2', count($links->selectOpenBlockers($t3)) == 1 && $links->selectBlockedIDs([$t3, $t1]) == [$t3]);
    $tickets->updateTicket($t2, ['status' => 'Declined']);
    check('a Declined (Done) blocker no longer blocks', count($links->selectOpenBlockers($t3)) == 0);
    check('due soon / overdue selection', in_array($t3, array_column($tickets->selectOverdue(), 'csTicketID')) && empty($tickets->selectDueSoon(1)));
    check('reporter list open only (Declined and Closed are Done)', count($tickets->selectByReporter(3, true)) == 3, count($tickets->selectByReporter(3, true)));
    $tags = $c->get(TagGateway::class);
    check('tags', $tags->addToTicket($t1, TagGateway::parse('VIP, urgent,vip')) == ['VIP', 'urgent'] && count($tags->selectByTicket($t1)) == 2);
    check('tag filter', array_column($tickets->selectByFilter([['field'=>'tag','op'=>'equals','value'=>'vip']]), 'csTicketID') == [$t1]);
    $w = $c->get(WatcherGateway::class); $w->add($t1, 4);
    check('watchers', $w->isWatching($t1, 4) && $w->selectWithNames($t1) == [4 => 'Sam Staff']);
    $cl = $c->get(ChecklistGateway::class); $lid = $cl->addChecklist($t1, 'Steps'); $i1 = $cl->addItem($lid, 'a'); $i2 = $cl->addItem($lid, 'b'); $cl->setDone($lid, [$i1], 1);
    check('checklist progress 1/2', $cl->progress($t1) == [1, 2], json_encode($cl->progress($t1)));

    echo "== permissions / access ==\n";
    $sp = $c->get(SpaceGateway::class);
    $sp->addMember(1, 2, 2); $sp->addMember(1, 4, 3);
    check('agent holds transition_ticket, viewer does not', $sp->hasPermission(2, 1, 'transition_ticket') && !$sp->hasPermission(4, 1, 'transition_ticket'));
    check('member sees the boards, stranger none', count($boards->selectAccessible(2)) == 6 && count($boards->selectAccessible(3)) == 0);
    check('people with permission', $sp->selectPeopleWithPermission([1], 'assign_ticket') == [2]);

    echo "== automation ==\n";
    $before = count($cl->selectByTicket($t1));
    $reg = $mk(2, 'Registration Dora', 'New');
    $ran = (new AutomationEngine($c))->fire('ticket_created', $reg);
    $lists = $cl->selectByTicket($reg);
    check('registration rule ran and added its checklist', $ran == 1 && count($lists) == 1 && $lists[0]['title'] == 'Registration steps' && count($lists[0]['items']) == 5, "ran=$ran");
    $leave = $mk(3, 'Leaving Emil', 'New'); (new AutomationEngine($c))->fire('ticket_created', $leave);
    check('leaving rule added its checklist', ($cl->selectByTicket($leave)[0]['title'] ?? '') == 'Leaving checklist');
    $abs = $mk(1, 'Absence', 'New');
    check('no rule for absence reports', (new AutomationEngine($c))->fire('ticket_created', $abs) == 0);
    $hist = $c->get(ActivityGateway::class)->selectByTicket($reg, true);
    check('automation wrote an internal history event', count($hist) == 1 && $hist[0]['visibility'] == 'Internal', json_encode($hist));
    check('the requester does not see it', count($c->get(ActivityGateway::class)->selectByTicket($reg, false)) == 0);

    echo "== quick create ==\n";
    // users: 1 admin (not a member), 2 agent in the space (edit + assign), 4 viewer, 3 parent
    check('admin may quick create', QuickCreate::canUse($c, 1, true));
    check('agent may quick create', QuickCreate::canUse($c, 2, false));
    check('viewer may not (no edit_ticket)', !QuickCreate::canUse($c, 4, false));
    check('parent may not', !QuickCreate::canUse($c, 3, false));
    check('agent can assign to the people of the space', QuickCreate::assignees($c, 2, false) == [2 => 'Olga Office', 4 => 'Sam Staff'], json_encode(QuickCreate::assignees($c, 2, false)));
    check('viewer can assign nobody', QuickCreate::assignees($c, 4, false) == []);
    $typeOptions = QuickCreate::typeOptions($c);
    check('quick create offers every active ticket type', count($typeOptions) == 6 && in_array('Absence Report', $typeOptions));

    Gibbon\Comms\NotificationSender::$sent = [];
    [$q1, $r1] = QuickCreate::create($c, 2, false, ['csTicketTypeID' => 1, 'title' => '  Phone call from Anna  ', 'summary' => 'Anna is ill until Friday', 'gibbonPersonIDAssignee' => 4]);
    $row = $tickets->getTicket($q1);
    check('quick create returns success and an id', $r1 == 'success0' && $q1 > 0, "$r1 $q1");
    check('title trimmed, summary is the description', $row['title'] == 'Phone call from Anna' && $row['description'] == 'Anna is ill until Friday');
    check('new request defaults: New, Medium, team of the type, reporter = creator', $row['status'] == 'New' && $row['priority'] == 'Medium' && $row['csTeamID'] == 1 && $row['gibbonPersonIDReporter'] == 2);
    check('assigned as asked', (int)$row['gibbonPersonIDAssignee'] == 4);
    check('it shows on the Absences board and on the generic board', in_array($q1, array_column($tickets->selectByFilter($boards->getByID(2)['filter']), 'csTicketID')) && in_array($q1, array_column($tickets->selectByFilter(null), 'csTicketID')));
    $events = array_column($c->get(ActivityGateway::class)->selectByTicket($q1, true), 'body');
    check('history says it was quick created and assigned', in_array('Request raised (quick create)', $events) && in_array('Assignee changed from Unassigned to Sam Staff', $events), json_encode($events));
    check('the assignee was notified once', count(array_filter(Gibbon\Comms\NotificationSender::$sent, fn($x) => $x[0] == 4)) == 1, json_encode(Gibbon\Comms\NotificationSender::$sent));

    [$q2, $r2] = QuickCreate::create($c, 2, false, ['csTicketTypeID' => 4, 'title' => 'No assignee']);
    check('assignee is optional; the team of the type is used', $r2 == 'success0' && empty($tickets->getTicket($q2)['gibbonPersonIDAssignee']) && $tickets->getTicket($q2)['csTeamID'] == 1);
    [$q3, $r3] = QuickCreate::create($c, 2, false, ['csTicketTypeID' => 2, 'title' => 'Registration by phone']);
    check('the type rules run (registration checklist)', $r3 == 'success0' && count($cl->selectByTicket($q3)) == 1);

    $count1 = (int)$db->query("SELECT COUNT(*) FROM gibbonCsTicket")->fetchColumn();
    check('a viewer cannot create', QuickCreate::create($c, 4, false, ['csTicketTypeID' => 1, 'title' => 'x']) == [0, 'error0']);
    check('a parent cannot create', QuickCreate::create($c, 3, false, ['csTicketTypeID' => 1, 'title' => 'x']) == [0, 'error0']);
    check('empty title is refused', QuickCreate::create($c, 2, false, ['csTicketTypeID' => 1, 'title' => '   ']) == [0, 'error1']);
    check('unknown type is refused', QuickCreate::create($c, 2, false, ['csTicketTypeID' => 999, 'title' => 'x']) == [0, 'error1']);
    check('somebody outside the space cannot be assigned', QuickCreate::create($c, 2, false, ['csTicketTypeID' => 1, 'title' => 'x', 'gibbonPersonIDAssignee' => 3]) == [0, 'error0']);
    check('a user who may not assign cannot slip an assignee in', QuickCreate::create($c, 4, false, ['csTicketTypeID' => 1, 'title' => 'x', 'gibbonPersonIDAssignee' => 2])[1] == 'error0');
    $db->exec("UPDATE gibbonCsTicketType SET active='N' WHERE csTicketTypeID=5");
    check('an inactive type is refused', QuickCreate::create($c, 1, true, ['csTicketTypeID' => 5, 'title' => 'x']) == [0, 'error1']);
    $db->exec("UPDATE gibbonCsTicketType SET active='Y' WHERE csTicketTypeID=5");
    check('nothing was created by the refused attempts', (int)$db->query("SELECT COUNT(*) FROM gibbonCsTicket")->fetchColumn() == $count1);
    [$q4, $r4] = QuickCreate::create($c, 1, true, ['csTicketTypeID' => 6, 'title' => 'Admin creates', 'gibbonPersonIDAssignee' => 2]);
    check('admin may create and assign across spaces', $r4 == 'success0' && (int)$tickets->getTicket($q4)['gibbonPersonIDAssignee'] == 2);
    $db->exec("INSERT INTO gibbonCsTeamMember (csTeamID, gibbonPersonID) VALUES (3, 3)");
    check('members of the type team can be assigned too', QuickCreate::create($c, 2, false, ['csTicketTypeID' => 5, 'title' => 'Team member', 'gibbonPersonIDAssignee' => 3])[1] == 'success0');

    echo "\n$n checks, $fail failed\n";
    exit($fail ? 1 : 0);
}
