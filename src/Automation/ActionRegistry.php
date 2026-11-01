<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\administration\Automation;

/**
 * Finds the actions: every class in src/Automation/Actions/ that implements AutomationAction.
 * Dropping a new class in that folder is all it takes to make a new action available.
 */
class ActionRegistry
{
    private static $actions;

    /** @return AutomationAction[] key => action, sorted by label */
    public static function all(): array
    {
        if (self::$actions === null) {
            self::$actions = [];
            foreach (glob(__DIR__.'/Actions/*.php') ?: [] as $file) {
                $class = __NAMESPACE__.'\\Actions\\'.basename($file, '.php');
                if (!class_exists($class)) {
                    continue;
                }
                $reflection = new \ReflectionClass($class);
                if ($reflection->isAbstract() || !$reflection->implementsInterface(AutomationAction::class)) {
                    continue;
                }
                $action = $reflection->newInstance();
                self::$actions[$action->key()] = $action;
            }
            uasort(self::$actions, function ($a, $b) {
                return strcmp($a->label(), $b->label());
            });
        }

        return self::$actions;
    }

    public static function get(string $key): ?AutomationAction
    {
        return self::all()[$key] ?? null;
    }

    /** key => label, for the rule editor. */
    public static function options(): array
    {
        return array_map(function ($action) {
            return $action->label();
        }, self::all());
    }
}
