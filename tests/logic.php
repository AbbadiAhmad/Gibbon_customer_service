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

    use Gibbon\Module\administration\Domain\{TicketGateway, TicketTypeGateway, FieldGateway, BoardGateway, StatusGateway, SpaceGateway, RoleGateway, TeamGateway, LinkGateway, TagGateway, WatcherGateway, ChecklistGateway, ActivityGateway, RuleGateway};
    use Gibbon\Module\administration\Service\{Workflow, FormConfig, BoardFilter, CustomFields, QuickCreate};
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
    $names = fn($list) => implode(',', array_map(fn($t) => $t['name'], $list));
    echo "  parent sees: ".$names($types->selectForAudience('Parent'))."\n";
    check('parents see 4 family types', count($types->selectForAudience('Parent')) == 4);
    check('staff see all 6', count($types->selectForAudience('Staff')) == 6);
    check('students see only the general question', $names($types->selectForAudience('Student')) == 'General Question');

    echo "== forms ==\n";
    foreach ($types->selectActive() as $t) {
        $items = FormConfig::items($t['formConfig'], $c->get(FieldGateway::class)->selectByType($t['csTicketTypeID']));
        $line = implode(' | ', array_map(fn($i) => $i['kind'] == 'core' ? $i['label'].($i['required'] ? '*' : '') : $i['field']['label'].($i['field']['required'] == 'Y' ? '*' : ''), $items));
        echo "  {$t['name']}: $line\n";
        check($t['name'].' form starts with the summary', $items[0]['key'] == 'title');
        check($t['name'].' workflow decodes', !empty(Workflow::decode($t['workflow'])['transitions']));
    }
    $abs = $types->getByID(1); $absItems = FormConfig::items($abs['formConfig'], $c->get(FieldGateway::class)->selectByType(1));
    check('absence form has no priority / dates', empty(array_filter($absItems, fn($i) => in_array($i['key'], ['priority','dateStart','dateDue']))));
    check('agent-only field hidden from the absence form', empty(array_filter($absItems, fn($i) => $i['key'] == 'attendance_recorded')));
    $leave = FormConfig::items($types->getByID(3)['formConfig'], $c->get(FieldGateway::class)->selectByType(3));
    check('leaving form asks the last day (due date) as required', (bool)array_filter($leave, fn($i) => $i['key'] == 'dateDue' && $i['label'] == 'Last day at school' && $i['required']));

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
    check('type options are grouped by portal group', isset($typeOptions['Families'], $typeOptions['Staff']) && count($typeOptions['Families']) == 4 && count($typeOptions['Staff']) == 2);

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
