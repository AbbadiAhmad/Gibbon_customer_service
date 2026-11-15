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

use Gibbon\Module\ServiceDesk\Automation\AutomationScript;
use Gibbon\Module\ServiceDesk\Automation\AutomationContext;
use Gibbon\Module\ServiceDesk\Automation\AutomationResult;

/**
 * Takes the students out of the classes chosen on the ticket (the enrolment is kept as "Student - Left").
 */
class UnenrolFromClasses implements AutomationScript
{
    public function getName(): string
    {
        return __m('Unenrol from classes');
    }

    public function getDescription(): string
    {
        return __m('Marks the students as left in the chosen classes. Reads the fields: classes, dateEffective (optional, default today). Finance is not changed.');
    }

    public function run(AutomationContext $context): AutomationResult
    {
        $db = $context->db();
        $classes = $context->field('classes');
        $date = $context->field('dateEffective', date('Y-m-d'));

        if (empty($classes) || !is_array($classes)) {
            return AutomationResult::failure(__m('The ticket has no classes that can be processed automatically.'));
        }

        foreach ($classes as $class) {
            $data = ['gibbonPersonID' => $class['gibbonPersonID'] ?? 0, 'gibbonCourseClassID' => $class['gibbonCourseClassID'] ?? 0];

            $db->update(
                "UPDATE gibbonCourseClassPerson SET role='Student - Left', dateUnenrolled=:date WHERE gibbonPersonID=:gibbonPersonID AND gibbonCourseClassID=:gibbonCourseClassID AND role='Student'",
                $data + ['date' => $date]
            );

            // Verify in the database: the student must no longer be an active student of the class.
            $remaining = $db->selectOne("SELECT COUNT(*) FROM gibbonCourseClassPerson WHERE gibbonPersonID=:gibbonPersonID AND gibbonCourseClassID=:gibbonCourseClassID AND role='Student'", $data);
            if ($remaining > 0) {
                return AutomationResult::failure(__m('A class enrolment could not be updated.'));
            }
        }

        return AutomationResult::success(__m('The students were unenrolled from {count} class(es).', ['count' => count($classes)]));
    }
}
