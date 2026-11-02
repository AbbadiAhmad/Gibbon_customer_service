<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\administration\Install;

/**
 * The data a fresh installation starts with: a school office (space), teams, ticket types (with workflows), the request forms for
 * families and staff (each mapped to a ticket type), boards for them and a generic board with every status.
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
     * Ticket types: what a ticket IS (team, workflow, the custom fields the tickets carry). Nothing about forms or who may use them.
     * The workflow lists the allowed moves per status (a status without a line may move anywhere); Done statuses always ask for a resolution note.
     * Fields: [key, label, fieldType, options, required, internal (agents only), help, order].
     */
    public static function types(): array
    {
        $reopenAndClose = ['Resolved' => ['In Progress', 'Closed']];

        return [
            [
                'id' => 1, 'name' => 'Absence Report', 'description' => 'A child will be absent or late.', 'team' => 1,
                'workflow' => ['transitions' => ['New' => ['In Progress', 'Resolved'], 'In Progress' => ['Resolved']] + $reopenAndClose],
                'fields' => [
                    ['child_name', "Child's name", 'varchar', '100', 'N', 'N', '', 10],
                    ['class_name', 'Class', 'varchar', '50', 'N', 'N', '', 20],
                    ['absent_from', 'Absent from', 'date', '', 'N', 'N', '', 30],
                    ['absent_until', 'Absent until', 'date', '', 'N', 'N', '', 40],
                    ['reason', 'Reason', 'select', 'Illness,Medical appointment,Family reasons,Other', 'N', 'N', '', 50],
                    ['contact_phone', 'Phone number', 'varchar', '30', 'N', 'N', '', 60],
                    ['attendance_recorded', 'Entered in the attendance register', 'yesno', '', 'N', 'Y', 'For the office only.', 70],
                ],
            ],
            [
                'id' => 2, 'name' => 'School Registration', 'description' => 'A child is registered at the school.', 'team' => 2,
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
                'fields' => [
                    ['child_first_name', "Child's first name", 'varchar', '100', 'N', 'N', '', 10],
                    ['child_last_name', "Child's last name", 'varchar', '100', 'N', 'N', '', 20],
                    ['birth_date', 'Date of birth', 'date', '', 'N', 'N', '', 30],
                    ['year_group', 'Year / grade', 'varchar', '50', 'N', 'N', '', 40],
                    ['previous_school', 'Previous school', 'varchar', '150', 'N', 'N', '', 50],
                    ['siblings_at_school', 'Siblings at our school', 'yesno', '', 'N', 'N', '', 60],
                    ['parent_phone', 'Phone number', 'varchar', '30', 'N', 'N', '', 70],
                    ['documents_complete', 'All documents received', 'yesno', '', 'N', 'Y', 'For the office only.', 80],
                    ['interview_date', 'Interview date', 'date', '', 'N', 'Y', 'For the office only.', 90],
                ],
            ],
            [
                'id' => 3, 'name' => 'School Leaving', 'description' => 'A child leaves the school.', 'team' => 2,
                'workflow' => [
                    'transitions' => [
                        'New' => ['In Progress', 'Waiting'], 'In Progress' => ['Waiting', 'Resolved'], 'Waiting' => ['In Progress', 'Resolved'],
                        'Resolved' => ['In Progress', 'Closed'],
                    ],
                ],
                'fields' => [
                    ['child_name', "Child's name", 'varchar', '100', 'N', 'N', '', 10],
                    ['class_name', 'Class', 'varchar', '50', 'N', 'N', '', 20],
                    ['reason', 'Reason for leaving', 'select', 'Moving away,Changing school,Other', 'N', 'N', '', 30],
                    ['new_school', 'New school', 'varchar', '150', 'N', 'N', '', 40],
                    ['documents_needed', 'Documents needed', 'checkboxes', 'Report card,Certificate of attendance,Transfer form', 'N', 'N', '', 50],
                    ['fees_settled', 'Open fees settled', 'yesno', '', 'N', 'Y', 'For the office only.', 60],
                    ['library_returned', 'Books and materials returned', 'yesno', '', 'N', 'Y', 'For the office only.', 70],
                ],
            ],
            [
                'id' => 4, 'name' => 'General Question', 'description' => 'Any other question.', 'team' => 1,
                'workflow' => [
                    'transitions' => [
                        'New' => ['In Progress', 'Resolved'], 'In Progress' => ['Waiting', 'Resolved'], 'Waiting' => ['In Progress', 'Resolved'],
                        'Resolved' => ['In Progress', 'Closed'],
                    ],
                ],
                'fields' => [
                    ['topic', 'Topic', 'select', 'Fees and payment,Schedule and calendar,Health and safety,Other', 'N', 'N', '', 10],
                    ['child_name', "Child's name", 'varchar', '100', 'N', 'N', '', 20],
                ],
            ],
            [
                'id' => 5, 'name' => 'Staff Request', 'description' => 'Leave, certificates, schedule changes and other requests of employees.', 'team' => 3,
                'workflow' => [
                    'transitions' => [
                        'New' => ['In Progress', 'Awaiting Approval', 'Declined'], 'In Progress' => ['Awaiting Approval', 'Resolved', 'Declined'],
                        'Awaiting Approval' => ['In Progress', 'Resolved', 'Declined'], 'Resolved' => ['Closed'], 'Declined' => ['Closed'],
                    ],
                ],
                'fields' => [
                    ['request_kind', 'Kind of request', 'select', 'Leave of absence,Certificate or letter,Schedule change,IT or access,Other', 'N', 'N', '', 10],
                ],
            ],
            [
                'id' => 6, 'name' => 'Material Request', 'description' => 'Teaching materials and supplies.', 'team' => 3,
                'workflow' => [
                    'transitions' => [
                        'New' => ['Awaiting Approval', 'Declined'], 'Awaiting Approval' => ['Ordered', 'Declined'], 'Ordered' => ['Resolved'],
                        'Resolved' => ['Closed'], 'Declined' => ['Closed'],
                    ],
                ],
                'fields' => [
                    ['item', 'Item', 'varchar', '150', 'N', 'N', '', 10],
                    ['quantity', 'Quantity', 'number', '', 'N', 'N', '', 20],
                    ['room', 'Room / department', 'varchar', '100', 'N', 'N', '', 30],
                    ['budget_code', 'Budget code', 'varchar', '50', 'N', 'N', '', 40],
                    ['supplier', 'Supplier', 'varchar', '150', 'N', 'Y', 'For the office only.', 50],
                    ['order_reference', 'Order reference', 'varchar', '100', 'N', 'Y', 'For the office only.', 60],
                ],
            ],
        ];
    }

    /**
     * Request forms, independent of the ticket types: each has its own questions, the mapping of every question to a ticket field,
     * and its own visibility (audience = role categories, roles = Gibbon role ids; nothing set = every registered user).
     * Questions: [key, label, help, inputType, options, required, target]. Target: title | description | priority | dateStart | dateDue |
     * attachments | custom:<key of a field of the ticket type> | '' (information text).
     */
    public static function forms(): array
    {
        $priorities = 'Low,Medium,High,Urgent';

        return [
            [
                'id' => 1, 'type' => 1, 'name' => 'Report an absence', 'slug' => 'absence', 'description' => 'Tell us that your child will be absent or late.',
                'group' => 'Families', 'icon' => "\u{271A}", 'audience' => 'Parent,Staff', 'titleTemplate' => 'Absence {child_name} from {absent_from}',
                'confirmation' => 'Thank you. The school office has received your absence report.', 'order' => 10,
                'questions' => [
                    ['child_name', "Child's name", '', 'varchar', '100', 'Y', 'custom:child_name'],
                    ['class_name', 'Class', '', 'varchar', '50', 'N', 'custom:class_name'],
                    ['absent_from', 'Absent from', '', 'date', '', 'Y', 'custom:absent_from'],
                    ['absent_until', 'Absent until', "Leave empty if you don't know yet.", 'date', '', 'N', 'custom:absent_until'],
                    ['reason', 'Reason', '', 'select', 'Illness,Medical appointment,Family reasons,Other', 'Y', 'custom:reason'],
                    ['contact_phone', 'Phone number where we can reach you', '', 'varchar', '30', 'N', 'custom:contact_phone'],
                    ['details', 'Additional information', '', 'text', '', 'N', 'description'],
                    ['doctors_note', "Doctor's note", 'Optional.', 'file', '', 'N', 'attachments'],
                ],
            ],
            [
                'id' => 2, 'type' => 2, 'name' => 'Register a child', 'slug' => 'registration', 'description' => 'Register a child at our school.',
                'group' => 'Families', 'icon' => "\u{270E}", 'audience' => 'Parent,Staff', 'titleTemplate' => 'Registration {child_first_name} {child_last_name}',
                'confirmation' => 'Thank you. The admissions team will contact you about the next steps.', 'order' => 20,
                'questions' => [
                    ['child_first_name', "Child's first name", '', 'varchar', '100', 'Y', 'custom:child_first_name'],
                    ['child_last_name', "Child's last name", '', 'varchar', '100', 'Y', 'custom:child_last_name'],
                    ['birth_date', 'Date of birth', '', 'date', '', 'Y', 'custom:birth_date'],
                    ['year_group', 'Year / grade applying for', '', 'varchar', '50', 'Y', 'custom:year_group'],
                    ['previous_school', 'Previous school', '', 'varchar', '150', 'N', 'custom:previous_school'],
                    ['siblings_at_school', 'Does your child have siblings at our school?', '', 'yesno', '', 'N', 'custom:siblings_at_school'],
                    ['parent_phone', 'Phone number', '', 'varchar', '30', 'Y', 'custom:parent_phone'],
                    ['desired_start', 'Desired start date', 'When should your child start with us?', 'date', '', 'Y', 'dateStart'],
                    ['interests', 'Interested in', '', 'checkboxes', 'After-school care,School bus,Lunch', 'N', 'description'],
                    ['remarks', 'Anything we should know?', '', 'text', '', 'N', 'description'],
                    ['documents', 'Documents', 'Birth certificate, last report card, vaccination record...', 'file', '', 'N', 'attachments'],
                ],
            ],
            [
                'id' => 3, 'type' => 3, 'name' => 'Leave the school', 'slug' => 'leaving', 'description' => 'Tell us that your child is leaving the school.',
                'group' => 'Families', 'icon' => "\u{279C}", 'audience' => 'Parent,Staff', 'titleTemplate' => 'Leaving {child_name}',
                'confirmation' => 'Thank you. We will confirm the last day and prepare the documents.', 'order' => 30,
                'questions' => [
                    ['child_name', "Child's name", '', 'varchar', '100', 'Y', 'custom:child_name'],
                    ['class_name', 'Class', '', 'varchar', '50', 'N', 'custom:class_name'],
                    ['reason', 'Reason for leaving', '', 'select', 'Moving away,Changing school,Other', 'Y', 'custom:reason'],
                    ['new_school', 'New school (if known)', '', 'varchar', '150', 'N', 'custom:new_school'],
                    ['documents_needed', 'Documents you need from us', '', 'checkboxes', 'Report card,Certificate of attendance,Transfer form', 'N', 'custom:documents_needed'],
                    ['last_day', 'Last day at school', 'The last day your child attends.', 'date', '', 'Y', 'dateDue'],
                    ['remarks', 'Anything we should know?', '', 'text', '', 'N', 'description'],
                ],
            ],
            [
                'id' => 4, 'type' => 4, 'name' => 'Ask a question', 'slug' => 'question', 'description' => 'Ask us anything else.',
                'group' => 'Families', 'icon' => "\u{2753}", 'audience' => null, 'titleTemplate' => null,
                'confirmation' => 'Thank you. We will answer as soon as possible.', 'order' => 40,
                'questions' => [
                    ['subject', 'Subject', '', 'varchar', '200', 'Y', 'title'],
                    ['topic', 'Topic', '', 'select', 'Fees and payment,Schedule and calendar,Health and safety,Other', 'Y', 'custom:topic'],
                    ['child_name', "Child's name (if it concerns a child)", '', 'varchar', '100', 'N', 'custom:child_name'],
                    ['question', 'Your question', '', 'text', '', 'Y', 'description'],
                    ['attachment', 'Attachment', 'Optional.', 'file', '', 'N', 'attachments'],
                ],
            ],
            [
                'id' => 5, 'type' => 5, 'name' => 'Staff request', 'slug' => 'staff-request', 'description' => 'Leave, certificates, schedule changes and other requests of employees.',
                'group' => 'Staff', 'icon' => "\u{2709}", 'audience' => 'Staff', 'titleTemplate' => null,
                'confirmation' => 'Thank you. Staff Services will handle your request.', 'order' => 10,
                'questions' => [
                    ['summary', 'Summary', '', 'varchar', '200', 'Y', 'title'],
                    ['request_kind', 'Kind of request', '', 'select', 'Leave of absence,Certificate or letter,Schedule change,IT or access,Other', 'Y', 'custom:request_kind'],
                    ['from', 'From', '', 'date', '', 'N', 'dateStart'],
                    ['needed_by', 'Needed by', '', 'date', '', 'N', 'dateDue'],
                    ['details', 'Details', '', 'text', '', 'Y', 'description'],
                    ['attachments', 'Attachments', '', 'file', '', 'N', 'attachments'],
                ],
            ],
            [
                'id' => 6, 'type' => 6, 'name' => 'Order material', 'slug' => 'material', 'description' => 'Order teaching materials and supplies.',
                'group' => 'Staff', 'icon' => "\u{2692}", 'audience' => 'Staff', 'titleTemplate' => 'Material: {item} ({quantity})',
                'confirmation' => 'Thank you. Your order request is waiting for approval.', 'order' => 20,
                'questions' => [
                    ['item', 'Item', '', 'varchar', '150', 'Y', 'custom:item'],
                    ['quantity', 'Quantity', '', 'number', '', 'Y', 'custom:quantity'],
                    ['room', 'Room / department', '', 'varchar', '100', 'N', 'custom:room'],
                    ['budget_code', 'Budget code', '', 'varchar', '50', 'N', 'custom:budget_code'],
                    ['needed_by', 'Needed by', '', 'date', '', 'Y', 'dateDue'],
                    ['urgency', 'How urgent is it?', '', 'select', $priorities, 'N', 'priority'],
                    ['reason', 'Why do you need it?', '', 'text', '', 'N', 'description'],
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
            $typeRows[] = [$t['id'], $t['name'], $t['description'], $t['team'], json_encode($t['workflow'])];
            foreach ($t['fields'] as [$key, $label, $fieldType, $options, $required, $internal, $description, $order]) {
                $fieldRows[] = [$t['id'], $key, $label, $fieldType, $options, $required, $internal, $description ?: null, $order];
            }
        }
        $sql[] = self::insert('gibbonCsTicketType', ['csTicketTypeID', 'name', 'description', 'csTeamID', 'workflow'], $typeRows);
        $sql[] = self::insert('gibbonCsField', ['csTicketTypeID', 'fieldKey', 'label', 'fieldType', 'options', 'required', 'internal', 'description', 'sequenceNumber'], $fieldRows);

        $formRows = [];
        $questionRows = [];
        foreach (self::forms() as $f) {
            $formRows[] = [$f['id'], $f['type'], $f['name'], $f['slug'], $f['description'], $f['group'], $f['icon'], $f['audience'], $f['titleTemplate'], $f['confirmation'], $f['order']];
            foreach ($f['questions'] as $i => [$key, $label, $help, $inputType, $options, $required, $target]) {
                $questionRows[] = [$f['id'], $key, $label, $help ?: null, $inputType, $options ?: null, $required, $target, ($i + 1) * 10];
            }
        }
        $sql[] = self::insert('gibbonCsForm', ['csFormID', 'csTicketTypeID', 'name', 'slug', 'description', 'groupName', 'icon', 'audience', 'titleTemplate', 'confirmationText', 'sequenceNumber'], $formRows);
        $sql[] = self::insert('gibbonCsFormField', ['csFormID', 'fieldKey', 'label', 'helpText', 'inputType', 'options', 'required', 'target', 'sequenceNumber'], $questionRows);

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
