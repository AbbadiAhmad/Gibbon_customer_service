<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/

namespace Gibbon\Module\ServiceDesk\Automation;

use Gibbon\Forms\Form;

/**
 * Everything about the settings of automation scripts, in one place and independent of any script:
 * reading the definitions (HasSettings), putting them on the stage form, checking what was posted, storing it as
 * JSON on the stage, and handing the values to a running script.
 *
 * The stored value is  {"ScriptName": {"settingName": value}}  in the column automationSettings of the stage. Only
 * settings that the script currently defines are ever kept or read; everything else is dropped.
 */
class AutomationSettings
{
    public const TYPES = ['select', 'text', 'number', 'yesno'];

    protected $registry;

    public function __construct(AutomationRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * The usable settings of a script: [] for a script without settings, a setting with an unknown type or a
     * name that is not letters and digits is ignored. A script that fails while describing itself has none.
     */
    public function definitions(?AutomationScript $script): array
    {
        if (!($script instanceof HasSettings)) return [];

        try {
            $definitions = $script->getSettings();
        } catch (\Throwable $e) {
            error_log('ServiceDesk: automation settings of '.get_class($script).' failed: '.$e->getMessage());
            return [];
        }

        $usable = [];
        foreach ($definitions as $name => $definition) {
            if (!is_array($definition) || !preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', (string) $name) || !in_array($definition['type'] ?? '', self::TYPES, true)) continue;
            $usable[$name] = $definition + ['label' => $name, 'description' => '', 'required' => false];
        }

        return $usable;
    }

    /**
     * The options of a select setting as value => label, with values as strings. A list that cannot be read is empty.
     */
    public function options(array $definition): array
    {
        $options = $definition['options'] ?? [];
        try {
            $options = is_callable($options) ? $options() : $options;
        } catch (\Throwable $e) {
            error_log('ServiceDesk: options of an automation setting failed: '.$e->getMessage());
            return [];
        }

        $clean = [];
        foreach ((array) $options as $value => $label) {
            $clean[(string) $value] = (string) $label;
        }

        return $clean;
    }

    public static function decode($json): array
    {
        $decoded = json_decode((string) $json, true);

        return is_array($decoded) ? $decoded : [];
    }

    public static function inputName(string $script, string $setting): string
    {
        return 'setting_'.$script.'_'.$setting;
    }

    /**
     * Adds the rows of every script that has settings to the stage form. A script's rows are shown only while its box
     * in the checkbox group $checkboxName is ticked.
     *
     * @param array $stored The decoded automationSettings of the stage.
     */
    public function addToForm(Form $form, string $checkboxName, array $stored)
    {
        foreach ($this->registry->all() as $key => $script) {
            $definitions = $this->definitions($script);
            if (empty($definitions)) continue;

            $class = 'automationSettings_'.$key;
            $form->toggleVisibilityByClass($class)->onCheckbox($checkboxName)->when($key);

            $row = $form->addRow()->addClass($class);
            $row->addContent('<b>'.htmlspecialchars(__m('Settings of "{script}"', ['script' => $script->getName()])).'</b>');

            foreach ($definitions as $name => $definition) {
                $input = self::inputName($key, $name);
                $value = $stored[$key][$name] ?? ($definition['default'] ?? null);

                $row = $form->addRow()->addClass($class);
                $row->addLabel($input, $definition['label'])->description($definition['description']);

                switch ($definition['type']) {
                    case 'select':
                        $options = $this->options($definition);
                        $select = $row->addSelect($input)->fromArray($options)->selected((string) $value)->required(!empty($definition['required']));
                        if (empty($definition['required'])) $select->placeholder(__m('Default'));
                        break;
                    case 'number':
                        $number = $row->addNumber($input)->setValue($value)->required(!empty($definition['required']));
                        if (isset($definition['min'])) $number->minimum($definition['min']);
                        if (isset($definition['max'])) $number->maximum($definition['max']);
                        break;
                    case 'yesno':
                        $row->addYesNo($input)->selected($value == 'Y' ? 'Y' : 'N');
                        break;
                    default:
                        $row->addTextField($input)->setValue((string) $value)->maxLength($definition['maxLength'] ?? 100)->required(!empty($definition['required']));
                }
            }
        }
    }

    /**
     * Reads the posted settings of the ticked scripts and checks every value against the definitions. Settings of
     * scripts that are not ticked, of unknown scripts, or with unknown names are ignored.
     *
     * @param string[] $ticked Script names ticked on the stage.
     * @return array [settings to store (script => name => value), errors (list of messages)]
     */
    public function collect(array $ticked, array $post): array
    {
        $settings = [];
        $errors = [];

        foreach ($ticked as $key) {
            $definitions = $this->definitions($this->registry->get($key));

            foreach ($definitions as $name => $definition) {
                $raw = $post[self::inputName($key, $name)] ?? '';
                $raw = is_string($raw) ? trim($raw) : '';

                list($value, $error) = $this->check($definition, $raw);
                if ($error !== null) {
                    $errors[] = $definition['label'].': '.$error;
                } elseif ($value !== null) {
                    $settings[$key][$name] = $value;
                }
            }
        }

        return [$settings, $errors];
    }

    /**
     * Checks one value. Returns [value or null for "not set", error message or null].
     */
    protected function check(array $definition, string $raw): array
    {
        $required = !empty($definition['required']);

        if ($definition['type'] == 'yesno') {
            return [$raw == 'Y' ? 'Y' : 'N', null];
        }

        if ($raw === '') {
            return $required && !isset($definition['default']) ? [null, __m('a value is required')] : [null, null];
        }

        switch ($definition['type']) {
            case 'select':
                return array_key_exists($raw, $this->options($definition)) ? [$raw, null] : [null, __m('this choice is not available')];

            case 'number':
                if (!is_numeric($raw)) return [null, __m('a number is required')];
                $number = $raw + 0;
                if ((isset($definition['min']) && $number < $definition['min']) || (isset($definition['max']) && $number > $definition['max'])) {
                    return [null, __m('the number is out of range')];
                }
                return [$number, null];

            default:
                return mb_strlen($raw) > ($definition['maxLength'] ?? 100) ? [null, __m('the text is too long')] : [$raw, null];
        }
    }

    /**
     * The values a running script gets: its defaults, then the stored values. A stored value that is not valid any
     * more (a select value that is no longer offered, a number out of range) throws: the stage then fails and the ticket
     * stays where it was, with the reason on the ticket - it never falls back to another value, which could record
     * something else than the administrator chose. A required setting without a value throws too.
     *
     * @throws \RuntimeException
     */
    public function resolve(AutomationScript $script, string $key, $storedJson): array
    {
        $stored = self::decode($storedJson)[$key] ?? [];
        $values = [];

        foreach ($this->definitions($script) as $name => $definition) {
            if (array_key_exists($name, (array) $stored) && $stored[$name] !== null && $stored[$name] !== '') {
                list($value, $error) = $this->check($definition, (string) $stored[$name]);
                if ($error !== null) {
                    throw new \RuntimeException(__m('The setting "{setting}" of "{script}" on this stage is no longer valid ({error}). Edit the stage in Manage Workflows.', [
                        'setting' => $definition['label'], 'script' => $script->getName(), 'error' => $error,
                    ]));
                }
                $values[$name] = $value;
            } elseif (!empty($definition['required']) && !isset($definition['default'])) {
                throw new \RuntimeException(__m('The setting "{setting}" of "{script}" has not been chosen on this stage. Edit the stage in Manage Workflows.', [
                    'setting' => $definition['label'], 'script' => $script->getName(),
                ]));
            } elseif (isset($definition['default'])) {
                $values[$name] = $definition['default'];
            }
        }

        return $values;
    }

    /**
     * A short text for the workflow list: the choices of the settings of a script on a stage.
     */
    public function describe(AutomationScript $script, string $key, $storedJson): string
    {
        $stored = self::decode($storedJson)[$key] ?? [];
        $parts = [];

        foreach ($this->definitions($script) as $name => $definition) {
            if (!isset($stored[$name]) || $stored[$name] === '') continue;

            $value = (string) $stored[$name];
            if ($definition['type'] == 'select') {
                $value = $this->options($definition)[$value] ?? __m('not available');
            } elseif ($definition['type'] == 'yesno') {
                $value = $value == 'Y' ? __('Yes') : __('No');
            }
            $parts[] = $definition['label'].': '.$value;
        }

        return implode('; ', $parts);
    }
}
