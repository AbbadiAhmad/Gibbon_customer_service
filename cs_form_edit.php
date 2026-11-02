<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Domain\DataSet;
use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Module\administration\Domain\FieldGateway;
use Gibbon\Module\administration\Domain\FormFieldGateway;
use Gibbon\Module\administration\Domain\FormGateway;
use Gibbon\Module\administration\Domain\TicketTypeGateway;
use Gibbon\Module\administration\Service\FormAccess;
use Gibbon\Module\administration\Service\FormMapper;

require_once __DIR__ . '/moduleFunctions.php';

csSanitizeRequest($container);

if (!csAccess($guid, $connection2, 'cs_form_edit.php')) {
    $page->addError(__('You do not have access to this action.'));
    return;
}

$csFormID = $_GET['csFormID'] ?? '';
$forms = $container->get(FormGateway::class);
$types = $container->get(TicketTypeGateway::class);
$values = ['name' => '', 'csTicketTypeID' => '', 'slug' => '', 'description' => '', 'groupName' => '', 'icon' => '', 'audience' => '', 'roles' => '',
    'titleTemplate' => '', 'confirmationText' => '', 'sequenceNumber' => 10, 'active' => 'Y'];

if (!empty($csFormID)) {
    $values = $forms->getByID($csFormID);
    if (empty($values)) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }
}

$page->breadcrumbs->add(__('Manage Forms'), 'cs_form_manage.php');
$page->breadcrumbs->add(empty($csFormID) ? __('Add Form') : $values['name']);
echo csFlashOutput($session);

// ---- The form itself: name, ticket type, visibility ------------------------------------------------
$form = Form::create('csForm', $session->get('absoluteURL').'/modules/administration/cs_form_process.php');
$form->addHiddenValue('address', $session->get('address'));
$form->addHiddenValue('action', 'save');
$form->addHiddenValue('csFormID', $csFormID);

$row = $form->addRow();
    $row->addLabel('name', __('Name'))->description(__('What people see on the portal.'));
    $row->addTextField('name')->maxLength(100)->required()->setValue($values['name']);

$row = $form->addRow();
    $row->addLabel('description', __('Description'))->description(__('Shown on the portal card and above the form.'));
    $row->addTextArea('description')->setRows(2)->setValue($values['description']);

$row = $form->addRow();
    $row->addLabel('csTicketTypeID', __('Creates Tickets of Type'))->description(empty($csFormID)
        ? __('The ticket type decides the team, the workflow and which extra fields a ticket has. The questions of this form fill in those fields.')
        : __('Fixed once the form exists: the questions are mapped to the fields of this ticket type.'));
if (empty($csFormID)) {
    $row->addSelect('csTicketTypeID')->fromArray($types->selectOptions())->required()->placeholder()->selected($_GET['csTicketTypeID'] ?? '');

    $row = $form->addRow();
        $row->addLabel('startWith', __('Start With'));
        $row->addSelect('startWith')->fromArray([
            'all'   => __('A question for every field of the ticket (recommended: delete or change what you do not need)'),
            'empty' => __('An empty form'),
        ])->selected('all');
} else {
    $row->addTextField('typeName')->setValue($types->getByID($values['csTicketTypeID'])['name'] ?? '')->readonly();
}

$form->addRow()->addHeading('Visibility', __('Who Can Use This Form'))
    ->append(__('This is a property of the form, independent of the ticket type. Leave everything empty for every registered user (a Gibbon login is always needed). Otherwise a user may use the form when their role category is ticked OR their role is selected.'));

$row = $form->addRow();
    $row->addLabel('audience', __('Role Categories'));
    $row->addCheckbox('audience')->fromArray(FormAccess::audienceOptions())->checked(FormAccess::parseList($values['audience']));

$row = $form->addRow();
    $row->addLabel('roles', __('Specific Roles'))->description(__('Hold Ctrl/Cmd to select several.'));
    $row->addSelect('roles')->fromArray($forms->selectRoleOptions())->selectMultiple()->selected(FormAccess::parseList($values['roles']));

$row = $form->addRow();
    $row->addLabel('active', __('Active'))->description(__('An inactive form is hidden from the portal and cannot be submitted.'));
    $row->addYesNo('active')->required()->selected($values['active']);

$form->addRow()->addHeading('Portal', __('On the Portal'));
$row = $form->addRow();
    $row->addLabel('groupName', __('Group'))->description(__('Forms with the same group are listed together, e.g. Families, Staff.'));
    $row->addTextField('groupName')->maxLength(60)->setValue($values['groupName']);

$row = $form->addRow();
    $row->addLabel('icon', __('Icon'))->description(__('One plain symbol, for example a check mark or an envelope (4-byte emoji do not save on databases with the utf8 character set).'));
    $row->addTextField('icon')->maxLength(8)->setValue($values['icon']);

$row = $form->addRow();
    $row->addLabel('sequenceNumber', __('Order'))->description(__('Within its group; lower first.'));
    $row->addNumber('sequenceNumber')->minimum(0)->maximum(9999)->required()->setValue($values['sequenceNumber']);

$form->addRow()->addHeading('Ticket', __('The Ticket It Creates'));
$row = $form->addRow();
    $row->addLabel('titleTemplate', __('Title Template'))->description(__('Used when no question is mapped to the title. Write the key of a question in braces, e.g. "Absence {child_name} from {absent_from}". Without a template the name of the form is the title.'));
    $row->addTextField('titleTemplate')->maxLength(200)->setValue($values['titleTemplate']);

$row = $form->addRow();
    $row->addLabel('confirmationText', __('Confirmation'))->description(__('Shown to the requester after sending.'));
    $row->addTextArea('confirmationText')->setRows(2)->setValue($values['confirmationText']);

$row = $form->addRow();
    $row->addLabel('slug', __('Link Name'))->description(__('The readable part of the link you can share (letters, numbers, dashes). Leave empty to make it from the name.'));
    $row->addTextField('slug')->maxLength(80)->setValue($values['slug']);

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

echo $form->getOutput();

if (empty($csFormID)) {
    return; // the questions come once the form exists
}

// ---- Share and preview -------------------------------------------------------------------------
$shareUrl = $session->get('absoluteURL').'/index.php?q=/modules/administration/cs_request_new.php&form='.rawurlencode($values['slug']);
echo '<h3>'.__('Share This Form').'</h3>';
echo '<p>'.__('Anyone who is allowed to use the form can open this link (they log in first if they are not logged in):').'</p>';
echo '<p><input type="text" readonly class="w-full" value="'.htmlspecialchars($shareUrl).'" onclick="this.select()"/></p>';
echo '<p>'.Format::link(csURL($session, 'cs_request_new.php', ['csFormID' => $csFormID]), __('Open the form as a requester sees it')).'</p>';

// ---- Questions ---------------------------------------------------------------------------------
$typeFields = $container->get(FieldGateway::class)->selectByType($values['csTicketTypeID']);
$questions = $container->get(FormFieldGateway::class)->selectByForm($csFormID);
$inputTypes = FormMapper::inputTypes();

$table = DataTable::create('csQuestions');
$table->setTitle(__('Questions'));
$table->addMetaData('blankSlate', __('This form has no questions yet.'));
$table->addHeaderAction('add', __('Add'))->setURL('/modules/administration/cs_formfield_edit.php')->addParam('csFormID', $csFormID)->displayLabel();
$table->addColumn('sequenceNumber', __('Order'));
$table->addColumn('label', __('Question'))->format(function ($q) {
    return htmlspecialchars($q['label']).($q['required'] == 'Y' ? ' *' : '').' <span class="text-gray-500 text-xs">{'.htmlspecialchars($q['fieldKey']).'}</span>';
});
$table->addColumn('inputType', __('Input'))->format(function ($q) use ($inputTypes) {
    return htmlspecialchars($inputTypes[$q['inputType']] ?? $q['inputType']);
});
$table->addColumn('target', __('Fills in'))->format(function ($q) use ($typeFields) {
    $label = FormMapper::targetLabel($q['target'], $typeFields);
    return $label === '' ? '<i>'.__('nothing (information)').'</i>' : htmlspecialchars($label);
});
$table->addActionColumn()
    ->addParam('csFormFieldID')
    ->addParam('csFormID', $csFormID)
    ->format(function ($q, $actions) {
        $actions->addAction('edit', __('Edit'))->setURL('/modules/administration/cs_formfield_edit.php');
        $actions->addAction('delete', __('Delete'))->setURL('/modules/administration/cs_delete.php')->addParam('entity', 'formfield')->addParam('id', $q['csFormFieldID']);
    });
echo $table->render(new DataSet($questions));

// What the form covers: a form may cover all the fields of the ticket or only some of them.
$mapped = array_column($questions, 'target');
$coreLabels = ['title' => __('Title'), 'description' => __('Description'), 'priority' => __('Priority'), 'dateStart' => __('Start date'), 'dateDue' => __('Due date'), 'attachments' => __('Attachments')];
$notCovered = [];
foreach ($coreLabels as $target => $label) {
    if (!in_array($target, $mapped, true)) {
        $notCovered[] = $label;
    }
}
$missingCustom = FormMapper::suggest($typeFields, $questions, false);
foreach ($missingCustom as $suggestion) {
    $notCovered[] = $suggestion['label'];
}
echo '<p class="text-gray-600">'.(empty($notCovered) ? __('This form covers every field of the ticket.') : sprintf(__('Not covered by this form (stay empty on new tickets): %1$s.'), htmlspecialchars(implode(', ', $notCovered)))).'</p>';
if (!in_array('title', $mapped, true)) {
    echo '<div class="warning">'.($values['titleTemplate'] ? sprintf(__('No question fills in the title: the template "%1$s" is used.'), htmlspecialchars($values['titleTemplate'])) : __('No question fills in the title and there is no title template: every ticket gets the name of the form as its title.')).'</div>';
}

// Bring the form up to date with its ticket type (a field was added to the type after the form was made).
if (!empty($missingCustom)) {
    $sync = Form::create('csFormSync', $session->get('absoluteURL').'/modules/administration/cs_form_process.php');
    $sync->addHiddenValue('address', $session->get('address'));
    $sync->addHiddenValue('action', 'sync');
    $sync->addHiddenValue('csFormID', $csFormID);
    $sync->setTitle(__('Update From the Ticket Type'));
    $row = $sync->addRow();
        $row->addContent(sprintf(__('Add a question for each ticket field the form does not cover yet: %1$s.'), htmlspecialchars(implode(', ', array_column($missingCustom, 'label')))));
        $row->addSubmit(__('Add These Questions'));
    echo $sync->getOutput();
}
