<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\CustomerService\Automation;

/** Convenience base class: derives key() and label() from the class name and gives a generic describe(). */
abstract class BaseAction implements AutomationAction
{
    public function key(): string
    {
        $short = (new \ReflectionClass($this))->getShortName();
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $short));
    }

    public function label(): string
    {
        return __(ucfirst(str_replace('_', ' ', $this->key())));
    }

    public function params($container): array
    {
        return [];
    }

    public function describe(array $params, $container): string
    {
        $values = [];
        foreach ($this->params($container) as $name => $definition) {
            $value = trim((string)($params[$name] ?? ''));
            if ($value === '') {
                continue;
            }
            switch ($definition['type']) {
                case 'select':
                    $value = $definition['options'][$value] ?? $value;
                    break;
                case 'team':
                    $value = $container->get(\Gibbon\Module\CustomerService\Domain\TeamGateway::class)->selectOptions()[(int)$value] ?? $value;
                    break;
                case 'person':
                    $value = $this->personName($container, $value);
                    break;
                default:
                    $value = mb_strlen($value) > 60 ? mb_substr($value, 0, 60).'...' : $value;
            }
            $values[] = $value;
        }

        return $this->label().(empty($values) ? '' : ': '.implode(', ', $values));
    }

    protected function personName($container, $gibbonPersonID): string
    {
        $row = $container->get(\Gibbon\Domain\User\UserGateway::class)->getByID($gibbonPersonID, ['preferredName', 'surname']);
        return empty($row) ? (string)$gibbonPersonID : trim($row['preferredName'].' '.$row['surname']);
    }
}
