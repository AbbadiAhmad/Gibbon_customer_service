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

/**
 * Finds the automation scripts: every class in src/Automation/Scripts that implements AutomationScript.
 * A stage stores the class name (without namespace) of its script.
 */
class AutomationRegistry
{
    protected $scripts;

    /**
     * @return AutomationScript[] keyed by class name
     */
    public function all(): array
    {
        if ($this->scripts === null) {
            $this->scripts = [];
            foreach (glob(__DIR__.'/Scripts/*.php') ?: [] as $file) {
                $key = basename($file, '.php');
                $class = __NAMESPACE__.'\\Scripts\\'.$key;
                if (!class_exists($class)) {
                    require_once $file;
                }
                if (class_exists($class) && is_subclass_of($class, AutomationScript::class)) {
                    // Created through Gibbon's container, so a script can ask for services in its constructor.
                    global $container;
                    $this->scripts[$key] = !empty($container) ? $container->get($class) : new $class();
                }
            }
            ksort($this->scripts);
        }

        return $this->scripts;
    }

    public function get($key): ?AutomationScript
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * key => name, for the stage form.
     */
    public function options(): array
    {
        return array_map(function (AutomationScript $script) {
            return $script->getName();
        }, $this->all());
    }
}
