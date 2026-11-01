<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\administration\Service;

use Gibbon\Forms\Form;
use Gibbon\Module\administration\Domain\FieldGateway;

/**
 * The "field / operator / value" rows used for board filters and for the conditions of automation rules.
 */
class FilterForm
{
    const ROWS = 5;

    /** Options of the field select: core fields, special fields and every custom field key. */
    public static function fieldOptions($container): array
    {
        $options = [
            __('Ticket') => [
                'type' => __('Ticket type (name)'), 'status' => __('Status'), 'priority' => __('Priority'), 'team' => __('Team (name)'),
                'assignee' => __('Assignee (username)'), 'reporter' => __('Requested by (username)'), 'title' => __('Title'),
            ],
            __('Dates, tags and links') => BoardFilter::specialFields(),
        ];
        foreach ($container->get(FieldGateway::class)->selectDistinctKeys() as $f) {
            $options[__('Custom fields')][$f['fieldKey']] = $f['label'].' ['.$f['fieldKey'].'] - '.$f['typeNames'];
        }

        return $options;
    }

    public static function addRows(Form $form, $container, array $conditions, string $prefix = 'filter', int $rows = self::ROWS): void
    {
        $options = self::fieldOptions($container);

        for ($i = 0; $i < $rows; $i++) {
            $c = $conditions[$i] ?? ['field' => '', 'op' => 'equals', 'value' => ''];

            $row = $form->addRow();
            $row->addLabel($prefix.'Field'.$i, sprintf(__('Condition %1$s'), $i + 1));
            $col = $row->addColumn()->addClass('flex flex-wrap gap-2');
            $col->addSelect($prefix.'Field'.$i)->fromArray($options)->selected($c['field'])->placeholder()->addClass('flex-1');
            $col->addSelect($prefix.'Op'.$i)->fromArray(BoardFilter::operatorLabels())->selected($c['op'])->addClass('flex-1');
            $col->addTextField($prefix.'Value'.$i)->maxLength(255)->setValue($c['value'])->addClass('flex-1');
        }
    }

    /** Read the rows back: empty and invalid rows are dropped. */
    public static function collect(array $post, string $prefix = 'filter', int $rows = self::ROWS): array
    {
        $conditions = [];
        for ($i = 0; $i < $rows; $i++) {
            $conditions[] = [
                'field' => trim($post[$prefix.'Field'.$i] ?? ''),
                'op'    => $post[$prefix.'Op'.$i] ?? 'equals',
                'value' => trim($post[$prefix.'Value'.$i] ?? ''),
            ];
        }

        return BoardFilter::decode($conditions);
    }

    public static function help(): string
    {
        return __('Dates accept YYYY-MM-DD, "today" or a number of days from today such as +3 or -1 (e.g. Due date is before +3 = due within 3 days). Y/N fields use "is" Y or N.');
    }
}
