<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Domain\DataSet;
use Gibbon\Forms\Form;
use Gibbon\Tables\DataTable;
use Gibbon\Module\CustomerService\Domain\FieldGateway;
use Gibbon\Module\CustomerService\Domain\TeamGateway;
use Gibbon\Module\CustomerService\Domain\TicketTypeGateway;
use Gibbon\Module\CustomerService\Service\CustomFields;
use Gibbon\Module\CustomerService\Service\FormConfig;
use Gibbon\Services\Format;
use Gibbon\Module\CustomerService\Service\Workflow;

require_once __DIR__ . '/moduleFunctions.php';

csSanitizeRequest($container);

if (!isActionAccessible($guid, $connection2, '/modules/CustomerService/cs_type_edit.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$csTicketTypeID = $_GET['csTicketTypeID'] ?? '';
$gateway = $container->get(TicketTypeGateway::class);
$values = ['name' => '', 'description' => '', 'csTeamID' => '', 'active' => 'Y', 'workflow' => null, 'groupName' => '', 'icon' => '', 'audience' => ''];

if (!empty($csTicketTypeID)) {
    $values = $gateway->getByID($csTicketTypeID);
    if (empty($values)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }
}

$page->breadcrumbs->add(__('Manage Ticket Types'), 'cs_type_manage.php');
$page->breadcrumbs->add(empty($csTicketTypeID) ? __('Add Ticket Type') : __('Edit Ticket Type'));

$form = Form::create('csType', $session->get('absoluteURL').'/modules/CustomerService/cs_type_process.php');
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('csTicketTypeID', $csTicketTypeID);

$row = $form->addRow();
    $row->addLabel('name', __('Name'));
    $row->addTextField('name')->maxLength(100)->required()->setValue($values['name']);

$row = $form->addRow();
    $row->addLabel('description', __('Description'))->description(__('Shown to the requester when choosing the request type.'));
    $row->addTextArea('description')->setRows(3)->setValue($values['description']);

$form->addRow()->addHeading('Portal', __('Customer Portal'));
$row = $form->addRow();
    $row->addLabel('groupName', __('Group'))->description(__('Request types with the same group are listed together on the portal, e.g. IT, Facilities, Administration.'));
    $row->addTextField('groupName')->maxLength(60)->setValue($values['groupName']);

$row = $form->addRow();
    $row->addLabel('icon', __('Icon'))->description(__('One symbol shown on the portal card, for example a check mark or an envelope. Use a plain symbol: emoji with 4 bytes do not save on databases with the utf8 character set.'));
    $row->addTextField('icon')->maxLength(8)->setValue($values['icon']);

$row = $form->addRow();
    $row->addLabel('audience', __('Who Can Raise It'))->description(__('Limit the request type to some kinds of users. Leave all unticked for everybody.'));
    $row->addCheckbox('audience')->fromArray(FormConfig::audienceOptions())->checked(array_filter(explode(',', (string)$values['audience'])));

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
    echo '<p>'.Format::link(csURL($session, 'cs_form_edit.php', ['csTicketTypeID' => $csTicketTypeID]), __('Configure the request form'))
        .' &middot; '.Format::link(csURL($session, 'cs_request_new.php', ['csTicketTypeID' => $csTicketTypeID]), __('Open the form as a requester sees it')).'</p>';
    echo '<div class="message">'.__('Every ticket type has these basic fields by default: Title, Description, Priority, Start Date, Due Date and Attachments. The fields below are extra fields for this type.').'</div>';

    $fields = $container->get(FieldGateway::class)->selectByType($csTicketTypeID);
    $typeLabels = CustomFields::typeLabels();

    $table = DataTable::create('csFields');
    $table->setTitle(__('Form Fields'));
    $table->addMetaData('blankSlate', __('This type has no extra fields yet. Requesters only fill in title, description and priority.'));
    $table->addHeaderAction('add', __('Add'))->setURL('/modules/CustomerService/cs_field_edit.php')
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
            $actions->addAction('edit', __('Edit'))->setURL('/modules/CustomerService/cs_field_edit.php');
            $actions->addAction('delete', __('Delete'))->setURL('/modules/CustomerService/cs_delete.php')
                ->addParam('entity', 'field')->addParam('id', $field['csFieldID']);
        });

    echo $table->render(new DataSet($fields));
}
