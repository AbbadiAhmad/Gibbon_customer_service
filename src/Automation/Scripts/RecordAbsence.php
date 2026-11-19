<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/

namespace Gibbon\Module\ServiceDesk\Automation\Scripts;

use Gibbon\Services\Format;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Module\ServiceDesk\Automation\HasSettings;
use Gibbon\Module\ServiceDesk\Automation\AutomationScript;
use Gibbon\Module\ServiceDesk\Automation\AutomationContext;
use Gibbon\Module\ServiceDesk\Automation\AutomationResult;

/**
 * Marks the students of a ticket absent in Gibbon's Attendance module, for every school day of the requested period.
 *
 * Setting "attendanceCode": which attendance code is written - for example an excused or an unexcused variant of
 * "Absent" (School Admin > Manage Attendance Codes). Empty: the code named "Absent". Two stages with the same script
 * and a different code give the team a choice when they approve.
 */
class RecordAbsence implements AutomationScript, HasSettings
{
    public const MAX_DAYS = 60;

    protected $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function getSettings(): array
    {
        return [
            'attendanceCode' => [
                'label'       => __m('Attendance code'),
                'description' => __m('The attendance code written for each absent day, for example an excused or an unexcused variant of "Absent". The codes come from School Admin > Manage Attendance Codes (active, leaving, allowed for absences in advance). Default: the code named "Absent".'),
                'type'        => 'select',
                'required'    => false,
                'options'     => function () {
                    return $this->absenceCodes();
                },
            ],
        ];
    }

    /**
     * The codes that can be chosen: id => name. Active, direction "Out" (absent, left) and allowed in advance (future),
     * the rule of Gibbon's own page for absences in advance.
     */
    protected function absenceCodes(): array
    {
        $codes = [];
        foreach ($this->db->select("SELECT gibbonAttendanceCodeID, name FROM gibbonAttendanceCode WHERE active='Y' AND future='Y' AND direction='Out' ORDER BY sequenceNumber, name")->fetchAll() as $row) {
            $codes[intval($row['gibbonAttendanceCodeID'])] = $row['name'];
        }

        return $codes;
    }

    public function getName(): string
    {
        return __m('Record absence in Attendance');
    }

    public function getDescription(): string
    {
        return __m('Marks the students absent for each school day of the period, with the attendance code chosen as setting of the stage. Reads the fields: students, dateStart, dateEnd (optional), reason (optional).');
    }

    public function run(AutomationContext $context): AutomationResult
    {
        $db = $context->db();
        $students = array_filter(array_map('intval', (array) $context->field('students', [])));
        $dateStart = $context->field('dateStart');
        $dateEnd = $context->field('dateEnd', $dateStart);
        $reason = (string) $context->field('reason', '');

        if (empty($students)) {
            return AutomationResult::failure(__m('The ticket has no students.'));
        }
        if (empty($dateStart) || strtotime($dateStart) === false || strtotime($dateEnd) === false || $dateEnd < $dateStart) {
            return AutomationResult::failure(__m('The absence period is missing or invalid.'));
        }
        if ((strtotime($dateEnd) - strtotime($dateStart)) / 86400 >= self::MAX_DAYS) {
            return AutomationResult::failure(__m('The absence period is longer than {days} days: record it by hand.', ['days' => self::MAX_DAYS]));
        }

        // The code chosen for this stage (the setting was checked when the stage ran; the code is looked up again, inside
        // the transaction), or the code named "Absent" when the stage has none.
        $chosen = $context->setting('attendanceCode');
        $code = !empty($chosen)
            ? $db->select("SELECT gibbonAttendanceCodeID, name, direction FROM gibbonAttendanceCode WHERE gibbonAttendanceCodeID=:id AND active='Y' AND future='Y' AND direction='Out'", ['id' => intval($chosen)])->fetch()
            : $db->select("SELECT gibbonAttendanceCodeID, name, direction FROM gibbonAttendanceCode WHERE name='Absent' AND active='Y'")->fetch();
        if (empty($code)) {
            return AutomationResult::failure(!empty($chosen)
                ? __m('The attendance code chosen for this stage is not available any more (deleted, inactive, or not allowed for absences in advance).')
                : __m('The attendance code "Absent" does not exist or is not active.'));
        }

        // Only reasons known to Attendance are stored (School Admin > Attendance Settings).
        $reasons = $db->selectOne("SELECT value FROM gibbonSetting WHERE scope='Attendance' AND name='attendanceReasons'");
        if (!in_array($reason, array_map('trim', explode(',', (string) $reasons)), true)) {
            $reason = '';
        }

        $schoolDays = [];
        for ($date = $dateStart; $date <= $dateEnd; $date = date('Y-m-d', strtotime($date.' +1 day'))) {
            if ($this->isSchoolOpen($context, $date)) $schoolDays[] = $date;
        }
        if (empty($schoolDays)) {
            return AutomationResult::failure(__m('The school is closed on every day of the absence period.'));
        }

        // A day that already has a whole-day record with this code is skipped. A different whole-day record (for example
        // the unexcused code when this stage records the excused one) is a conflict: nothing is written, the team sees
        // which student and day, and corrects it in Attendance first.
        $existing = $db->select(
            "SELECT gibbonAttendanceLogPerson.gibbonPersonID, gibbonAttendanceLogPerson.date, gibbonAttendanceLogPerson.type, gibbonPerson.preferredName, gibbonPerson.surname
             FROM gibbonAttendanceLogPerson JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=gibbonAttendanceLogPerson.gibbonPersonID)
             WHERE gibbonAttendanceLogPerson.context IN ('Future','Person') AND FIND_IN_SET(gibbonAttendanceLogPerson.gibbonPersonID+0, :students) AND FIND_IN_SET(gibbonAttendanceLogPerson.date, :dates)",
            ['students' => implode(',', $students), 'dates' => implode(',', $schoolDays)]
        )->fetchAll();
        $recorded = [];
        foreach ($existing as $row) {
            if ($row['type'] === $code['name']) {
                $recorded[intval($row['gibbonPersonID']).'|'.$row['date']] = true;
            } else {
                return AutomationResult::failure(__m('{name} already has the attendance record "{type}" on {date}. Change or remove it in Attendance first.', [
                    'name' => Format::name('', $row['preferredName'], $row['surname'], 'Student', false), 'type' => $row['type'], 'date' => Format::date($row['date']),
                ]));
            }
        }

        // Write one log per student per school day.
        $today = date('Y-m-d');
        foreach ($schoolDays as $date) {
            foreach ($students as $gibbonPersonID) {
                if (isset($recorded[intval($gibbonPersonID).'|'.$date])) continue;

                $db->insert(
                    "INSERT INTO gibbonAttendanceLogPerson SET gibbonAttendanceCodeID=:gibbonAttendanceCodeID, gibbonPersonID=:gibbonPersonID, direction=:direction, type=:type, reason=:reason, context=:context, comment=:comment, date=:date, gibbonPersonIDTaker=:gibbonPersonIDTaker, timestampTaken=:timestampTaken",
                    [
                        'gibbonAttendanceCodeID' => $code['gibbonAttendanceCodeID'],
                        'gibbonPersonID'         => $gibbonPersonID,
                        'direction'              => $code['direction'],
                        'type'                   => $code['name'],
                        'reason'                 => $reason,
                        'context'                => $date > $today ? 'Future' : 'Person',
                        'comment'                => 'Service Desk #'.$context->ticketID(),
                        'date'                   => $date,
                        'gibbonPersonIDTaker'    => $context->actorID() ?: $context->ticket()['gibbonPersonIDRequester'],
                        'timestampTaken'         => date('Y-m-d H:i:s'),
                    ]
                );
            }
        }

        // Verify in the database: every student must now be absent on every school day of the period.
        $expected = count($students) * count($schoolDays);
        $found = $this->countAbsences($context, $students, $schoolDays, $code['name']);
        if ($found < $expected) {
            return AutomationResult::failure(__m('Only {found} of {expected} absences could be recorded.', ['found' => $found, 'expected' => $expected]));
        }

        $period = Format::date($schoolDays[0]).(count($schoolDays) > 1 ? ' - '.Format::date(end($schoolDays)) : '');

        return AutomationResult::success(__m('The absence was recorded in Attendance: {students} student(s), {days} school day(s), {period}.', [
            'students' => count($students),
            'days'     => count($schoolDays),
            'period'   => $period,
        ]));
    }

    /**
     * Number of (student, day) pairs that have a whole-day absence log.
     */
    protected function countAbsences(AutomationContext $context, array $students, array $dates, string $type): int
    {
        $sql = "SELECT COUNT(DISTINCT gibbonPersonID, date) FROM gibbonAttendanceLogPerson
                WHERE type=:type AND context IN ('Future','Person')
                AND FIND_IN_SET(CAST(gibbonPersonID AS UNSIGNED), :students) AND FIND_IN_SET(date, :dates)";

        return (int) $context->db()->selectOne($sql, ['type' => $type, 'students' => implode(',', $students), 'dates' => implode(',', $dates)]);
    }

    protected function isSchoolOpen(AutomationContext $context, string $date): bool
    {
        if (function_exists('isSchoolOpen')) {
            global $guid;
            return (bool) isSchoolOpen($guid, $date, $context->db()->getConnection());
        }

        return date('N', strtotime($date)) < 6;
    }
}
