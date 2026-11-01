<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\CustomerService\Service;

/**
 * The request form of a ticket type, configured like a Jira Service Management request type:
 * the basic (core) fields can be shown/hidden, made required, relabelled and given help text; custom fields can be
 * shown on the portal form or kept for the agents; everything has an order.
 *
 * Core field settings live in gibbonCsTicketType.formConfig (JSON, key => {show, required, label, help, order}).
 * Custom field settings live on the field itself (gibbonCsField.showOnForm / required / sequenceNumber).
 */
class FormConfig
{
    /** The summary can never be hidden or optional: a ticket needs a title. */
    const LOCKED = ['title'];

    public static function defaults(): array
    {
        return [
            'title'       => ['label' => __('Summary'),     'help' => '', 'show' => true, 'required' => true,  'order' => 1],
            'description' => ['label' => __('Description'), 'help' => '', 'show' => true, 'required' => false, 'order' => 2],
            'priority'    => ['label' => __('Priority'),    'help' => '', 'show' => true, 'required' => false, 'order' => 3],
            'dateStart'   => ['label' => __('Start Date'),  'help' => __('Optional. When the work should begin.'), 'show' => true, 'required' => false, 'order' => 4],
            'dateDue'     => ['label' => __('Due Date'),    'help' => __('Optional. When you need it done.'),     'show' => true, 'required' => false, 'order' => 5],
            'attachments' => ['label' => __('Attachments'), 'help' => '', 'show' => true, 'required' => false, 'order' => 1000],
        ];
    }

    /** Defaults overlaid with what the type stored. */
    public static function core($json): array
    {
        $config = self::defaults();
        $saved = is_array($json) ? $json : json_decode((string)$json, true);

        foreach ((array)$saved as $key => $values) {
            if (!isset($config[$key]) || !is_array($values)) {
                continue;
            }
            foreach (['show', 'required'] as $flag) {
                if (isset($values[$flag])) {
                    $config[$key][$flag] = (bool)$values[$flag];
                }
            }
            foreach (['label', 'help'] as $text) {
                if (isset($values[$text]) && trim((string)$values[$text]) !== '') {
                    $config[$key][$text] = mb_substr(trim((string)$values[$text]), 0, 255);
                }
            }
            if (isset($values['order'])) {
                $config[$key]['order'] = (int)$values['order'];
            }
        }

        foreach (self::LOCKED as $key) {
            $config[$key]['show'] = true;
            $config[$key]['required'] = true;
        }

        return $config;
    }

    /**
     * What the portal form shows, in order: shown core fields and custom fields marked "show on form".
     *
     * @return array of ['kind' => 'core'|'custom', 'key' => string, 'order' => int, + core: label/help/required, custom: field]
     */
    public static function items($formConfigJson, array $customFields): array
    {
        $items = [];
        foreach (self::core($formConfigJson) as $key => $c) {
            if ($c['show']) {
                $items[] = ['kind' => 'core', 'key' => $key] + $c;
            }
        }
        foreach ($customFields as $field) {
            if (($field['showOnForm'] ?? 'Y') == 'Y') {
                $items[] = ['kind' => 'custom', 'key' => $field['fieldKey'], 'order' => (int)$field['sequenceNumber'], 'field' => $field];
            }
        }

        // Stable sort: same order keeps core fields before custom ones.
        usort($items, function ($a, $b) {
            return [$a['order'], $a['kind'] == 'custom'] <=> [$b['order'], $b['kind'] == 'custom'];
        });

        return $items;
    }

    /** Read the form editor back into the JSON stored on the type (only known core keys). */
    public static function fromPost(array $post): string
    {
        $config = [];
        foreach (array_keys(self::defaults()) as $key) {
            $config[$key] = [
                'show'     => in_array($key, self::LOCKED, true) ? true : (($post['fmShow_'.$key] ?? 'Y') == 'Y'),
                'required' => in_array($key, self::LOCKED, true) ? true : (($post['fmRequired_'.$key] ?? 'N') == 'Y'),
                'label'    => trim((string)($post['fmLabel_'.$key] ?? '')),
                'help'     => trim((string)($post['fmHelp_'.$key] ?? '')),
                'order'    => (int)($post['fmOrder_'.$key] ?? self::defaults()[$key]['order']),
            ];
        }
        return json_encode($config, JSON_UNESCAPED_UNICODE);
    }

    /** Role categories a request type can be limited to. */
    public static function audienceOptions(): array
    {
        return ['Staff' => __('Staff'), 'Student' => __('Students'), 'Parent' => __('Parents'), 'Other' => __('Other users')];
    }

    /** Is the request type open to someone in this Gibbon role category? An empty audience means everybody. */
    public static function inAudience($audience, string $roleCategory): bool
    {
        $allowed = array_filter(array_map('trim', explode(',', (string)$audience)));
        return empty($allowed) || in_array($roleCategory, $allowed, true);
    }
}
