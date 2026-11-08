<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\administration\Automation;

use Gibbon\Forms\Form;
use Gibbon\Module\administration\Domain\PersonGateway;
use Gibbon\Module\administration\Domain\TeamGateway;

/**
 * Builds the action slots of the rule editor from the parameter definitions of the registered actions,
 * and reads them back. Nothing here knows about a specific action.
 */
class ActionForm
{
    const SLOTS = 5;

    /**
     * @param array $existing the saved actions: [['type' => key, 'params' => [...]], ...]
     */
    public static function addSlots(Form $form, $container, array $existing = [], int $slots = self::SLOTS): void
    {
        $actions = ActionRegistry::all();
        $teams = $container->get(TeamGateway::class)->selectOptions();
        $people = $container->get(PersonGateway::class)->selectOptions();   // once, not once per slot

        for ($i = 0; $i < $slots; $i++) {
            $saved = $existing[$i] ?? ['type' => '', 'params' => []];

            $row = $form->addRow();
            $row->addLabel('actionType'.$i, sprintf(__('Action %1$s'), $i + 1));
            $row->addSelect('actionType'.$i)->fromArray(ActionRegistry::options())->selected($saved['type'])->placeholder();

            foreach ($actions as $key => $action) {
                $class = 'act'.$i.'_'.$key;
                foreach ($action->params($container) as $name => $definition) {
                    $field = $class.'_'.$name;
                    $value = $saved['type'] === $key ? ($saved['params'][$name] ?? '') : '';

                    $row = $form->addRow()->addClass($class);
                    $label = $row->addLabel($field, $definition['label'])->addClass('pl-8');
                    if (!empty($definition['description'])) {
                        $label->description($definition['description']);
                    }

                    switch ($definition['type']) {
                        case 'textarea':
                            $row->addTextArea($field)->setRows(3)->setValue($value);
                            break;
                        case 'number':
                            $row->addNumber($field)->minimum(0)->maximum(3650)->setValue($value);
                            break;
                        case 'select':
                            $row->addSelect($field)->fromArray($definition['options'] ?? [])->selected($value);
                            break;
                        case 'team':
                            $row->addSelect($field)->fromArray($teams)->selected($value)->placeholder();
                            break;
                        case 'person':
                            $row->addSelectPerson($field)->fromArray($people)->selected($value)->placeholder();
                            break;
                        default:
                            $row->addTextField($field)->maxLength(255)->setValue($value);
                    }
                }
                $form->toggleVisibilityByClass($class)->onSelect('actionType'.$i)->when($key);
            }
        }
    }

    /**
     * @return array [array $actions (to store as JSON), array $problems (labels of required parameters left empty)]
     */
    public static function collect(array $post, $container, int $slots = self::SLOTS): array
    {
        $out = [];
        $problems = [];

        for ($i = 0; $i < $slots; $i++) {
            $action = ActionRegistry::get((string)($post['actionType'.$i] ?? ''));
            if ($action === null) {
                continue;
            }

            $params = [];
            foreach ($action->params($container) as $name => $definition) {
                $value = $post['act'.$i.'_'.$action->key().'_'.$name] ?? '';
                $value = is_array($value) ? '' : trim((string)$value);
                if ($definition['type'] === 'select' && $value !== '' && !isset($definition['options'][$value])) {
                    $value = '';
                }
                if (!empty($definition['required']) && $value === '') {
                    $problems[] = $action->label().': '.$definition['label'];
                }
                $params[$name] = $value;
            }
            $out[] = ['type' => $action->key(), 'params' => $params];
        }

        return [$out, $problems];
    }
}
