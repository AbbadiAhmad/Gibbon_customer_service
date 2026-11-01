<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\administration\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

/** A user's saved ticket search ("my filters"). `params` is the JSON of the search form values. */
class SavedFilterGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonCsSavedFilter';
    private static $primaryKey = 'csSavedFilterID';
    private static $searchableColumns = ['name'];

    public function selectByPerson($gibbonPersonID): array
    {
        return $this->db()->select("SELECT * FROM gibbonCsSavedFilter WHERE gibbonPersonID=:person ORDER BY name", ['person' => $gibbonPersonID])->fetchAll();
    }

    public function getOwned($csSavedFilterID, $gibbonPersonID): array
    {
        return $this->db()->select("SELECT * FROM gibbonCsSavedFilter WHERE csSavedFilterID=:id AND gibbonPersonID=:person", ['id' => $csSavedFilterID, 'person' => $gibbonPersonID])->fetch() ?: [];
    }
}
