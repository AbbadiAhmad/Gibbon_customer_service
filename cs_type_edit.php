<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Domain\DataSet;
use Gibbon\Forms\Form;
use Gibbon\Tables\DataTable;
use Gibbon\Module\administration\Domain\FieldGateway;
use Gibbon\Module\administration\Domain\FormGateway;
use Gibbon\Module\administration\Domain\TeamGateway;
use Gibbon\Module\administration\Domain\TicketTypeGateway;
use Gibbon\Module\administration\Service\CustomFields;
use Gibbon\Services\Format;
use Gibbon\Module\administration\Service\Workflow;

require_once __DIR__ . '/moduleFunctions.php';

csSanitizeRequest($container);

if (!isActionAccessible($guid, $connection2, '/modules/administration/cs_type_edit.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$csTicketTypeID = $_GET['csTicketTypeID'] ?? '';
$gateway = $container->get(TicketTypeGateway::class);
$values = ['name' => '', 'description' => '', 'csTeamID' => '', 'active' => 'Y', 'workflow' => null];

if (!empty($csTicketTypeID)) {
    $values = $gateway->getByID($csTicketTypeID);
    if (empty($values)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }
}

$page->breadcrumbs->add(__('Manage Ticket Types'), 'cs_type_manage.php');
$page->breadcrumbs->add(empty($csTicketTypeID) ? __('Add Ticket Type') : __('Edit Ticket Type'));

$form = Form::create('csType', $session->get('absoluteURL').'/modules/administration/cs_type_process.php');
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('csTicketTypeID', $csTicketTypeID);

$row = $form->addRow();
    $row->addLabel('name', __('Name'));
    $row->addTextField('name')->maxLength(100)->required()->setValue($values['name']);

$row = $form->addRow();
    $row->addLabel('description', __('Description'))->description(__('What kind of ticket this is. Forms that create tickets of this type have their own name and description.'));
    $row->addTextArea('description')->setRows(3)->setValue($values['description']);

$row = $form->addRow();
    $row->addLabel('csTeamID', __('Default Team'))->description(__('New tickets of this type are routed to this team.'));
    $row->addSelect('csTeamID')->fromArray($container->get(TeamGateway::class)->selectOptions())->selected($values['csTeamID'])->placeholder();

$row = $form->addRow();
    $row->addLabel('active', __('Active'))->description(__('Inactive types cannot be chosen for new requests.'));
    $row->addYesNo('active')->required()->selected($values['active']);

$form->addRow()->addHeading('Workflow', __('Workflow'))
    ->append(__('Optional. Without rules a ticket can be moved to any status. Moving a ticket to Resolved or Closed always requires a resolution note.'));

$row = $form->addRow();
    $row->addLabel('workflowTransitions', __('Allowed Moves'))->description(__('One rule per line: "From status: Allowed target 1, Allowed target 2". A status without a line can move anywhere.'));
    $row->addTextArea('workflowTransitions')->setRows(6)->setValue(Workflow::transitionsToText($values['workflow']))
        ->placeholder("New: In Progress, Waiting\nIn Progress: Waiting, Resolved\nWaiting: In Progress");

$row = $form->addRow();
    $row->addLabel('workflowCommentRequired', __('Comment Required For'))->description(__('Comma separated statuses that need a comment when a ticket is moved to them (besides Resolved / Closed).'));
    $row->addTextField('workflowCommentRequired')->maxLength(255)->setValue(Workflow::commentRequiredToText($values['workflow']));

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

echo $form->getOutput();

// Fields of this type
if (!empty($csTicketTypeID)) {
    // The forms are separate: they only point at this type. Show which forms create tickets of it.
    $typeForms = $container->get(FormGateway::class)->selectByType($csTicketTypeID);
    echo '<h3>'.__('Forms for This Ticket Type').'</h3><p>';
    echo empty($typeForms) ? '<i>'.__('No form creates tickets of this type yet.').'</i> ' : implode(' &middot; ', array_map(function ($id, $name) use ($session) {
        return Format::link(csURL($session, 'cs_form_edit.php', ['csFormID' => $id]), htmlspecialchars($name));
    }, array_keys($typeForms), $typeForms)).' &middot; ';
    echo Format::link(csURL($session, 'cs_form_edit.php', ['csTicketTypeID' => $csTicketTypeID]), __('Add a form for this type')).'</p>';
    echo '<div class="message">'.__('The fields below are the extra fields the tickets of this type carry (besides title, description, priority, dates and attachments). They are not a form: a form decides which of them it asks for. Tick Internal for fields only the agents see.').'</div>';

    $fields = $container->get(FieldGateway::class)->selectByType($csTicketTypeID);
    $typeLabels = CustomFields::typeLabels();

    $table = DataTable::create('csFields');
    $table->setTitle(__('Ticket Fields'));
    $table->addMetaData('blankSlate', __('This type has no extra fields yet.'));
    $table->addHeaderAction('add', __('Add'))->setURL('/modules/administration/cs_field_edit.php')
        ->addParam('csTicketTypeID', $csTicketTypeID)->displayLabel();

    $table->addColumn('sequenceNumber', __('Order'));
    $table->addColumn('label', __('Label'));
    $table->addColumn('fieldKey', __('Key'));
    $table->addColumn('fieldType', __('Type'))->format(function ($f) use ($typeLabels) {
        return $typeLabels[$f['fieldType']] ?? $f['fieldType'];
    });
    $table->addColumn('required', __('Required'))->format(function ($f) {
        return $f['required'] == 'Y' ? __('Yes') : __('No');
    });
    $table->addActionColumn()
        ->addParam('csFieldID')
        ->addParam('csTicketTypeID', $csTicketTypeID)
        ->format(function ($field, $actions) {
            $actions->addAction('edit', __('Edit'))->setURL('/modules/administration/cs_field_edit.php');
            $actions->addAction('delete', __('Delete'))->setURL('/modules/administration/cs_delete.php')
                ->addParam('entity', 'field')->addParam('id', $field['csFieldID']);
        });

    echo $table->render(new DataSet($fields));
}
