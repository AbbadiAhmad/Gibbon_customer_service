<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\CustomerService\Service;

/**
 * Catalog of the permissions that can be assigned to a (space) role.
 */
class Permissions
{
    const VIEW_BOARD = 'view_board';
    const COMMENT = 'comment';
    const EDIT_TICKET = 'edit_ticket';
    const TRANSITION = 'transition_ticket';
    const ASSIGN = 'assign_ticket';
    const DELETE_TICKET = 'delete_ticket';
    const MANAGE_BOARDS = 'manage_boards';
    const MANAGE_MEMBERS = 'manage_members';

    /** @return array permission key => label */
    public static function catalog(): array
    {
        return [
            self::VIEW_BOARD     => __('View boards and tickets of the space'),
            self::COMMENT        => __('Comment on tickets'),
            self::EDIT_TICKET    => __('Edit ticket title, priority, team and fields'),
            self::TRANSITION     => __('Move tickets between columns (change status)'),
            self::ASSIGN         => __('Assign tickets'),
            self::DELETE_TICKET  => __('Delete tickets'),
            self::MANAGE_BOARDS  => __('Add, edit and delete boards of the space'),
            self::MANAGE_MEMBERS => __('Add and remove members of the space'),
        ];
    }

    public static function isValid(string $permission): bool
    {
        return array_key_exists($permission, static::catalog());
    }
}
