<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

// Basic variables
$name        = 'ServiceDesk';
$description = 'Requests from parents, students and staff, routed to the right team as tickets with workflows, automation, notifications and saved filters.';
$entryURL    = 'tickets.php';
$type        = 'Additional';
$category    = 'Other';
$version     = '1.4.00';
$author      = 'Abbadi Ahmad';
$url         = 'https://github.com/AbbadiAhmad/Gibbon_help_desk';

// ---------------------------------------------------------------------------------------------------------------------
// Tables (one statement per entry)
// ---------------------------------------------------------------------------------------------------------------------
$moduleTables[] = "CREATE TABLE `gibbonServiceDeskTeam` (
    `gibbonServiceDeskTeamID` int(8) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `name` varchar(60) NOT NULL,
    `description` varchar(255) NOT NULL DEFAULT '',
    `active` enum('Y','N') NOT NULL DEFAULT 'Y',
    `gibbonPersonIDDefaultAssignee` int(10) unsigned zerofill NULL,
    PRIMARY KEY (`gibbonServiceDeskTeamID`),
    UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE `gibbonServiceDeskTeamMember` (
    `gibbonServiceDeskTeamMemberID` int(8) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `gibbonServiceDeskTeamID` int(8) unsigned zerofill NOT NULL,
    `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
    PRIMARY KEY (`gibbonServiceDeskTeamMemberID`),
    UNIQUE KEY `member` (`gibbonServiceDeskTeamID`,`gibbonPersonID`),
    KEY `gibbonPersonID` (`gibbonPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE `gibbonServiceDeskTicketType` (
    `gibbonServiceDeskTicketTypeID` int(8) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `name` varchar(60) NOT NULL,
    `description` varchar(255) NOT NULL DEFAULT '',
    `roleCategories` varchar(60) NOT NULL DEFAULT 'Staff,Student,Parent,Other',
    `color` varchar(7) NOT NULL DEFAULT '',
    `active` enum('Y','N') NOT NULL DEFAULT 'Y',
    `sequenceNumber` int(4) NOT NULL DEFAULT 0,
    PRIMARY KEY (`gibbonServiceDeskTicketTypeID`),
    UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE `gibbonServiceDeskTicketTypeTeam` (
    `gibbonServiceDeskTicketTypeID` int(8) unsigned zerofill NOT NULL,
    `gibbonServiceDeskTeamID` int(8) unsigned zerofill NOT NULL,
    PRIMARY KEY (`gibbonServiceDeskTicketTypeID`,`gibbonServiceDeskTeamID`),
    KEY `gibbonServiceDeskTeamID` (`gibbonServiceDeskTeamID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE `gibbonServiceDeskField` (
    `gibbonServiceDeskFieldID` int(8) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `gibbonServiceDeskTicketTypeID` int(8) unsigned zerofill NOT NULL,
    `name` varchar(40) NOT NULL,
    `label` varchar(90) NOT NULL,
    `description` varchar(255) NOT NULL DEFAULT '',
    `type` enum('Text','Long Text','Date','Select','Yes/No','Students','Classes') NOT NULL DEFAULT 'Text',
    `options` text NULL,
    `required` enum('Y','N') NOT NULL DEFAULT 'N',
    `sequenceNumber` int(4) NOT NULL DEFAULT 0,
    PRIMARY KEY (`gibbonServiceDeskFieldID`),
    UNIQUE KEY `name` (`gibbonServiceDeskTicketTypeID`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

// gibbonServiceDeskTicketTypeID NULL = the default workflow, copied to every new ticket type.
$moduleTables[] = "CREATE TABLE `gibbonServiceDeskStage` (
    `gibbonServiceDeskStageID` int(8) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `gibbonServiceDeskTicketTypeID` int(8) unsigned zerofill NULL,
    `name` varchar(40) NOT NULL,
    `category` enum('Open','In Progress','Closed') NOT NULL DEFAULT 'Open',
    `sequenceNumber` int(4) NOT NULL DEFAULT 0,
    `automation` varchar(60) NULL,
    `nextStage` varchar(40) NULL,
    PRIMARY KEY (`gibbonServiceDeskStageID`),
    KEY `gibbonServiceDeskTicketTypeID` (`gibbonServiceDeskTicketTypeID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

$moduleTables[] = "CREATE TABLE `gibbonServiceDeskTicket` (
    `gibbonServiceDeskTicketID` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `gibbonSchoolYearID` int(3) unsigned zerofill NULL,
    `gibbonServiceDeskTicketTypeID` int(8) unsigned zerofill NOT NULL,
    `gibbonServiceDeskStageID` int(8) unsigned zerofill NOT NULL,
    `gibbonPersonIDRequester` int(10) unsigned zerofill NOT NULL,
    `gibbonPersonIDAssignee` int(10) unsigned zerofill NULL,
    `subject` varchar(120) NOT NULL,
    `fields` text NULL,
    `priority` enum('Low','Normal','High','Urgent') NOT NULL DEFAULT 'Normal',
    `timestampCreated` datetime NOT NULL,
    `timestampModified` datetime NOT NULL,
    `timestampClosed` datetime NULL,
    PRIMARY KEY (`gibbonServiceDeskTicketID`),
    KEY `type` (`gibbonServiceDeskTicketTypeID`),
    KEY `stage` (`gibbonServiceDeskStageID`),
    KEY `requester` (`gibbonPersonIDRequester`),
    KEY `assignee` (`gibbonPersonIDAssignee`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

// gibbonPersonID NULL = written by the system (workflow / automation).
$moduleTables[] = "CREATE TABLE `gibbonServiceDeskTicketComment` (
    `gibbonServiceDeskTicketCommentID` int(12) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `gibbonServiceDeskTicketID` int(10) unsigned zerofill NOT NULL,
    `gibbonPersonID` int(10) unsigned zerofill NULL,
    `type` enum('Comment','Internal Note','Log','Internal Log') NOT NULL DEFAULT 'Comment',
    `comment` text NOT NULL,
    `timestamp` datetime NOT NULL,
    `timestampEdited` datetime NULL,
    PRIMARY KEY (`gibbonServiceDeskTicketCommentID`),
    KEY `ticket` (`gibbonServiceDeskTicketID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

// The notification policy of a person (no row = module default: both channels on).
$moduleTables[] = "CREATE TABLE `gibbonServiceDeskNotificationPreference` (
    `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
    `notifyInApp` enum('Y','N') NOT NULL DEFAULT 'Y',
    `notifyEmail` enum('Y','N') NOT NULL DEFAULT 'Y',
    PRIMARY KEY (`gibbonPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

// The copy of that policy on one ticket: who follows the ticket and how they are told.
$moduleTables[] = "CREATE TABLE `gibbonServiceDeskTicketSubscriber` (
    `gibbonServiceDeskTicketID` int(10) unsigned zerofill NOT NULL,
    `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
    `notifyInApp` enum('Y','N') NOT NULL DEFAULT 'Y',
    `notifyEmail` enum('Y','N') NOT NULL DEFAULT 'Y',
    PRIMARY KEY (`gibbonServiceDeskTicketID`,`gibbonPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

// gibbonPersonID NULL = shared filter made by an administrator. A shared filter has an audience: Agents (administrators
// and team members; gibbonServiceDeskTeamID then limits it to one team) or Requesters (everybody else).
$moduleTables[] = "CREATE TABLE `gibbonServiceDeskFilter` (
    `gibbonServiceDeskFilterID` int(8) unsigned zerofill NOT NULL AUTO_INCREMENT,
    `gibbonPersonID` int(10) unsigned zerofill NULL,
    `gibbonServiceDeskTeamID` int(8) unsigned zerofill NULL,
    `audience` enum('Agents','Requesters') NOT NULL DEFAULT 'Agents',
    `name` varchar(60) NOT NULL,
    `criteria` text NOT NULL,
    `sequenceNumber` int(4) NOT NULL DEFAULT 0,
    PRIMARY KEY (`gibbonServiceDeskFilterID`),
    KEY `gibbonPersonID` (`gibbonPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

// How a person last looked at the ticket list (list or board); the page opens the same way next time.
$moduleTables[] = "CREATE TABLE `gibbonServiceDeskViewPreference` (
    `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
    `ticketView` enum('List','Board') NOT NULL DEFAULT 'List',
    PRIMARY KEY (`gibbonPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

// ---------------------------------------------------------------------------------------------------------------------
// Default data (explicit ids so rows can reference each other; no 4-byte characters)
// ---------------------------------------------------------------------------------------------------------------------
$moduleTables[] = "INSERT INTO `gibbonServiceDeskTeam` (`gibbonServiceDeskTeamID`, `name`, `description`) VALUES
    (1, 'Administration', 'School office: attendance, enrolment, registration and general requests.'),
    (2, 'Management', 'School management: complaints.')";

$moduleTables[] = "INSERT INTO `gibbonServiceDeskTicketType` (`gibbonServiceDeskTicketTypeID`, `name`, `description`, `roleCategories`, `sequenceNumber`, `color`) VALUES
    (1, 'Absence Report', 'Tell the school that your child will be (or was) absent.', 'Parent', 1, '#f59e0b'),
    (2, 'Course Unenrolment', 'Ask to take your child out of one or more classes.', 'Parent', 2, '#3b82f6'),
    (3, 'School De-registration', 'Ask to withdraw your child from the school.', 'Parent', 3, '#8b5cf6'),
    (4, 'Complaint', 'Raise a complaint. The school will reply on the ticket.', 'Staff,Student,Parent,Other', 4, '#ef4444'),
    (5, 'General Request', 'Anything else you need from the school office.', 'Staff,Student,Parent,Other', 5, '#10b981')";

$moduleTables[] = "INSERT INTO `gibbonServiceDeskTicketTypeTeam` (`gibbonServiceDeskTicketTypeID`, `gibbonServiceDeskTeamID`) VALUES
    (1, 1), (2, 1), (3, 1), (4, 2), (5, 1)";

$moduleTables[] = "INSERT INTO `gibbonServiceDeskField` (`gibbonServiceDeskTicketTypeID`, `name`, `label`, `description`, `type`, `options`, `required`, `sequenceNumber`) VALUES
    (1, 'students', 'Children', '', 'Students', NULL, 'Y', 1),
    (1, 'reason', 'Absence Type', '', 'Select', 'Medical,Family,Education,Other', 'Y', 2),
    (1, 'dateStart', 'First Day of Absence', '', 'Date', NULL, 'Y', 3),
    (1, 'dateEnd', 'Last Day of Absence', 'Leave empty for a single day.', 'Date', NULL, 'N', 4),
    (1, 'details', 'Details', '', 'Long Text', NULL, 'N', 5),
    (2, 'classes', 'Classes', 'The classes your child should leave.', 'Classes', NULL, 'Y', 1),
    (2, 'dateEffective', 'Effective From', '', 'Date', NULL, 'N', 2),
    (2, 'details', 'Reason', '', 'Long Text', NULL, 'N', 3),
    (3, 'students', 'Children', '', 'Students', NULL, 'Y', 1),
    (3, 'dateEffective', 'Last Day at School', '', 'Date', NULL, 'Y', 2),
    (3, 'details', 'Details', 'Reason for leaving and anything the office should know.', 'Long Text', NULL, 'Y', 3),
    (4, 'students', 'Children Concerned', 'Only if the complaint is about one of your children.', 'Students', NULL, 'N', 1),
    (4, 'details', 'Complaint Details', '', 'Long Text', NULL, 'Y', 2),
    (5, 'details', 'Details', '', 'Long Text', NULL, 'Y', 1)";

// The default workflow (type NULL) and its copy for every default type. The Absence Report gets an "Approved" stage whose
// automation records the absence in Attendance and then moves the ticket to "Done".
$serviceDeskWorkflow = [['New', 'Open'], ['In Progress', 'In Progress'], ['Done', 'Closed'], ['Rejected', 'Closed']];
foreach ([null, 1, 2, 3, 4, 5] as $serviceDeskTypeID) {
    $rows = [];
    foreach ($serviceDeskWorkflow as $index => $stage) {
        $rows[] = "(".($serviceDeskTypeID ?? 'NULL').", '{$stage[0]}', '{$stage[1]}', ".(($index + 1) * 10).", NULL, NULL)";
    }
    if ($serviceDeskTypeID === 1) {
        $rows[] = "(1, 'Approved', 'In Progress', 25, 'RecordAbsence', 'Done')";
    }
    $moduleTables[] = "INSERT INTO `gibbonServiceDeskStage` (`gibbonServiceDeskTicketTypeID`, `name`, `category`, `sequenceNumber`, `automation`, `nextStage`) VALUES ".implode(', ', $rows);
}

$moduleTables[] = "INSERT INTO `gibbonServiceDeskFilter` (`gibbonPersonID`, `gibbonServiceDeskTeamID`, `audience`, `name`, `criteria`, `sequenceNumber`) VALUES
    (NULL, NULL, 'Agents', 'My Requests', '{\"requester\":\"me\"}', 1),
    (NULL, NULL, 'Agents', 'Open Tickets', '{\"status\":[\"Open\",\"In Progress\"]}', 2),
    (NULL, NULL, 'Agents', 'Assigned to Me', '{\"assignee\":\"me\",\"status\":[\"Open\",\"In Progress\"]}', 3),
    (NULL, NULL, 'Agents', 'Unassigned', '{\"assignee\":\"none\",\"status\":[\"Open\",\"In Progress\"]}', 4),
    (NULL, 1, 'Agents', 'Absence Intake', '{\"type\":[\"1\"],\"status\":[\"Open\"]}', 5),
    (NULL, NULL, 'Requesters', 'Open', '{\"status\":[\"Open\",\"In Progress\"]}', 1),
    (NULL, NULL, 'Requesters', 'Closed', '{\"status\":[\"Closed\"]}', 2)";

// ---------------------------------------------------------------------------------------------------------------------
// Actions
// ---------------------------------------------------------------------------------------------------------------------
$serviceDeskEveryone = [
    'defaultPermissionAdmin' => 'Y', 'defaultPermissionTeacher' => 'Y', 'defaultPermissionStudent' => 'Y', 'defaultPermissionParent' => 'Y', 'defaultPermissionSupport' => 'Y',
    'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'Y', 'categoryPermissionParent' => 'Y', 'categoryPermissionOther' => 'Y',
];
$serviceDeskAdminOnly = [
    'defaultPermissionAdmin' => 'Y', 'defaultPermissionTeacher' => 'N', 'defaultPermissionStudent' => 'N', 'defaultPermissionParent' => 'N', 'defaultPermissionSupport' => 'N',
    'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'N', 'categoryPermissionParent' => 'N', 'categoryPermissionOther' => 'N',
];

$actionRows[] = [
    'name'        => 'New Request',
    'precedence'  => '0',
    'category'    => 'Requests',
    'description' => 'Choose a service and send a request to the school.',
    'URLList'     => 'request_new.php,request_newProcess.php',
    'entryURL'    => 'request_new.php',
    'entrySidebar' => 'Y',
    'menuShow'    => 'Y',
] + $serviceDeskEveryone;

// A second action on the same pages (Gibbon's "grouped action" convention: name_suffix + higher precedence).
// Holders see every active service and can send a request on behalf of another person.
$actionRows[] = [
    'name'        => 'New Request_onBehalf',
    'precedence'  => '1',
    'category'    => 'Requests',
    'description' => 'Create tickets on behalf of others: see every service and send a request for another person, who becomes the requester.',
    'URLList'     => 'request_new.php,request_newProcess.php',
    'entryURL'    => 'request_new.php',
    'entrySidebar' => 'Y',
    'menuShow'    => 'Y',
    'defaultPermissionAdmin' => 'Y', 'defaultPermissionTeacher' => 'N', 'defaultPermissionStudent' => 'N', 'defaultPermissionParent' => 'N', 'defaultPermissionSupport' => 'Y',
    'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'N', 'categoryPermissionParent' => 'N', 'categoryPermissionOther' => 'N',
];

$actionRows[] = [
    'name'        => 'Tickets',
    'precedence'  => '0',
    'category'    => 'Requests',
    'description' => 'See your own requests and, for team members, the tickets of your teams.',
    'URLList'     => 'tickets.php,tickets_filterProcess.php,tickets_boardProcess.php,tickets_view.php,tickets_viewProcess.php',
    'entryURL'    => 'tickets.php',
    'entrySidebar' => 'Y',
    'menuShow'    => 'Y',
] + $serviceDeskEveryone;

// Comment permissions: two families, each with the levels "_my" (own comments) and "_all" (any comment). They have
// no menu entry. They add to the ticket rules, never replace them: the ticket must still be visible to the person.
$serviceDeskCommentActions = [
    'Edit Comments' => [
        'description' => 'Change the text of {scope} on tickets, and delete {scope}.',
        'URLList'     => 'tickets_comment_edit.php,tickets_comment_editProcess.php,tickets_comment_delete.php,tickets_comment_deleteProcess.php',
    ],
    'Change Comment Visibility' => [
        'description' => 'Switch {scope} between visible to the requester and internal. Only on tickets the person works on.',
        'URLList'     => 'tickets_comment_edit.php,tickets_comment_editProcess.php',
    ],
];
foreach ($serviceDeskCommentActions as $serviceDeskActionName => $serviceDeskAction) {
    foreach (['my' => 'own comments', 'all' => 'any comment'] as $serviceDeskLevel => $serviceDeskScope) {
        $actionRows[] = [
            'name'        => $serviceDeskActionName.'_'.$serviceDeskLevel,
            'precedence'  => $serviceDeskLevel == 'all' ? '1' : '0',
            'category'    => 'Requests',
            'description' => str_replace('{scope}', $serviceDeskScope, $serviceDeskAction['description']),
            'URLList'     => $serviceDeskAction['URLList'],
            'entryURL'    => 'tickets_comment_edit.php',
            'entrySidebar' => 'Y',
            'menuShow'    => 'N',
            // Default: administrators any comment; teachers and support staff their own.
            'defaultPermissionAdmin'   => $serviceDeskLevel == 'all' ? 'Y' : 'N',
            'defaultPermissionTeacher' => $serviceDeskLevel == 'my' ? 'Y' : 'N',
            'defaultPermissionStudent' => 'N',
            'defaultPermissionParent'  => 'N',
            'defaultPermissionSupport' => $serviceDeskLevel == 'my' ? 'Y' : 'N',
            'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'Y', 'categoryPermissionParent' => 'Y', 'categoryPermissionOther' => 'Y',
        ];
    }
}

$actionRows[] = [
    'name'        => 'Notification Settings',
    'precedence'  => '0',
    'category'    => 'Requests',
    'description' => 'Choose how you are told about updates on your tickets.',
    'URLList'     => 'notifications.php,notificationsProcess.php',
    'entryURL'    => 'notifications.php',
    'entrySidebar' => 'Y',
    'menuShow'    => 'Y',
] + $serviceDeskEveryone;

$actionRows[] = [
    'name'        => 'Manage Ticket Types',
    'precedence'  => '0',
    'category'    => 'Configure',
    'description' => 'Define ticket types, their request form, teams and workflow. Holders are Service Desk administrators and see every ticket.',
    'URLList'     => 'types_manage.php,types_manage_edit.php,types_manage_editProcess.php,types_manage_delete.php,types_manage_deleteProcess.php,types_field_edit.php,types_field_editProcess.php,types_field_delete.php,types_field_deleteProcess.php',
    'entryURL'    => 'types_manage.php',
    'entrySidebar' => 'Y',
    'menuShow'    => 'Y',
] + $serviceDeskAdminOnly;

$actionRows[] = [
    'name'        => 'Manage Workflows',
    'precedence'  => '0',
    'category'    => 'Configure',
    'description' => 'Edit the default workflow copied to new ticket types, and the workflow of each ticket type.',
    'URLList'     => 'workflow_manage.php,workflow_stage_edit.php,workflow_stage_editProcess.php,workflow_stage_delete.php,workflow_stage_deleteProcess.php',
    'entryURL'    => 'workflow_manage.php',
    'entrySidebar' => 'Y',
    'menuShow'    => 'Y',
] + $serviceDeskAdminOnly;

$actionRows[] = [
    'name'        => 'Manage Teams',
    'precedence'  => '0',
    'category'    => 'Configure',
    'description' => 'Create teams and choose their members.',
    'URLList'     => 'teams_manage.php,teams_manage_edit.php,teams_manage_editProcess.php,teams_manage_delete.php,teams_manage_deleteProcess.php,teams_memberProcess.php',
    'entryURL'    => 'teams_manage.php',
    'entrySidebar' => 'Y',
    'menuShow'    => 'Y',
] + $serviceDeskAdminOnly;
