<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\administration\Service;

use Gibbon\Services\Format;

/**
 * Turns the answers of a request form into a ticket.
 *
 * A form is independent of the ticket type: it has its own questions (inputs). Every question is MAPPED to one field of the ticket:
 *
 *   title           the summary (several questions are joined with " - "; a form may also have a title template)
 *   description     the description. Several questions can go here: each becomes a "Question: answer" line, so choices and
 *                   checkboxes end up as text ("Interested in: After-school care, School bus")
 *   priority        Low / Medium / High / Urgent
 *   dateStart       start date            dateDue   due date
 *   attachments     uploaded files (file questions only)
 *   custom:<key>    a custom field of the ticket type (the answer is checked against that field: options, date, number...)
 *
 * A form can cover all ticket fields or only some of them; the ticket fields it does not cover stay empty (the agents fill them in later).
 * This class has no UI and no database access: the pages and the tests give it the form, its questions and the ticket type fields.
 */
class FormMapper
{
    const CORE_TARGETS = ['title', 'description', 'priority', 'dateStart', 'dateDue', 'attachments'];
    const PRIORITIES = ['Low', 'Medium', 'High', 'Urgent'];
    const CHOICES = ['select', 'radio', 'checkboxes'];

    /** Input types of a question => label. */
    public static function inputTypes(): array
    {
        return CustomFields::typeLabels() + ['file' => __('File upload'), 'info' => __('Information text (no answer)')];
    }

    /** Name of the HTML input of a question. */
    public static function inputName(string $fieldKey): string
    {
        return 'ff_'.$fieldKey;
    }

    public static function slug(string $text, string $fallback = 'form'): string
    {
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
        return substr($slug, 0, 60) ?: $fallback;
    }

    /** The target a new question gets by default. */
    public static function defaultTarget(string $inputType): string
    {
        return $inputType === 'file' ? 'attachments' : ($inputType === 'info' ? '' : 'description');
    }

    /** Options for the "maps to" select: the basic ticket fields, then the custom fields of the ticket type. */
    public static function targetOptions(array $typeFields): array
    {
        $options = [__('Ticket fields') => [
            'title' => __('Title'), 'description' => __('Description'), 'priority' => __('Priority'),
            'dateStart' => __('Start date'), 'dateDue' => __('Due date'), 'attachments' => __('Attachments'),
        ]];
        foreach ($typeFields as $field) {
            if (($field['internal'] ?? 'N') != 'Y') {
                $options[__('Fields of the ticket type')]['custom:'.$field['fieldKey']] = $field['label'];
            }
        }
        return $options;
    }

    public static function targetLabel(string $target, array $typeFields): string
    {
        if ($target === '') {
            return '';
        }
        foreach (self::targetOptions($typeFields) as $group) {
            if (isset($group[$target])) {
                return $group[$target];
            }
        }
        return $target.' ('.__('unavailable').')';
    }

    /** Find the definition of the custom field a target points to. */
    public static function customField(string $target, array $typeFields): ?array
    {
        if (strpos($target, 'custom:') !== 0) {
            return null;
        }
        $key = substr($target, 7);
        foreach ($typeFields as $field) {
            if ($field['fieldKey'] === $key && ($field['internal'] ?? 'N') != 'Y') {
                return $field;
            }
        }
        return null;
    }

    /**
     * Is this question mapped to something that can hold its answer?
     *
     * @param array $question inputType, options, target
     * @return string '' when fine, otherwise what is wrong
     */
    public static function validateMapping(array $question, array $typeFields): string
    {
        $input = $question['inputType'] ?? '';
        $target = $question['target'] ?? '';
        $options = CustomFields::optionList($question['options'] ?? '');

        if (!isset(self::inputTypes()[$input])) {
            return __('Unknown input type.');
        }
        if (in_array($input, self::CHOICES, true) && empty($options)) {
            return __('A choice needs its options (comma separated).');
        }
        if ($input === 'info') {
            return $target === '' ? '' : __('Information text is not mapped to a ticket field.');
        }
        if ($input === 'file') {
            return $target === 'attachments' ? '' : __('A file upload can only be mapped to Attachments.');
        }
        if ($target === 'attachments') {
            return __('Attachments can only be filled by a file upload.');
        }
        if (in_array($target, ['title', 'description'], true)) {
            return '';
        }
        if (in_array($target, ['dateStart', 'dateDue'], true)) {
            return $input === 'date' ? '' : __('A date field needs a date input.');
        }
        if ($target === 'priority') {
            $invalid = array_diff($options, self::PRIORITIES);
            return in_array($input, ['select', 'radio'], true) && empty($invalid) ? '' : __('Priority needs a dropdown or radio buttons whose options are Low, Medium, High or Urgent.');
        }

        $field = self::customField($target, $typeFields);
        if ($field === null) {
            return __('Choose a field of the ticket.');
        }
        $targetOptions = CustomFields::optionList($field['options']);
        switch ($field['fieldType']) {
            case 'select':
            case 'radio':
                if (!in_array($input, ['select', 'radio'], true) || array_diff($options, $targetOptions)) {
                    return sprintf(__('"%1$s" accepts a dropdown or radio buttons whose options are: %2$s.'), $field['label'], implode(', ', $targetOptions));
                }
                break;
            case 'checkboxes':
                if (!in_array($input, self::CHOICES, true) || array_diff($options, $targetOptions)) {
                    return sprintf(__('"%1$s" accepts choices among: %2$s.'), $field['label'], implode(', ', $targetOptions));
                }
                break;
            case 'yesno':
            case 'date':
            case 'number':
                if ($input !== $field['fieldType']) {
                    return sprintf(__('"%1$s" needs an input of the same kind (%2$s).'), $field['label'], self::inputTypes()[$field['fieldType']]);
                }
                break;
        }

        return '';
    }

    /** The answer as text (for the description, the title and the title template). */
    public static function text($answer, string $inputType): string
    {
        if ($answer === '' || $answer === [] || $answer === null) {
            return '';
        }
        switch ($inputType) {
            case 'checkboxes':
                return implode(', ', (array)$answer);
            case 'yesno':
                return $answer == 'Y' ? __('Yes') : __('No');
            case 'date':
                return Format::date($answer);
            default:
                return (string)$answer;
        }
    }

    /**
     * Questions to add to a form: every field of the ticket type that no question is mapped to yet.
     * $withCore also offers the basic fields (title, description, priority, dates, attachments), for a brand new form.
     *
     * @return array of ['fieldKey', 'label', 'helpText', 'inputType', 'options', 'required', 'target']
     */
    public static function suggest(array $typeFields, array $existing, bool $withCore): array
    {
        $mapped = array_column($existing, 'target');
        $keys = array_column($existing, 'fieldKey');
        $out = [];
        $add = function (array $question) use (&$out, &$keys, &$mapped) {
            $base = $question['fieldKey'];
            for ($i = 2; in_array($question['fieldKey'], $keys, true); $i++) {
                $question['fieldKey'] = $base.'_'.$i;
            }
            $keys[] = $question['fieldKey'];
            $mapped[] = $question['target'];
            $out[] = $question + ['helpText' => '', 'options' => '', 'required' => 'N'];
        };

        if ($withCore) {
            $core = [
                ['title', __('Summary'), 'varchar', '200', 'Y'], ['description', __('Description'), 'text', '', 'N'],
                ['priority', __('Priority'), 'select', implode(',', self::PRIORITIES), 'N'],
                ['dateStart', __('Start Date'), 'date', '', 'N'], ['dateDue', __('Due Date'), 'date', '', 'N'],
                ['attachments', __('Attachments'), 'file', '', 'N'],
            ];
            foreach ($core as [$target, $label, $type, $options, $required]) {
                if (!in_array($target, $mapped, true)) {
                    $add(['fieldKey' => $target, 'label' => $label, 'inputType' => $type, 'options' => $options, 'required' => $required, 'target' => $target]);
                }
            }
        }
        foreach ($typeFields as $field) {
            $target = 'custom:'.$field['fieldKey'];
            if (($field['internal'] ?? 'N') != 'Y' && !in_array($target, $mapped, true)) {
                $add(['fieldKey' => $field['fieldKey'], 'label' => $field['label'], 'helpText' => (string)$field['description'], 'inputType' => $field['fieldType'],
                    'options' => (string)$field['options'], 'required' => $field['required'], 'target' => $target]);
            }
        }

        return $out;
    }

    /**
     * Read the answers and build the ticket values.
     *
     * @param array $form        name, titleTemplate
     * @param array $questions   the form fields in order: fieldKey, label, inputType, options, required, target
     * @param array $typeFields  the custom fields of the ticket type
     * @param array $post        the request
     * @param array $uploaded    fieldKeys of the file questions that received a file
     * @return array errors (labels of what is missing / wrong), title, description, priority, dateStart, dateDue, customData
     */
    public static function map(array $form, array $questions, array $typeFields, array $post, array $uploaded = []): array
    {
        $errors = [];
        $titles = [];
        $descriptionParts = [];
        $priority = null;
        $dates = ['dateStart' => null, 'dateDue' => null];
        $customData = [];
        $texts = [];

        foreach ($questions as $q) {
            $input = $q['inputType'];
            if ($input === 'info') {
                continue;
            }
            if ($input === 'file') {
                if ($q['required'] == 'Y' && !in_array($q['fieldKey'], $uploaded, true)) {
                    $errors[] = $q['label'];
                }
                continue;
            }

            $answer = CustomFields::normalize(['fieldType' => $input, 'options' => $q['options']], $post[self::inputName($q['fieldKey'])] ?? null);
            $text = self::text($answer, $input);
            $texts[$q['fieldKey']] = $text;

            if ($q['required'] == 'Y' && ($answer === '' || $answer === [])) {
                $errors[] = $q['label'];
                continue;
            }
            if ($answer === '' || $answer === []) {
                continue;
            }

            $target = $q['target'];
            if ($target === 'title') {
                $titles[] = $text;
            } elseif ($target === 'description') {
                $descriptionParts[] = ['label' => $q['label'], 'text' => $text, 'input' => $input];
            } elseif ($target === 'priority') {
                foreach (self::PRIORITIES as $p) {
                    if ($priority === null && strcasecmp($p, $text) === 0) {
                        $priority = $p;
                    }
                }
            } elseif (array_key_exists($target, $dates)) {
                $dates[$target] = $dates[$target] ?? $answer;
            } elseif (($field = self::customField($target, $typeFields)) !== null) {
                $value = CustomFields::normalize($field, $answer);
                $key = $field['fieldKey'];
                if ($value === '' || $value === []) {
                    continue;
                }
                if (!isset($customData[$key]) || $customData[$key] === '' || $customData[$key] === []) {
                    $customData[$key] = $value;
                } elseif (is_array($value)) {
                    $customData[$key] = array_values(array_unique(array_merge((array)$customData[$key], $value)));
                } elseif (in_array($field['fieldType'], ['varchar', 'text'], true)) {
                    $customData[$key] .= "\n".$value;
                }
            }
        }

        if (!empty($dates['dateStart']) && !empty($dates['dateDue']) && $dates['dateDue'] < $dates['dateStart']) {
            $errors[] = __('Dates');
        }

        // Title: the questions mapped to it, else the template ("Absence {child_name}"), else the name of the form.
        $title = implode(' - ', $titles);
        if ($title === '' && trim((string)($form['titleTemplate'] ?? '')) !== '') {
            $title = preg_replace_callback('/\{([A-Za-z0-9_]+)\}/', function ($m) use ($texts) {
                return $texts[$m[1]] ?? '';
            }, $form['titleTemplate']);
            $title = trim(preg_replace('/\s+/', ' ', $title), " -,:;");
        }
        if ($title === '') {
            $title = (string)$form['name'];
        }

        return [
            'errors'      => $errors,
            'title'       => mb_substr($title, 0, 200),
            'description' => self::describe($descriptionParts),
            'priority'    => $priority ?? 'Medium',
            'dateStart'   => $dates['dateStart'],
            'dateDue'     => $dates['dateDue'],
            'customData'  => $customData,
        ];
    }

    /** The description from the questions mapped to it. One long text alone is kept as it is; otherwise "Question: answer" lines. */
    public static function describe(array $parts): string
    {
        if (count($parts) === 1 && in_array($parts[0]['input'], ['text', 'varchar'], true)) {
            return $parts[0]['text'];
        }
        $lines = [];
        foreach ($parts as $part) {
            $lines[] = strpos($part['text'], "\n") !== false ? $part['label'].":\n".$part['text'] : $part['label'].': '.$part['text'];
        }
        return implode("\n", $lines);
    }
}
