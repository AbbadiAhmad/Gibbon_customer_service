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

use Gibbon\Services\Format;
use Gibbon\Contracts\Database\Connection;

/**
 * Lookups in Gibbon's own tables: the students a user may raise a request for, and their classes.
 */
class People
{
    protected $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * The students a user may select, as gibbonPersonID => name.
     * Parents: their children (family adult with data access, child enrolled this year). Students: themselves.
     * Returns null for everyone else (staff), meaning "any student enrolled this year".
     */
    public function getSelectableStudents($gibbonPersonID, $roleCategory, $gibbonSchoolYearID): ?array
    {
        if ($roleCategory == 'Parent') {
            $data = ['gibbonPersonID' => $gibbonPersonID, 'gibbonSchoolYearID' => $gibbonSchoolYearID, 'today' => date('Y-m-d'), 'today2' => date('Y-m-d')];
            $sql = "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.surname, gibbonPerson.preferredName, gibbonFormGroup.nameShort AS formGroup
                    FROM gibbonFamilyAdult
                    JOIN gibbonFamilyChild ON (gibbonFamilyChild.gibbonFamilyID=gibbonFamilyAdult.gibbonFamilyID)
                    JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=gibbonFamilyChild.gibbonPersonID)
                    JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID AND gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID)
                    LEFT JOIN gibbonFormGroup ON (gibbonFormGroup.gibbonFormGroupID=gibbonStudentEnrolment.gibbonFormGroupID)
                    WHERE gibbonFamilyAdult.gibbonPersonID=:gibbonPersonID
                    AND gibbonFamilyAdult.childDataAccess='Y'
                    AND gibbonPerson.status='Full'
                    AND (gibbonPerson.dateStart IS NULL OR gibbonPerson.dateStart<=:today)
                    AND (gibbonPerson.dateEnd IS NULL OR gibbonPerson.dateEnd>=:today2)
                    GROUP BY gibbonPerson.gibbonPersonID
                    ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

            $students = [];
            foreach ($this->db->select($sql, $data)->fetchAll() as $row) {
                $name = Format::name('', $row['preferredName'], $row['surname'], 'Student', false);
                $students[intval($row['gibbonPersonID'])] = $name.(!empty($row['formGroup']) ? ' ('.$row['formGroup'].')' : '');
            }
            return $students;
        }

        if ($roleCategory == 'Student') {
            return $this->getNames([$gibbonPersonID]);
        }

        return null;
    }

    /**
     * Keeps only the ids that the user may select (see getSelectableStudents).
     */
    public function filterSelectableStudents(array $ids, $gibbonPersonID, $roleCategory, $gibbonSchoolYearID): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids)) return [];

        $allowed = $this->getSelectableStudents($gibbonPersonID, $roleCategory, $gibbonSchoolYearID);
        if ($allowed !== null) {
            return array_values(array_intersect($ids, array_keys($allowed)));
        }

        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'ids' => implode(',', $ids)];
        $sql = "SELECT gibbonPerson.gibbonPersonID FROM gibbonPerson
                JOIN gibbonStudentEnrolment ON (gibbonStudentEnrolment.gibbonPersonID=gibbonPerson.gibbonPersonID AND gibbonStudentEnrolment.gibbonSchoolYearID=:gibbonSchoolYearID)
                WHERE gibbonPerson.status='Full' AND FIND_IN_SET(CAST(gibbonPerson.gibbonPersonID AS UNSIGNED), :ids)";

        return array_map('intval', $this->db->select($sql, $data)->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * The current classes of the given students, as "gibbonPersonID-gibbonCourseClassID" => "Student: Course.Class (Course name)".
     */
    public function getClassesByStudents(array $studentIDs, $gibbonSchoolYearID): array
    {
        $studentIDs = array_filter(array_map('intval', $studentIDs));
        if (empty($studentIDs)) return [];

        $data = ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'ids' => implode(',', $studentIDs)];
        $sql = "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.surname, gibbonPerson.preferredName, gibbonCourseClass.gibbonCourseClassID,
                    gibbonCourse.nameShort AS course, gibbonCourseClass.nameShort AS class, gibbonCourse.name AS courseName
                FROM gibbonCourseClassPerson
                JOIN gibbonCourseClass ON (gibbonCourseClass.gibbonCourseClassID=gibbonCourseClassPerson.gibbonCourseClassID)
                JOIN gibbonCourse ON (gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID)
                JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=gibbonCourseClassPerson.gibbonPersonID)
                WHERE gibbonCourse.gibbonSchoolYearID=:gibbonSchoolYearID
                AND gibbonCourseClassPerson.role='Student'
                AND FIND_IN_SET(CAST(gibbonCourseClassPerson.gibbonPersonID AS UNSIGNED), :ids)
                ORDER BY gibbonPerson.surname, gibbonPerson.preferredName, gibbonCourse.nameShort, gibbonCourseClass.nameShort";

        $classes = [];
        foreach ($this->db->select($sql, $data)->fetchAll() as $row) {
            $key = intval($row['gibbonPersonID']).'-'.intval($row['gibbonCourseClassID']);
            $classes[$key] = Format::name('', $row['preferredName'], $row['surname'], 'Student', false).': '.$row['course'].'.'.$row['class'].' ('.$row['courseName'].')';
        }

        return $classes;
    }

    /**
     * Names for a list of person ids, as gibbonPersonID => name.
     */
    public function getNames(array $ids): array
    {
        $ids = array_filter(array_map('intval', $ids));
        if (empty($ids)) return [];

        $data = ['ids' => implode(',', $ids)];
        $sql = "SELECT gibbonPersonID, surname, preferredName FROM gibbonPerson WHERE FIND_IN_SET(CAST(gibbonPersonID AS UNSIGNED), :ids) ORDER BY surname, preferredName";

        $names = [];
        foreach ($this->db->select($sql, $data)->fetchAll() as $row) {
            $names[intval($row['gibbonPersonID'])] = Format::name('', $row['preferredName'], $row['surname'], 'Student', false);
        }

        return $names;
    }

    /**
     * Staff who can be added to a team, as gibbonPersonID => name.
     */
    public function getStaffOptions(): array
    {
        $sql = "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.surname, gibbonPerson.preferredName
                FROM gibbonStaff
                JOIN gibbonPerson ON (gibbonPerson.gibbonPersonID=gibbonStaff.gibbonPersonID)
                WHERE gibbonPerson.status='Full'
                ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

        $staff = [];
        foreach ($this->db->select($sql)->fetchAll() as $row) {
            $staff[$row['gibbonPersonID']] = Format::name('', $row['preferredName'], $row['surname'], 'Staff', true, true);
        }

        return $staff;
    }

    /**
     * An active user with the category of their primary role (Staff / Student / Parent / Other), or [].
     */
    public function getPerson($gibbonPersonID): array
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.surname, gibbonPerson.preferredName, gibbonRole.category AS roleCategory
                FROM gibbonPerson
                LEFT JOIN gibbonRole ON (gibbonRole.gibbonRoleID=gibbonPerson.gibbonRoleIDPrimary)
                WHERE gibbonPerson.gibbonPersonID=:gibbonPersonID AND gibbonPerson.status='Full'";

        $person = $this->db->select($sql, $data)->fetch();
        if (empty($person)) return [];

        $person['name'] = Format::name('', $person['preferredName'], $person['surname'], 'Student', false);
        $person['roleCategory'] = $person['roleCategory'] ?: 'Other';

        return $person;
    }

    /**
     * Every active user, as gibbonPersonID => "Surname, Name (Parent)", to choose who a request is for.
     */
    public function getPeopleOptions(): array
    {
        $sql = "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.surname, gibbonPerson.preferredName, gibbonRole.category AS roleCategory
                FROM gibbonPerson
                LEFT JOIN gibbonRole ON (gibbonRole.gibbonRoleID=gibbonPerson.gibbonRoleIDPrimary)
                WHERE gibbonPerson.status='Full'
                ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

        $people = [];
        foreach ($this->db->select($sql)->fetchAll() as $row) {
            $people[intval($row['gibbonPersonID'])] = Format::name('', $row['preferredName'], $row['surname'], 'Student', true).' ('.__($row['roleCategory'] ?: 'Other').')';
        }

        return $people;
    }
}
