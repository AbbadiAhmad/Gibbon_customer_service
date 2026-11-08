<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

namespace Gibbon\Module\administration\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryableGateway;

/** The questions of a form. `target` says which field of the ticket the answer goes to (see Service/FormMapper). */
class FormFieldGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonCsFormField';
    private static $primaryKey = 'csFormFieldID';
    private static $searchableColumns = ['label', 'fieldKey'];

    public function selectByForm($csFormID): array
    {
        return $this->db()->select("SELECT * FROM gibbonCsFormField WHERE csFormID=:id ORDER BY sequenceNumber, csFormFieldID", ['id' => $csFormID])->fetchAll();
    }

    public function keyExists($csFormID, string $fieldKey, $exceptID = 0): bool
    {
        $sql = "SELECT COUNT(*) FROM gibbonCsFormField WHERE csFormID=:form AND fieldKey=:key AND csFormFieldID<>:id";
        return (int)$this->db()->selectOne($sql, ['form' => $csFormID, 'key' => $fieldKey, 'id' => (int)$exceptID]) > 0;
    }

    /** Next order number: the questions are numbered 10, 20, 30... so one can be slipped in between. */
    public function nextSequence($csFormID): int
    {
        return 10 * (1 + intdiv((int)$this->db()->selectOne("SELECT COALESCE(MAX(sequenceNumber),0) FROM gibbonCsFormField WHERE csFormID=:id", ['id' => $csFormID]), 10));
    }

    /** Append questions (as returned by FormMapper::suggest) to a form. */
    public function addQuestions($csFormID, array $questions): int
    {
        $count = 0;
        foreach ($questions as $q) {
            $this->insert([
                'csFormID' => $csFormID, 'fieldKey' => $q['fieldKey'], 'label' => $q['label'], 'helpText' => $q['helpText'] ?: null,
                'inputType' => $q['inputType'], 'options' => $q['options'] ?: null, 'required' => $q['required'], 'target' => $q['target'],
                'sequenceNumber' => $this->nextSequence($csFormID),
            ]);
            $count++;
        }
        return $count;
    }
}
