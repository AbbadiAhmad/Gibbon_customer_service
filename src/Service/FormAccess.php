<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\administration\Service;

/**
 * Who may open and submit a form. This is a property of the FORM, not of the ticket type behind it.
 *
 *   audience  Gibbon role categories (Staff, Student, Parent, Other)
 *   roles     specific Gibbon roles (ids)
 *
 * Nothing set = every registered (logged in) user. Something set = the user's current role category is in the audience OR the user's
 * current role is in the list. (Anonymous access is not supported: every form needs a Gibbon login.)
 */
class FormAccess
{
    public static function audienceOptions(): array
    {
        return ['Staff' => __('Staff'), 'Student' => __('Students'), 'Parent' => __('Parents'), 'Other' => __('Other users')];
    }

    public static function parseList($csv): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string)$csv)), 'strlen'));
    }

    /** May a user with this current role category / role open the form? (The form must also be active: see FormGateway.) */
    public static function allows(array $form, string $roleCategory, $gibbonRoleID): bool
    {
        $categories = self::parseList($form['audience'] ?? '');
        $roles = self::parseList($form['roles'] ?? '');

        if (empty($categories) && empty($roles)) {
            return true;
        }
        return in_array($roleCategory, $categories, true) || in_array((string)(int)$gibbonRoleID, $roles, true);
    }

    /** Short text for lists: "Everyone", "Staff, Parents", "Staff, Role: Teacher". */
    public static function summary(array $form, array $roleNames = []): string
    {
        $parts = [];
        foreach (self::parseList($form['audience'] ?? '') as $category) {
            $parts[] = self::audienceOptions()[$category] ?? $category;
        }
        foreach (self::parseList($form['roles'] ?? '') as $roleID) {
            $parts[] = __('Role').': '.($roleNames[(int)$roleID] ?? $roleID);
        }
        return empty($parts) ? __('Every registered user') : implode(', ', $parts);
    }
}
