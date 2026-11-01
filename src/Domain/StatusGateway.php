<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\CustomerService\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;
use Gibbon\Module\CustomerService\Service\Workflow;

/**
 * The registry of ticket statuses. Every status belongs to one category (To Do / In Progress / Done) and
 * the category is the same on every board: boards only choose which statuses they show as columns, and
 * may add new statuses of any category.
 */
class StatusGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonCsStatus';
    private static $primaryKey = 'csStatusID';
    private static $searchableColumns = ['name'];

    /** @return array status name => category */
    public function selectMap(): array
    {
        return $this->db()->select("SELECT name, category FROM gibbonCsStatus")->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    /** All statuses ordered To Do, In Progress, Done and by name. */
    public function selectAllOrdered(): array
    {
        return $this->db()->select("SELECT * FROM gibbonCsStatus ORDER BY FIELD(category, 'To Do', 'In Progress', 'Done'), name")->fetchAll();
    }

    /** For a select with option groups: category label => [name => name]. */
    public function selectGrouped(): array
    {
        $labels = Workflow::categoryOptions();
        $groups = [];
        foreach ($this->selectAllOrdered() as $status) {
            $groups[$labels[$status['category']]][$status['name']] = $status['name'];
        }
        return $groups;
    }

    /** Create the status or change its category. */
    public function upsert(string $name, string $category): void
    {
        $name = mb_substr(trim($name), 0, 60);
        if ($name === '' || !in_array($category, Workflow::CATEGORIES, true)) {
            return;
        }
        $this->db()->insert(
            "INSERT INTO gibbonCsStatus (name, category) VALUES (:name, :category) ON DUPLICATE KEY UPDATE category=VALUES(category)",
            ['name' => $name, 'category' => $category]
        );
        Workflow::setStatusMap(null);
    }
}
