<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/

namespace Gibbon\Module\ServiceDesk\Service;

use Gibbon\Contracts\Database\Connection;

/**
 * Finds the teachers who should hear about what is approved for a child: form tutors, and class teachers.
 */
class TeacherFinder
{
    protected $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * Teachers for the Students and Classes answers of a ticket, as a list of gibbonPersonID.
     *  - Students answer (an absence): the form tutors of those students and every teacher of their classes this year.
     *  - Classes answer (an unenrolment): the form tutors of those students and the teachers of the chosen classes.
     * Only active users.
     */
    public function findForAnswers(array $answers, $gibbonSchoolYearID): array
    {
        $students = [];
        $classes = [];
        $allClassesOfStudents = [];

        foreach ($answers as $answer) {
            if (($answer['type'] ?? '') == 'Students') {
                foreach ((array) $answer['value'] as $gibbonPersonID) {
                    $students[] = intval($gibbonPersonID);
                    $allClassesOfStudents[] = intval($gibbonPersonID);
                }
            } elseif (($answer['type'] ?? '') == 'Classes' && is_array($answer['value'])) {
                foreach ($answer['value'] as $class) {
                    $students[] = intval($class['gibbonPersonID'] ?? 0);
                    $classes[] = intval($class['gibbonCourseClassID'] ?? 0);
                }
            }
        }

        $students = array_values(array_filter(array_unique($students)));
        $classes = array_values(array_filter(array_unique($classes)));
        $allClassesOfStudents = array_values(array_filter(array_unique($allClassesOfStudents)));
        $teachers = [];

        if (!empty($students)) {
            $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'students' => implode(',', $students)];
            $sql = "SELECT gibbonFormGroup.gibbonPersonIDTutor, gibbonFormGroup.gibbonPersonIDTutor2, gibbonFormGroup.gibbonPersonIDTutor3
                    FROM gibbonStudentEnrolment
                    JOIN gibbonFormGroup ON (gibbonFormGroup.gibbonFormGroupID=gibbonStudentEnrolment.gibbonFormGroupID)
                    WHERE gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID AND FIND_IN_SET(gibbonStudentEnrolment.gibbonPersonID+0, :students)";
            foreach ($this->db->select($sql, $data)->fetchAll() as $row) {
                $teachers = array_merge($teachers, [$row['gibbonPersonIDTutor'], $row['gibbonPersonIDTutor2'], $row['gibbonPersonIDTutor3']]);
            }
        }

        if (!empty($classes)) {
            $sql = "SELECT gibbonCourseClassPerson.gibbonPersonID FROM gibbonCourseClassPerson
                    WHERE gibbonCourseClassPerson.role='Teacher' AND FIND_IN_SET(gibbonCourseClassPerson.gibbonCourseClassID+0, :classes)";
            $teachers = array_merge($teachers, $this->db->select($sql, ['classes' => implode(',', $classes)])->fetchAll(\PDO::FETCH_COLUMN));
        }

        if (!empty($allClassesOfStudents)) {
            $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'students' => implode(',', $allClassesOfStudents)];
            $sql = "SELECT teacher.gibbonPersonID
                    FROM gibbonCourseClassPerson AS student
                    JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseClassID=student.gibbonCourseClassID)
                    JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID AND gibbonCourse.gibbonSchoolYearID=:gibbonSchoolYearID)
                    JOIN gibbonCourseClassPerson AS teacher ON (teacher.gibbonCourseClassID=student.gibbonCourseClassID AND teacher.role='Teacher')
                    WHERE student.role='Student' AND FIND_IN_SET(student.gibbonPersonID+0, :students)";
            $teachers = array_merge($teachers, $this->db->select($sql, $data)->fetchAll(\PDO::FETCH_COLUMN));
        }

        $teachers = array_values(array_unique(array_filter(array_map('intval', $teachers))));
        if (empty($teachers)) return [];

        $sql = "SELECT gibbonPersonID FROM gibbonPerson WHERE status='Full' AND FIND_IN_SET(gibbonPersonID+0, :teachers) ORDER BY gibbonPersonID";

        return array_map('intval', $this->db->select($sql, ['teachers' => implode(',', $teachers)])->fetchAll(\PDO::FETCH_COLUMN));
    }
}
