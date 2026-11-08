<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\administration\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

/**
 * The people the module lets you pick (team members, space members, watchers, rule targets).
 *
 * One plain query and plain Gibbon selects. Gibbon's own `addSelectUsers()` helper is NOT used: it runs its own heavy queries for every call
 * (a rule with several action slots called it 15 times) and, in a live Gibbon, it came back as an error placeholder instead of a select.
 */
class PersonGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonPerson';
    private static $primaryKey = 'gibbonPersonID';
    private static $searchableColumns = ['surname', 'preferredName', 'username'];

    /** @return array gibbonPersonID => "Surname, Preferred name (username)" for the active (Full) users */
    public function selectOptions(): array
    {
        $options = [];
        foreach ($this->db()->select("SELECT gibbonPersonID, surname, preferredName, username FROM gibbonPerson WHERE status='Full' ORDER BY surname, preferredName")->fetchAll() as $p) {
            $options[$p['gibbonPersonID']] = $p['surname'].', '.$p['preferredName'].' ('.$p['username'].')';
        }

        return $options;
    }
}
