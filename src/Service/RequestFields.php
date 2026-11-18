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

use Gibbon\Forms\Form;
use Gibbon\Services\Format;

/**
 * The request form of a ticket type: turns the field definitions into form rows, reads and checks what was posted,
 * and formats the stored answers for display.
 *
 * Answers are stored on the ticket as a JSON list of [name, label, type, value, display], so a ticket keeps showing
 * what was asked and answered even when the type's form is changed later. Automation scripts read answers by name.
 */
class RequestFields
{
    public const TYPES = ['Text', 'Long Text', 'Date', 'Select', 'Attendance Reason', 'Yes/No', 'Students', 'Classes'];

    protected $people;

    public function __construct(People $people)
    {
        $this->people = $people;
    }

    public static function inputName(array $field): string
    {
        return 'field'.intval($field['gibbonServiceDeskFieldID']);
    }

    public static function parseOptions($options): array
    {
        $options = array_filter(array_map('trim', explode(',', (string) $options)), 'strlen');

        return array_combine($options, $options);
    }

    /**
     * Adds one row per field to the form. $user is [gibbonPersonID, roleCategory, gibbonSchoolYearID].
     */
    public function addToForm(Form $form, array $fields, array $user)
    {
        $students = $this->people->getSelectableStudents($user['gibbonPersonID'], $user['roleCategory'], $user['gibbonSchoolYearID']);

        foreach ($fields as $field) {
            $name = self::inputName($field);
            $required = $field['required'] == 'Y';

            $row = $form->addRow();
            $row->addLabel($name, __m($field['label']))->description(__m($field['description']));

            switch ($field['type']) {
                case 'Long Text':
                    $row->addTextArea($name)->setRows(5)->required($required);
                    break;

                case 'Date':
                    $row->addDate($name)->required($required);
                    break;

                case 'Select':
                    $row->addSelect($name)->fromArray(self::parseOptions($field['options']))->placeholder()->required($required);
                    break;

                case 'Attendance Reason':
                    // The reasons of School Admin > Attendance Settings, read now, so a change there shows at once.
                    $row->addSelect($name)->fromArray(array_map('__m', $this->people->getAttendanceReasons()))->placeholder()->required($required);
                    break;

                case 'Yes/No':
                    $row->addYesNo($name)->selected('N');
                    break;

                case 'Students':
                    if ($students === null) {
                        $row->addSelectStudent($name, $user['gibbonSchoolYearID'])->selectMultiple()->required($required);
                    } elseif (empty($students)) {
                        $row->addContent(Format::alert(__m('There are no students linked to your account.'), 'warning'));
                    } else {
                        $row->addCheckbox($name)->fromArray($students)->checked(count($students) == 1 ? array_keys($students) : []);
                    }
                    break;

                case 'Classes':
                    if ($students === null) {
                        $row->addTextField($name)->maxLength(255)->required($required)->placeholder(__m('Student and class'));
                    } else {
                        $classes = $this->people->getClassesByStudents(array_keys($students), $user['gibbonSchoolYearID']);
                        if (empty($classes)) {
                            $row->addContent(Format::alert(__m('There are no classes to choose from.'), 'warning'));
                        } else {
                            $row->addCheckbox($name)->fromArray($classes);
                        }
                    }
                    break;

                default:
                    $row->addTextField($name)->maxLength(255)->required($required);
            }
        }
    }

    /**
     * Reads the posted answers. Every answer is checked again against what the form offered to this user.
     *
     * @return array [answers, valid]
     */
    public function collect(array $fields, array $post, array $user): array
    {
        $answers = [];
        $valid = true;
        $students = $this->people->getSelectableStudents($user['gibbonPersonID'], $user['roleCategory'], $user['gibbonSchoolYearID']);

        foreach ($fields as $field) {
            $raw = $post[self::inputName($field)] ?? '';
            $value = '';
            $display = null;

            switch ($field['type']) {
                case 'Date':
                    $date = is_string($raw) ? Format::dateConvert(trim($raw)) : '';
                    $value = self::isDate($date) ? $date : '';
                    break;

                case 'Select':
                    $value = is_string($raw) && array_key_exists($raw, self::parseOptions($field['options'])) ? $raw : '';
                    break;

                case 'Attendance Reason':
                    $value = is_string($raw) && array_key_exists($raw, $this->people->getAttendanceReasons()) ? $raw : '';
                    break;

                case 'Yes/No':
                    $value = $raw === 'Y' ? 'Y' : 'N';
                    break;

                case 'Students':
                    $value = $this->people->filterSelectableStudents((array) $raw, $user['gibbonPersonID'], $user['roleCategory'], $user['gibbonSchoolYearID']);
                    $names = $students ?? $this->people->getNames($value);
                    $display = implode(', ', array_intersect_key($names, array_flip($value)));
                    break;

                case 'Classes':
                    if ($students === null) {
                        $value = is_string($raw) ? mb_substr(trim($raw), 0, 255) : '';
                    } else {
                        $classes = $this->people->getClassesByStudents(array_keys($students), $user['gibbonSchoolYearID']);
                        $chosen = array_intersect_key($classes, array_flip(array_map('strval', (array) $raw)));
                        $value = [];
                        foreach (array_keys($chosen) as $key) {
                            list($gibbonPersonID, $gibbonCourseClassID) = explode('-', $key);
                            $value[] = ['gibbonPersonID' => intval($gibbonPersonID), 'gibbonCourseClassID' => intval($gibbonCourseClassID)];
                        }
                        $display = implode("\n", $chosen);
                    }
                    break;

                case 'Long Text':
                    $value = is_string($raw) ? trim($raw) : '';
                    break;

                default:
                    $value = is_string($raw) ? mb_substr(trim($raw), 0, 255) : '';
            }

            if ($field['required'] == 'Y' && $field['type'] != 'Yes/No' && empty($value)) {
                $valid = false;
            }

            $answer = ['name' => $field['name'], 'label' => $field['label'], 'type' => $field['type'], 'value' => $value];
            if ($display !== null) {
                $answer['display'] = $display;
            }
            $answers[] = $answer;
        }

        return [$answers, $valid];
    }

    /**
     * The stored answers of a ticket as [label, text] pairs for display (plain text, escape on output).
     */
    public static function describe($stored): array
    {
        $output = [];
        foreach (self::decode($stored) as $answer) {
            $value = $answer['value'] ?? '';
            if ($value === '' || $value === []) continue;

            switch ($answer['type'] ?? '') {
                case 'Date':   $text = Format::date($value); break;
                case 'Yes/No': $text = $value == 'Y' ? __('Yes') : __('No'); break;
                default:       $text = $answer['display'] ?? (is_array($value) ? json_encode($value) : $value);
            }
            $output[] = ['label' => __m($answer['label'] ?? $answer['name']), 'text' => $text];
        }

        return $output;
    }

    /**
     * The stored answers of a ticket as name => value, for automation scripts.
     */
    public static function values($stored): array
    {
        return array_column(self::decode($stored), 'value', 'name');
    }

    /**
     * Names of the people chosen in the first Students or Classes answer, for the default ticket subject.
     */
    public static function firstPeopleText(array $answers): string
    {
        foreach ($answers as $answer) {
            if ($answer['type'] == 'Students' && !empty($answer['display'])) {
                return $answer['display'];
            }
            if ($answer['type'] == 'Classes' && !empty($answer['display'])) {
                $names = array_map(function ($line) {
                    return trim(strstr($line, ':', true) ?: $line);
                }, explode("\n", $answer['display']));
                return implode(', ', array_unique($names));
            }
        }

        return '';
    }

    public static function decode($stored): array
    {
        if (is_array($stored)) return $stored;
        $decoded = json_decode((string) $stored, true);

        return is_array($decoded) ? $decoded : [];
    }

    public static function isDate($date): bool
    {
        return is_string($date) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /**
     * Plain text of the answers that people search for: details, children, classes, dates - one per line. Reasons
     * from lists and yes/no answers are included as typed, labels and field names are not (searching for "name" must
     * not find every ticket).
     */
    public static function searchText(array $answers): string
    {
        $lines = [];
        foreach ($answers as $answer) {
            $value = $answer['value'] ?? '';
            switch ($answer['type'] ?? '') {
                case 'Date':
                    if (self::isDate($value)) $lines[] = $value.' '.Format::date($value);
                    break;
                case 'Students':
                case 'Classes':
                    if (!empty($answer['display'])) $lines[] = $answer['display'];
                    elseif (is_string($value) && $value !== '') $lines[] = $value;
                    break;
                case 'Yes/No':
                    break;
                default:
                    if (is_string($value) && $value !== '') $lines[] = $value;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * One short line about what a request is for - the children, their classes and the dates - for notices to people
     * who must not read the whole ticket (teachers). Free text and reasons are left out on purpose.
     */
    public static function summary(array $answers): string
    {
        $names = [];
        $classes = [];
        $dates = [];

        foreach ($answers as $answer) {
            if (($answer['type'] ?? '') == 'Students' && !empty($answer['display'])) {
                foreach (explode(', ', $answer['display']) as $name) {
                    $names[$name] = true;
                }
            } elseif (($answer['type'] ?? '') == 'Classes' && !empty($answer['display'])) {
                foreach (explode("\n", $answer['display']) as $line) {
                    $parts = explode(': ', $line, 2);
                    if (count($parts) == 2) {
                        $names[$parts[0]] = true;
                        $classes[$parts[1]] = true;
                    }
                }
            } elseif (($answer['type'] ?? '') == 'Date' && self::isDate($answer['value'] ?? '')) {
                $dates[$answer['value']] = true;
            }
        }

        $parts = [];
        if (!empty($names)) $parts[] = implode(', ', array_keys($names));
        if (!empty($classes)) $parts[] = __m('classes').': '.implode(', ', array_keys($classes));
        if (!empty($dates)) {
            $dates = array_keys($dates);
            sort($dates);
            $parts[] = count($dates) > 1 ? Format::date($dates[0]).' - '.Format::date(end($dates)) : Format::date($dates[0]);
        }

        return implode('; ', $parts);
    }
}
