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
use Gibbon\Module\ServiceDesk\Automation\AutomationScript;
use Gibbon\Module\ServiceDesk\Automation\AutomationContext;
use Gibbon\Module\ServiceDesk\Automation\AutomationResult;

/**
 * Marks the students of a ticket absent in Gibbon's Attendance module, for every school day of the requested period.
 */
class RecordAbsence implements AutomationScript
{
    public const MAX_DAYS = 60;

    public function getName(): string
    {
        return __m('Record absence in Attendance');
    }

    public function getDescription(): string
    {
        return __m('Marks the students absent for each school day of the period. Reads the fields: students, dateStart, dateEnd (optional), reason (optional).');
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

        $code = $db->select("SELECT gibbonAttendanceCodeID, name, direction FROM gibbonAttendanceCode WHERE name='Absent' AND active='Y'")->fetch();
        if (empty($code)) {
            return AutomationResult::failure(__m('The attendance code "Absent" does not exist or is not active.'));
        }

        // Only reasons known to Attendance are stored (School Admin > Attendance Settings).
        $reasons = $db->selectOne("SELECT value FROM gibbonSetting WHERE scope='Attendance' AND name='attendanceReasons'");
        if (!in_array($reason, array_map('trim', explode(',', (string) $reasons)), true)) {
            $reason = '';
        }

        // Write one log per student per school day, skipping days already recorded as absent.
        $today = date('Y-m-d');
        $schoolDays = [];
        for ($date = $dateStart; $date <= $dateEnd; $date = date('Y-m-d', strtotime($date.' +1 day'))) {
            if (!$this->isSchoolOpen($context, $date)) continue;
            $schoolDays[] = $date;

            foreach ($students as $gibbonPersonID) {
                if ($this->countAbsences($context, [$gibbonPersonID], [$date], $code['name']) > 0) continue;

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

        if (empty($schoolDays)) {
            return AutomationResult::failure(__m('The school is closed on every day of the absence period.'));
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
