<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\CustomerService\Install;

/**
 * The data a fresh installation starts with: a school office (space), teams, request types for families and staff
 * with their forms and workflows, boards for them and a generic board with every status.
 *
 * This is the only place that describes the defaults. manifest.php turns it into INSERT statements
 * (plain PHP, no Gibbon classes: it runs inside the module installer).
 *
 * Icons are BMP symbols on purpose: Gibbon talks to MySQL with charset utf8 (3 bytes), 4-byte emoji would not save.
 * Names used here (statuses, team and type names) are referenced by boards and workflows: keep them in sync.
 */
class DefaultData
{
    /** Status registry: name => category. */
    public static function statuses(): array
    {
        return [
            'New' => 'To Do',
            'In Progress' => 'In Progress', 'Waiting' => 'In Progress', 'Documents Requested' => 'In Progress',
            'Interview Planned' => 'In Progress', 'Awaiting Approval' => 'In Progress', 'Ordered' => 'In Progress',
            'Resolved' => 'Done', 'Declined' => 'Done', 'Closed' => 'Done',
        ];
    }

    public static function roles(): array
    {
        return [
            [1, 'Space Admin', 'Full control of a space: members, boards and all tickets', ['view_board', 'comment', 'edit_ticket', 'transition_ticket', 'assign_ticket', 'delete_ticket', 'manage_boards', 'manage_members']],
            [2, 'Agent', 'Triages and works on tickets (e.g. the front office)', ['view_board', 'comment', 'edit_ticket', 'transition_ticket', 'assign_ticket']],
            [3, 'Viewer', 'Can see the boards of the space and comment', ['view_board', 'comment']],
        ];
    }

    public static function teams(): array
    {
        return [
            [1, 'Front Office', 'First line (L1): triages every incoming request, answers questions and records absences'],
            [2, 'Admissions', 'Handles registrations and leaving requests'],
            [3, 'Staff Services', 'Handles requests of employees and material orders'],
        ];
    }

    /**
     * Request types. The workflow lists the allowed moves per status (a status without a line may move anywhere);
     * Done statuses always ask for a resolution note. formConfig changes the basic fields of the portal form.
     */
    public static function types(): array
    {
        $reopenAndClose = ['Resolved' => ['In Progress', 'Closed']];

        return [
            [
                'id' => 1, 'name' => 'Absence Report', 'description' => 'Tell us that your child will be absent or late.',
                'team' => 1, 'group' => 'Families', 'icon' => "\u{271A}", 'audience' => 'Parent,Staff',
                'workflow' => ['transitions' => ['New' => ['In Progress', 'Resolved'], 'In Progress' => ['Resolved']] + $reopenAndClose],
                'formConfig' => [
                    'title' => ['label' => 'Summary', 'help' => 'For example: Anna - ill on 3 November', 'order' => 1],
                    'description' => ['label' => 'Additional information', 'order' => 80],
                    'priority' => ['show' => false], 'dateStart' => ['show' => false], 'dateDue' => ['show' => false],
                    'attachments' => ['label' => "Doctor's note", 'help' => 'Optional.'],
                ],
                'fields' => [
                    ['child_name', "Child's name", 'varchar', '100', 'Y', 'Y', '', 10],
                    ['class_name', 'Class', 'varchar', '50', 'N', 'Y', '', 20],
                    ['absent_from', 'Absent from', 'date', '', 'Y', 'Y', '', 30],
                    ['absent_until', 'Absent until', 'date', '', 'N', 'Y', "Leave empty if you don't know yet.", 40],
                    ['reason', 'Reason', 'select', 'Illness,Medical appointment,Family reasons,Other', 'Y', 'Y', '', 50],
                    ['contact_phone', 'Phone number where we can reach you', 'varchar', '30', 'N', 'Y', '', 60],
                    ['attendance_recorded', 'Entered in the attendance register', 'yesno', '', 'N', 'N', 'For the office only.', 70],
                ],
            ],
            [
                'id' => 2, 'name' => 'School Registration', 'description' => 'Register a child at our school.',
                'team' => 2, 'group' => 'Families', 'icon' => "\u{270E}", 'audience' => 'Parent,Staff',
                'workflow' => [
                    'transitions' => [
                        'New' => ['Documents Requested', 'Interview Planned', 'Waiting', 'Declined'],
                        'Documents Requested' => ['Interview Planned', 'Waiting', 'Declined'],
                        'Waiting' => ['Documents Requested', 'Interview Planned', 'Declined'],
                        'Interview Planned' => ['Waiting', 'Resolved', 'Declined'],
                        'Resolved' => ['Closed'], 'Declined' => ['Closed'],
                    ],
                    'commentRequired' => ['Waiting'],
                ],
                'formConfig' => [
                    'title' => ['label' => 'Summary', 'help' => "For example: Registration of Anna Meier"],
                    'description' => ['label' => 'Anything we should know?', 'order' => 100],
                    'priority' => ['show' => false], 'dateDue' => ['show' => false],
                    'dateStart' => ['label' => 'Desired start date', 'help' => 'When should your child start with us?', 'required' => true, 'order' => 95],
                    'attachments' => ['label' => 'Documents', 'help' => 'Birth certificate, last report card, vaccination record...'],
                ],
                'fields' => [
                    ['child_first_name', "Child's first name", 'varchar', '100', 'Y', 'Y', '', 10],
                    ['child_last_name', "Child's last name", 'varchar', '100', 'Y', 'Y', '', 20],
                    ['birth_date', 'Date of birth', 'date', '', 'Y', 'Y', '', 30],
                    ['year_group', 'Year / grade applying for', 'varchar', '50', 'Y', 'Y', '', 40],
                    ['previous_school', 'Previous school', 'varchar', '150', 'N', 'Y', '', 50],
                    ['siblings_at_school', 'Does your child have siblings at our school?', 'yesno', '', 'N', 'Y', '', 60],
                    ['parent_phone', 'Phone number', 'varchar', '30', 'Y', 'Y', '', 70],
                    ['documents_complete', 'All documents received', 'yesno', '', 'N', 'N', 'For the office only.', 80],
                    ['interview_date', 'Interview date', 'date', '', 'N', 'N', 'For the office only.', 90],
                ],
            ],
            [
                'id' => 3, 'name' => 'School Leaving', 'description' => 'Tell us that your child is leaving the school.',
                'team' => 2, 'group' => 'Families', 'icon' => "\u{279C}", 'audience' => 'Parent,Staff',
                'workflow' => [
                    'transitions' => [
                        'New' => ['In Progress', 'Waiting'], 'In Progress' => ['Waiting', 'Resolved'], 'Waiting' => ['In Progress', 'Resolved'],
                        'Resolved' => ['In Progress', 'Closed'],
                    ],
                ],
                'formConfig' => [
                    'title' => ['label' => 'Summary', 'help' => 'For example: Leaving of Anna Meier'],
                    'description' => ['label' => 'Anything we should know?', 'order' => 100],
                    'priority' => ['show' => false], 'dateStart' => ['show' => false],
                    'dateDue' => ['label' => 'Last day at school', 'help' => 'The last day your child attends.', 'required' => true, 'order' => 95],
                ],
                'fields' => [
                    ['child_name', "Child's name", 'varchar', '100', 'Y', 'Y', '', 10],
                    ['class_name', 'Class', 'varchar', '50', 'N', 'Y', '', 20],
                    ['reason', 'Reason for leaving', 'select', 'Moving away,Changing school,Other', 'Y', 'Y', '', 30],
                    ['new_school', 'New school (if known)', 'varchar', '150', 'N', 'Y', '', 40],
                    ['documents_needed', 'Documents you need from us', 'checkboxes', 'Report card,Certificate of attendance,Transfer form', 'N', 'Y', '', 50],
                    ['fees_settled', 'Open fees settled', 'yesno', '', 'N', 'N', 'For the office only.', 60],
                    ['library_returned', 'Books and materials returned', 'yesno', '', 'N', 'N', 'For the office only.', 70],
                ],
            ],
            [
                'id' => 4, 'name' => 'General Question', 'description' => 'Ask us anything else.',
                'team' => 1, 'group' => 'Families', 'icon' => "\u{2753}", 'audience' => null,
                'workflow' => [
                    'transitions' => [
                        'New' => ['In Progress', 'Resolved'], 'In Progress' => ['Waiting', 'Resolved'], 'Waiting' => ['In Progress', 'Resolved'],
                        'Resolved' => ['In Progress', 'Closed'],
                    ],
                ],
                'formConfig' => [
                    'title' => ['label' => 'Subject'],
                    'description' => ['label' => 'Your question', 'required' => true, 'order' => 30],
                    'priority' => ['show' => false], 'dateStart' => ['show' => false], 'dateDue' => ['show' => false],
                ],
                'fields' => [
                    ['topic', 'Topic', 'select', 'Fees and payment,Schedule and calendar,Health and safety,Other', 'Y', 'Y', '', 10],
                    ['child_name', "Child's name (if it concerns a child)", 'varchar', '100', 'N', 'Y', '', 20],
                ],
            ],
            [
                'id' => 5, 'name' => 'Staff Request', 'description' => 'Leave, certificates, schedule changes and other requests of employees.',
                'team' => 3, 'group' => 'Staff', 'icon' => "\u{2709}", 'audience' => 'Staff',
                'workflow' => [
                    'transitions' => [
                        'New' => ['In Progress', 'Awaiting Approval', 'Declined'], 'In Progress' => ['Awaiting Approval', 'Resolved', 'Declined'],
                        'Awaiting Approval' => ['In Progress', 'Resolved', 'Declined'], 'Resolved' => ['Closed'], 'Declined' => ['Closed'],
                    ],
                ],
                'formConfig' => [
                    'description' => ['label' => 'Details', 'required' => true, 'order' => 80],
                    'dateStart' => ['label' => 'From', 'order' => 20], 'dateDue' => ['label' => 'Needed by', 'order' => 30],
                ],
                'fields' => [
                    ['request_kind', 'Kind of request', 'select', 'Leave of absence,Certificate or letter,Schedule change,IT or access,Other', 'Y', 'Y', '', 10],
                ],
            ],
            [
                'id' => 6, 'name' => 'Material Request', 'description' => 'Order teaching materials and supplies.',
                'team' => 3, 'group' => 'Staff', 'icon' => "\u{2692}", 'audience' => 'Staff',
                'workflow' => [
                    'transitions' => [
                        'New' => ['Awaiting Approval', 'Declined'], 'Awaiting Approval' => ['Ordered', 'Declined'], 'Ordered' => ['Resolved'],
                        'Resolved' => ['Closed'], 'Declined' => ['Closed'],
                    ],
                ],
                'formConfig' => [
                    'description' => ['label' => 'Why do you need it?', 'order' => 80],
                    'dateStart' => ['show' => false], 'dateDue' => ['label' => 'Needed by', 'required' => true, 'order' => 70],
                ],
                'fields' => [
                    ['item', 'Item', 'varchar', '150', 'Y', 'Y', '', 10],
                    ['quantity', 'Quantity', 'number', '', 'Y', 'Y', '', 20],
                    ['room', 'Room / department', 'varchar', '100', 'N', 'Y', '', 30],
                    ['budget_code', 'Budget code', 'varchar', '50', 'N', 'Y', '', 40],
                    ['supplier', 'Supplier', 'varchar', '150', 'N', 'N', 'For the office only.', 50],
                    ['order_reference', 'Order reference', 'varchar', '100', 'N', 'N', 'For the office only.', 60],
                ],
            ],
        ];
    }

    public static function spaces(): array
    {
        return [[1, 'School Office', 'Front office, admissions and staff services']];
    }

    /**
     * Boards: [id, space, name, description, filter conditions, [status columns]].
     * The columns are status names; their category comes from statuses().
     */
    public static function boards(): array
    {
        return [
            [1, 1, 'All requests', 'Every request with every status', [],
                ['New', 'In Progress', 'Waiting', 'Documents Requested', 'Interview Planned', 'Awaiting Approval', 'Ordered', 'Resolved', 'Declined', 'Closed']],
            [2, 1, 'Absences', 'Absence reports of the families', [['field' => 'type', 'op' => 'equals', 'value' => 'Absence Report']],
                ['New', 'In Progress', 'Resolved', 'Closed']],
            [3, 1, 'Registrations', 'The registration process from application to decision', [['field' => 'type', 'op' => 'equals', 'value' => 'School Registration']],
                ['New', 'Documents Requested', 'Interview Planned', 'Waiting', 'Resolved', 'Declined', 'Closed']],
            [4, 1, 'Leaving', 'Children leaving the school', [['field' => 'type', 'op' => 'equals', 'value' => 'School Leaving']],
                ['New', 'In Progress', 'Waiting', 'Resolved', 'Closed']],
            [5, 1, 'Questions', 'General questions of the families', [['field' => 'type', 'op' => 'equals', 'value' => 'General Question']],
                ['New', 'In Progress', 'Waiting', 'Resolved', 'Closed']],
            [6, 1, 'Staff Services', 'Staff requests and material orders (everything routed to the Staff Services team)', [['field' => 'team', 'op' => 'equals', 'value' => 'Staff Services']],
                ['New', 'In Progress', 'Awaiting Approval', 'Ordered', 'Resolved', 'Declined', 'Closed']],
        ];
    }

    /** Rules that give new registrations and leavings their checklist. */
    public static function rules(): array
    {
        return [
            [1, 2, 'Registration: add the steps checklist', 'ticket_created', [], [['type' => 'add_checklist', 'params' => [
                'title' => 'Registration steps',
                'items' => "Check the application and the documents\nPlan the interview\nTake the decision\nSend the decision letter\nCreate the student record",
            ]]], 1],
            [2, 3, 'Leaving: add the leaving checklist', 'ticket_created', [], [['type' => 'add_checklist', 'params' => [
                'title' => 'Leaving checklist',
                'items' => "Confirm the last day at school\nPrepare the transfer documents\nCollect books and materials\nSettle open fees\nSend the confirmation",
            ]]], 1],
        ];
    }

    /** @return string[] the INSERT statements, in an order that satisfies the references between them */
    public static function statements(): array
    {
        $sql = [];
        $sequence = 0;

        $rows = [];
        foreach (self::statuses() as $name => $category) {
            $rows[] = [$name, $category, ++$sequence];
        }
        $sql[] = self::insert('gibbonCsStatus', ['name', 'category', 'sequenceNumber'], $rows);

        $roleRows = [];
        $permissionRows = [];
        foreach (self::roles() as [$id, $name, $description, $permissions]) {
            $roleRows[] = [$id, $name, $description];
            foreach ($permissions as $permission) {
                $permissionRows[] = [$id, $permission];
            }
        }
        $sql[] = self::insert('gibbonCsRole', ['csRoleID', 'name', 'description'], $roleRows);
        $sql[] = self::insert('gibbonCsRolePermission', ['csRoleID', 'permission'], $permissionRows);

        $sql[] = self::insert('gibbonCsTeam', ['csTeamID', 'name', 'description'], self::teams());

        $typeRows = [];
        $fieldRows = [];
        foreach (self::types() as $t) {
            $typeRows[] = [
                $t['id'], $t['name'], $t['description'], $t['team'], $t['group'], $t['icon'], $t['audience'],
                json_encode($t['workflow']), json_encode($t['formConfig']),
            ];
            foreach ($t['fields'] as [$key, $label, $fieldType, $options, $required, $showOnForm, $description, $order]) {
                $fieldRows[] = [$t['id'], $key, $label, $fieldType, $options, $required, $showOnForm, $description ?: null, $order];
            }
        }
        $sql[] = self::insert('gibbonCsTicketType', ['csTicketTypeID', 'name', 'description', 'csTeamID', 'groupName', 'icon', 'audience', 'workflow', 'formConfig'], $typeRows);
        $sql[] = self::insert('gibbonCsField', ['csTicketTypeID', 'fieldKey', 'label', 'fieldType', 'options', 'required', 'showOnForm', 'description', 'sequenceNumber'], $fieldRows);

        $sql[] = self::insert('gibbonCsSpace', ['csSpaceID', 'name', 'description'], self::spaces());

        $boardRows = [];
        $columnRows = [];
        foreach (self::boards() as [$id, $space, $name, $description, $filter, $columns]) {
            $boardRows[] = [$id, $space, $name, $description, empty($filter) ? null : json_encode($filter)];
            foreach ($columns as $i => $column) {
                $columnRows[] = [$id, $column, $i + 1];
            }
        }
        $sql[] = self::insert('gibbonCsBoard', ['csBoardID', 'csSpaceID', 'name', 'description', 'filter'], $boardRows);
        $sql[] = self::insert('gibbonCsBoardColumn', ['csBoardID', 'name', 'sequenceNumber'], $columnRows);

        $ruleRows = [];
        foreach (self::rules() as [$id, $type, $name, $trigger, $conditions, $actions, $order]) {
            $ruleRows[] = [$id, $type, $name, $trigger, empty($conditions) ? null : json_encode($conditions), json_encode($actions), $order];
        }
        $sql[] = self::insert('gibbonCsAutomationRule', ['csAutomationRuleID', 'csTicketTypeID', 'name', 'triggerKey', 'conditions', 'actions', 'sequenceNumber'], $ruleRows);

        return $sql;
    }

    /** A multi-row INSERT with escaped values (the installer has no PDO to bind with). */
    protected static function insert(string $table, array $columns, array $rows): string
    {
        $values = array_map(function ($row) {
            return '('.implode(', ', array_map([self::class, 'quote'], $row)).')';
        }, $rows);

        return 'INSERT INTO `'.$table.'` (`'.implode('`, `', $columns).'`) VALUES '.implode(",\n", $values).';';
    }

    protected static function quote($value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_int($value)) {
            return (string)$value;
        }
        return "'".str_replace(['\\', "'"], ['\\\\', "''"], (string)$value)."'";
    }
}
