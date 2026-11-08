<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\administration\Service;

use Gibbon\Forms\Form;
use Gibbon\Services\Format;

/**
 * Renders the custom fields of a ticket type into a form, reads them back from a
 * submitted request, and formats stored values for display.
 * Inputs are named cf_<fieldKey>.
 */
class CustomFields
{
    const TYPES = [
        'varchar'    => 'Short text',
        'text'       => 'Long text',
        'number'     => 'Number',
        'date'       => 'Date',
        'select'     => 'Dropdown',
        'checkboxes' => 'Checkboxes',
        'radio'      => 'Radio buttons',
        'yesno'      => 'Yes / No',
    ];

    public static function typeLabels(): array
    {
        return array_map('__', self::TYPES);
    }

    /** Comma separated option string => trimmed list. */
    public static function optionList($options): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string)$options)), 'strlen'));
    }

    public static function slug(string $label): string
    {
        $key = strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', trim($label)));
        return substr(trim($key, '_'), 0, 50);
    }

    public static function addToForm(Form $form, array $fields, array $values = []): void
    {
        foreach ($fields as $field) {
            $name = 'cf_'.$field['fieldKey'];
            $value = $values[$field['fieldKey']] ?? null;
            $options = self::optionList($field['options']);

            $row = $form->addRow();
            $label = $row->addLabel($name, $field['label']);
            if (!empty($field['description'])) {
                $label->description($field['description']);
            }

            switch ($field['fieldType']) {
                case 'text':
                    $input = $row->addTextArea($name)->setRows(4);
                    if ($value !== null) $input->setValue($value);
                    break;
                case 'number':
                    $input = $row->addNumber($name)->decimalPlaces(2);
                    if ($value !== null) $input->setValue($value);
                    break;
                case 'date':
                    $input = $row->addDate($name);
                    if (!empty($value)) $input->setValue(Format::date($value));
                    break;
                case 'select':
                    $input = $row->addSelect($name)->fromArray($options)->placeholder();
                    if ($value !== null) $input->selected($value);
                    break;
                case 'checkboxes':
                    $input = $row->addCheckbox($name)->fromArray($options);
                    if (is_array($value)) $input->checked($value);
                    break;
                case 'radio':
                    $input = $row->addRadio($name)->fromArray($options);
                    if ($value !== null) $input->checked($value);
                    break;
                case 'yesno':
                    $input = $row->addYesNo($name)->placeholder();
                    if ($value !== null) $input->selected($value);
                    break;
                default:
                    $length = (int)$field['options'];
                    $input = $row->addTextField($name)->maxLength($length > 0 ? $length : 255);
                    if ($value !== null) $input->setValue($value);
                    break;
            }

            if ($field['required'] == 'Y') {
                $input->required();
            }
        }
    }

    /**
     * Read the submitted values for the given fields.
     * @return array [array $values keyed by fieldKey, array $missing labels of required fields left empty]
     */
    /**
     * Clean one raw answer according to the definition of a ticket field ('fieldType', 'options'):
     * a choice must be one of the options, a date a valid date, a number numeric; checkboxes give a list, everything else text.
     * Used for the agent's field form and for form answers that are mapped to a ticket field.
     *
     * @return string|int|float|array '' / [] when the answer is empty or invalid
     */
    public static function normalize(array $field, $raw)
    {
        switch ($field['fieldType']) {
            case 'checkboxes':
                return array_values(array_intersect(array_map('strval', (array)$raw), self::optionList($field['options'])));
            case 'select':
            case 'radio':
                return is_scalar($raw) && in_array((string)$raw, self::optionList($field['options']), true) ? (string)$raw : '';
            case 'yesno':
                return in_array($raw, ['Y', 'N'], true) ? $raw : '';
            case 'date':
                $date = is_scalar($raw) && trim((string)$raw) !== '' ? Format::dateConvert(trim((string)$raw)) : '';
                return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string)$date, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? $date : '';
            case 'number':
                return is_numeric($raw) ? $raw + 0 : '';
            default:
                $value = trim(is_array($raw) ? implode(', ', array_map('strval', $raw)) : (string)$raw);
                if ($field['fieldType'] == 'varchar' && (int)$field['options'] > 0) {
                    $value = mb_substr($value, 0, (int)$field['options']);
                }
                return $value;
        }
    }

    public static function collect(array $fields, array $post): array
    {
        $values = [];
        $missing = [];

        foreach ($fields as $field) {
            $value = self::normalize($field, $post['cf_'.$field['fieldKey']] ?? null);

            if ($field['required'] == 'Y' && ($value === '' || $value === [])) {
                $missing[] = $field['label'];
            }
            $values[$field['fieldKey']] = $value;
        }

        return [$values, $missing];
    }

    public static function format(array $field, $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '';
        }

        switch ($field['fieldType']) {
            case 'checkboxes':
                return implode(', ', (array)$value);
            case 'date':
                return Format::date($value);
            case 'yesno':
                return $value == 'Y' ? __('Yes') : __('No');
            default:
                return (string)$value;
        }
    }
}
