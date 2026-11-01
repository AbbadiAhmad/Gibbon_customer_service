<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community
Copyright © 2010, Gibbon Foundation
*/

// Basic variables
$name = 'CustomerService';      // Module name = folder name = namespace Gibbon\Module\CustomerService
$description = 'Service desk for customer requests: forms, tickets, teams, spaces and boards';
$entryURL = 'cs_portal.php';
$type = 'Additional';
$category = 'Other';
$version = '0.5.0';
$author = 'Ahmad';
$url = '';

// ---------------------------------------------------------------------------
// Tables
// ---------------------------------------------------------------------------

$moduleTables[] = "
    CREATE TABLE `gibbonCsTeam` (
        `csTeamID` int(6) unsigned NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        `description` text NULL,
        `active` enum('Y','N') NOT NULL DEFAULT 'Y',
        PRIMARY KEY (`csTeamID`),
        UNIQUE KEY `name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

$moduleTables[] = "
    CREATE TABLE `gibbonCsTeamMember` (
        `csTeamMemberID` int(8) unsigned NOT NULL AUTO_INCREMENT,
        `csTeamID` int(6) unsigned NOT NULL,
        `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
        PRIMARY KEY (`csTeamMemberID`),
        UNIQUE KEY `team_person` (`csTeamID`, `gibbonPersonID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

$moduleTables[] = "
    CREATE TABLE `gibbonCsTicketType` (
        `csTicketTypeID` int(6) unsigned NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        `description` text NULL,
        `csTeamID` int(6) unsigned NULL COMMENT 'Default team new tickets of this type are routed to',
        `workflow` text NULL COMMENT 'JSON: {transitions: {from: [to, ...]}, commentRequired: [status, ...]}',
        `groupName` varchar(60) NULL COMMENT 'Heading the type is listed under on the customer portal',
        `icon` varchar(16) NULL COMMENT 'Emoji shown on the portal card',
        `audience` varchar(100) NULL COMMENT 'Comma separated Gibbon role categories allowed to raise it (Staff, Student, Parent, Other). Empty = everybody',
        `formConfig` text NULL COMMENT 'JSON: settings of the basic request form fields (show, required, label, help, order)',
        `active` enum('Y','N') NOT NULL DEFAULT 'Y',
        PRIMARY KEY (`csTicketTypeID`),
        UNIQUE KEY `name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

$moduleTables[] = "
    CREATE TABLE `gibbonCsField` (
        `csFieldID` int(8) unsigned NOT NULL AUTO_INCREMENT,
        `csTicketTypeID` int(6) unsigned NOT NULL,
        `fieldKey` varchar(50) NOT NULL COMMENT 'Machine name (a-z, 0-9, _). Same key on different types = same field for board filters',
        `label` varchar(100) NOT NULL,
        `fieldType` enum('varchar','text','number','date','select','checkboxes','radio','yesno') NOT NULL DEFAULT 'varchar',
        `options` text NULL COMMENT 'Max length for varchar, comma separated list for select/checkboxes/radio',
        `required` enum('N','Y') NOT NULL DEFAULT 'N',
        `showOnForm` enum('Y','N') NOT NULL DEFAULT 'Y' COMMENT 'N = only the agents fill it in, the portal form does not show it',
        `description` varchar(255) NULL,
        `sequenceNumber` int(4) NOT NULL DEFAULT 0,
        PRIMARY KEY (`csFieldID`),
        UNIQUE KEY `type_key` (`csTicketTypeID`, `fieldKey`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

$moduleTables[] = "
    CREATE TABLE `gibbonCsRole` (
        `csRoleID` int(6) unsigned NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        `description` text NULL,
        PRIMARY KEY (`csRoleID`),
        UNIQUE KEY `name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

$moduleTables[] = "
    CREATE TABLE `gibbonCsRolePermission` (
        `csRolePermissionID` int(8) unsigned NOT NULL AUTO_INCREMENT,
        `csRoleID` int(6) unsigned NOT NULL,
        `permission` varchar(50) NOT NULL,
        PRIMARY KEY (`csRolePermissionID`),
        UNIQUE KEY `role_permission` (`csRoleID`, `permission`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

$moduleTables[] = "
    CREATE TABLE `gibbonCsSpace` (
        `csSpaceID` int(6) unsigned NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        `description` text NULL,
        `active` enum('Y','N') NOT NULL DEFAULT 'Y',
        PRIMARY KEY (`csSpaceID`),
        UNIQUE KEY `name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

$moduleTables[] = "
    CREATE TABLE `gibbonCsSpaceMember` (
        `csSpaceMemberID` int(8) unsigned NOT NULL AUTO_INCREMENT,
        `csSpaceID` int(6) unsigned NOT NULL,
        `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
        `csRoleID` int(6) unsigned NOT NULL,
        PRIMARY KEY (`csSpaceMemberID`),
        UNIQUE KEY `space_person_role` (`csSpaceID`, `gibbonPersonID`, `csRoleID`),
        KEY `gibbonPersonID` (`gibbonPersonID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

$moduleTables[] = "
    CREATE TABLE `gibbonCsBoard` (
        `csBoardID` int(6) unsigned NOT NULL AUTO_INCREMENT,
        `csSpaceID` int(6) unsigned NOT NULL,
        `name` varchar(100) NOT NULL,
        `description` text NULL,
        `filter` text NULL COMMENT 'JSON list of conditions: [{field, op, value}]. Empty = all tickets',
        `active` enum('Y','N') NOT NULL DEFAULT 'Y',
        PRIMARY KEY (`csBoardID`),
        KEY `csSpaceID` (`csSpaceID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

$moduleTables[] = "
    CREATE TABLE `gibbonCsBoardColumn` (
        `csBoardColumnID` int(8) unsigned NOT NULL AUTO_INCREMENT,
        `csBoardID` int(6) unsigned NOT NULL,
        `name` varchar(60) NOT NULL COMMENT 'Also the ticket status a ticket gets when moved into this column',
        `sequenceNumber` int(4) NOT NULL DEFAULT 0,
        PRIMARY KEY (`csBoardColumnID`),
        UNIQUE KEY `board_name` (`csBoardID`, `name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

$moduleTables[] = "
    CREATE TABLE `gibbonCsTicket` (
        `csTicketID` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `csTicketTypeID` int(6) unsigned NOT NULL,
        `title` varchar(200) NOT NULL,
        `description` text NULL,
        `status` varchar(60) NOT NULL DEFAULT 'New',
        `priority` enum('Low','Medium','High','Urgent') NOT NULL DEFAULT 'Medium',
        `gibbonPersonIDReporter` int(10) unsigned zerofill NOT NULL,
        `gibbonPersonIDAssignee` int(10) unsigned zerofill NULL,
        `csTeamID` int(6) unsigned NULL,
        `customData` longtext NULL COMMENT 'JSON object of custom field values keyed by fieldKey',
        `resolution` text NULL COMMENT 'Resolution note given when the ticket was moved to Resolved / Closed',
        `timestampResolved` timestamp NULL DEFAULT NULL,
        `csTicketIDParent` int(10) unsigned NULL COMMENT 'Set on subtasks: the ticket this one is a part of',
        `dateStart` date NULL,
        `dateDue` date NULL,
        `dueReminderSent` datetime NULL COMMENT 'When the due soon reminder was sent (reset when the due date changes)',
        `overdueNoticeSent` datetime NULL COMMENT 'When the overdue notice was sent (reset when the due date changes)',
        `timestampCreated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `timestampUpdated` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`csTicketID`),
        KEY `csTicketTypeID` (`csTicketTypeID`),
        KEY `status` (`status`),
        KEY `reporter` (`gibbonPersonIDReporter`),
        KEY `assignee` (`gibbonPersonIDAssignee`),
        KEY `csTeamID` (`csTeamID`),
        KEY `csTicketIDParent` (`csTicketIDParent`),
        KEY `dateDue` (`dateDue`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

$moduleTables[] = "
    CREATE TABLE `gibbonCsTicketActivity` (
        `csTicketActivityID` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `csTicketID` int(10) unsigned NOT NULL,
        `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
        `kind` enum('Comment','Event') NOT NULL DEFAULT 'Comment',
        `visibility` enum('Public','Internal') NOT NULL DEFAULT 'Public' COMMENT 'Internal = only visible to the people working on the ticket',
        `body` text NOT NULL,
        `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`csTicketActivityID`),
        KEY `csTicketID` (`csTicketID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

$moduleTables[] = "
    CREATE TABLE `gibbonCsTicketAttachment` (
        `csTicketAttachmentID` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `csTicketID` int(10) unsigned NOT NULL,
        `csTicketActivityID` int(10) unsigned NULL COMMENT 'The comment it was attached to; NULL = attached to the request itself',
        `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
        `fileName` varchar(255) NOT NULL,
        `filePath` varchar(255) NOT NULL COMMENT 'Relative to the Gibbon root, below uploads/',
        `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`csTicketAttachmentID`),
        KEY `csTicketID` (`csTicketID`),
        KEY `csTicketActivityID` (`csTicketActivityID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

$moduleTables[] = "
    CREATE TABLE `gibbonCsSavedFilter` (
        `csSavedFilterID` int(8) unsigned NOT NULL AUTO_INCREMENT,
        `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
        `name` varchar(100) NOT NULL,
        `params` text NOT NULL COMMENT 'JSON of the ticket search form values',
        PRIMARY KEY (`csSavedFilterID`),
        KEY `gibbonPersonID` (`gibbonPersonID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

$moduleTables[] = "
    CREATE TABLE `gibbonCsTag` (
      `csTagID` int(8) unsigned NOT NULL AUTO_INCREMENT,
      `name` varchar(50) NOT NULL,
      PRIMARY KEY (`csTagID`),
      UNIQUE KEY `name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

$moduleTables[] = "
    CREATE TABLE `gibbonCsTicketTag` (
      `csTicketTagID` int(10) unsigned NOT NULL AUTO_INCREMENT,
      `csTicketID` int(10) unsigned NOT NULL,
      `csTagID` int(8) unsigned NOT NULL,
      PRIMARY KEY (`csTicketTagID`),
      UNIQUE KEY `ticket_tag` (`csTicketID`, `csTagID`),
      KEY `csTagID` (`csTagID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

$moduleTables[] = "
    CREATE TABLE `gibbonCsTicketWatcher` (
      `csTicketWatcherID` int(10) unsigned NOT NULL AUTO_INCREMENT,
      `csTicketID` int(10) unsigned NOT NULL,
      `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
      PRIMARY KEY (`csTicketWatcherID`),
      UNIQUE KEY `ticket_person` (`csTicketID`, `gibbonPersonID`),
      KEY `gibbonPersonID` (`gibbonPersonID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

$moduleTables[] = "
    CREATE TABLE `gibbonCsTicketLink` (
      `csTicketLinkID` int(10) unsigned NOT NULL AUTO_INCREMENT,
      `csTicketIDFrom` int(10) unsigned NOT NULL COMMENT 'FROM blocks / duplicates / relates to TO',
      `csTicketIDTo` int(10) unsigned NOT NULL,
      `linkType` enum('blocks','duplicates','relates') NOT NULL,
      `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
      `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`csTicketLinkID`),
      UNIQUE KEY `link` (`csTicketIDFrom`, `csTicketIDTo`, `linkType`),
      KEY `csTicketIDTo` (`csTicketIDTo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

$moduleTables[] = "
    CREATE TABLE `gibbonCsChecklist` (
      `csChecklistID` int(10) unsigned NOT NULL AUTO_INCREMENT,
      `csTicketID` int(10) unsigned NOT NULL,
      `title` varchar(100) NOT NULL,
      `sequenceNumber` int(4) NOT NULL DEFAULT 0,
      PRIMARY KEY (`csChecklistID`),
      KEY `csTicketID` (`csTicketID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

$moduleTables[] = "
    CREATE TABLE `gibbonCsChecklistItem` (
      `csChecklistItemID` int(10) unsigned NOT NULL AUTO_INCREMENT,
      `csChecklistID` int(10) unsigned NOT NULL,
      `text` varchar(255) NOT NULL,
      `done` enum('N','Y') NOT NULL DEFAULT 'N',
      `sequenceNumber` int(4) NOT NULL DEFAULT 0,
      `gibbonPersonIDDone` int(10) unsigned zerofill NULL,
      `timestampDone` timestamp NULL DEFAULT NULL,
      PRIMARY KEY (`csChecklistItemID`),
      KEY `csChecklistID` (`csChecklistID`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

$moduleTables[] = "
    CREATE TABLE `gibbonCsAutomationRule` (
      `csAutomationRuleID` int(8) unsigned NOT NULL AUTO_INCREMENT,
      `csTicketTypeID` int(6) unsigned NULL COMMENT 'NULL = applies to every ticket type',
      `name` varchar(100) NOT NULL,
      `triggerKey` varchar(50) NOT NULL COMMENT 'See AutomationEngine::triggers()',
      `conditions` text NULL COMMENT 'JSON list of {field, op, value} - same format as board filters',
      `actions` text NOT NULL COMMENT 'JSON list of {type, params} - see Automation/Actions',
      `sequenceNumber` int(4) NOT NULL DEFAULT 0,
      `active` enum('Y','N') NOT NULL DEFAULT 'Y',
      PRIMARY KEY (`csAutomationRuleID`),
      KEY `triggerKey` (`triggerKey`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

// Seed data: default roles, permissions, a team, a ticket type and a space with a board.
$moduleTables[] = "INSERT INTO `gibbonCsRole` (`csRoleID`, `name`, `description`) VALUES
    (1, 'Space Admin', 'Full control of a space: members, boards and all tickets'),
    (2, 'Agent', 'Triages and works on tickets (e.g. the L1 team)'),
    (3, 'Viewer', 'Can see the boards of the space and comment');";

$moduleTables[] = "INSERT INTO `gibbonCsRolePermission` (`csRoleID`, `permission`) VALUES
    (1, 'view_board'), (1, 'comment'), (1, 'edit_ticket'), (1, 'transition_ticket'), (1, 'assign_ticket'), (1, 'delete_ticket'), (1, 'manage_boards'), (1, 'manage_members'),
    (2, 'view_board'), (2, 'comment'), (2, 'edit_ticket'), (2, 'transition_ticket'), (2, 'assign_ticket'),
    (3, 'view_board'), (3, 'comment');";

$moduleTables[] = "INSERT INTO `gibbonCsTeam` (`csTeamID`, `name`, `description`) VALUES (1, 'L1 Support', 'First line: triage incoming requests and move them forward');";
$moduleTables[] = "INSERT INTO `gibbonCsTicketType` (`csTicketTypeID`, `name`, `description`, `csTeamID`) VALUES (1, 'General Request', 'Anything that does not fit another request type', 1);";
$moduleTables[] = "INSERT INTO `gibbonCsSpace` (`csSpaceID`, `name`, `description`) VALUES (1, 'Customer Service', 'Default space');";
$moduleTables[] = "INSERT INTO `gibbonCsBoard` (`csBoardID`, `csSpaceID`, `name`, `description`, `filter`) VALUES (1, 1, 'All requests', 'Every request, grouped by status', NULL);";
$moduleTables[] = "INSERT INTO `gibbonCsBoardColumn` (`csBoardID`, `name`, `sequenceNumber`) VALUES
    (1, 'New', 1), (1, 'In Progress', 2), (1, 'Waiting', 3), (1, 'Resolved', 4), (1, 'Closed', 5);";

// ---------------------------------------------------------------------------
// Actions
// ---------------------------------------------------------------------------

// Defaults: end-user actions are open to every role; admin actions to Admin/Staff only.
$csAll = [
    'defaultPermissionAdmin' => 'Y', 'defaultPermissionTeacher' => 'Y', 'defaultPermissionStudent' => 'Y',
    'defaultPermissionParent' => 'Y', 'defaultPermissionSupport' => 'Y',
    'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'Y',
    'categoryPermissionParent' => 'Y', 'categoryPermissionOther' => 'Y',
];
$csStaff = [
    'defaultPermissionAdmin' => 'Y', 'defaultPermissionTeacher' => 'Y', 'defaultPermissionStudent' => 'N',
    'defaultPermissionParent' => 'N', 'defaultPermissionSupport' => 'Y',
    'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'N',
    'categoryPermissionParent' => 'N', 'categoryPermissionOther' => 'N',
];
$csAdmin = [
    'defaultPermissionAdmin' => 'Y', 'defaultPermissionTeacher' => 'N', 'defaultPermissionStudent' => 'N',
    'defaultPermissionParent' => 'N', 'defaultPermissionSupport' => 'N',
    'categoryPermissionStaff' => 'Y', 'categoryPermissionStudent' => 'N',
    'categoryPermissionParent' => 'N', 'categoryPermissionOther' => 'N',
];

$actionRows[] = array_merge($csAll, [
    'name' => 'Customer Portal', 'precedence' => '0', 'category' => 'Requests',
    'description' => 'Choose a request type and fill in its form',
    'URLList' => 'cs_portal.php', 'entryURL' => 'cs_portal.php',
    'entrySidebar' => 'Y', 'menuShow' => 'Y',
]);

$actionRows[] = array_merge($csAll, [
    'name' => 'My Dashboard', 'precedence' => '1', 'category' => 'Requests',
    'description' => 'Overview of my requests and the tickets waiting on my boards',
    'URLList' => 'cs_dashboard.php', 'entryURL' => 'cs_dashboard.php',
    'entrySidebar' => 'Y', 'menuShow' => 'Y',
]);

$actionRows[] = array_merge($csAll, [
    'name' => 'Raise a Request', 'precedence' => '2', 'category' => 'Requests',
    'description' => 'Raise a new request using the form of a ticket type',
    'URLList' => 'cs_request_new.php, cs_request_newProcess.php', 'entryURL' => 'cs_request_new.php',
    'entrySidebar' => 'Y', 'menuShow' => 'Y',
]);

$actionRows[] = array_merge($csAll, [
    'name' => 'View Ticket', 'precedence' => '2', 'category' => 'Requests',
    'description' => 'View a ticket, comment on it and (with permission) triage it',
    'URLList' => 'cs_ticket_view.php, cs_ticket_process.php, cs_ticket_extrasProcess.php, cs_attachment.php', 'entryURL' => 'cs_ticket_view.php',
    'entrySidebar' => 'N', 'menuShow' => 'N',
]);

$actionRows[] = array_merge($csAdmin, [
    'name' => 'Manage Automation', 'precedence' => '14', 'category' => 'Configuration',
    'description' => 'Rules that run actions when something happens to a ticket (conditions + actions)',
    'URLList' => 'cs_rule_manage.php, cs_rule_edit.php, cs_rule_process.php, cs_delete.php, cs_delete_process.php',
    'entryURL' => 'cs_rule_manage.php', 'entrySidebar' => 'Y', 'menuShow' => 'Y',
]);

$actionRows[] = array_merge($csAll, [
    'name' => 'Search Tickets', 'precedence' => '2', 'category' => 'Requests',
    'description' => 'Search the tickets you can see, with quick views and saved filters',
    'URLList' => 'cs_ticket_search.php, cs_search_process.php', 'entryURL' => 'cs_ticket_search.php',
    'entrySidebar' => 'Y', 'menuShow' => 'Y',
]);

// Boards are a working tool of the staff: not offered to students / parents (membership of a space is still required).
$actionRows[] = array_merge($csStaff, [
    'name' => 'Boards', 'precedence' => '3', 'category' => 'Service Desk',
    'description' => 'Work on tickets on the boards of the spaces you are a member of',
    'URLList' => 'cs_board_view.php', 'entryURL' => 'cs_board_view.php',
    'entrySidebar' => 'Y', 'menuShow' => 'Y',
]);

$actionRows[] = array_merge($csAdmin, [
    'name' => 'Manage Teams', 'precedence' => '10', 'category' => 'Configuration',
    'description' => 'Define teams and their members',
    'URLList' => 'cs_team_manage.php, cs_team_edit.php, cs_team_process.php, cs_delete.php, cs_delete_process.php',
    'entryURL' => 'cs_team_manage.php', 'entrySidebar' => 'Y', 'menuShow' => 'Y',
]);

$actionRows[] = array_merge($csAdmin, [
    'name' => 'Manage Ticket Types', 'precedence' => '11', 'category' => 'Configuration',
    'description' => 'Define ticket types and the form fields of each type',
    'URLList' => 'cs_type_manage.php, cs_type_edit.php, cs_type_process.php, cs_field_edit.php, cs_field_process.php, cs_form_edit.php, cs_form_process.php, cs_delete.php, cs_delete_process.php',
    'entryURL' => 'cs_type_manage.php', 'entrySidebar' => 'Y', 'menuShow' => 'Y',
]);

$actionRows[] = array_merge($csAdmin, [
    'name' => 'Manage Roles', 'precedence' => '12', 'category' => 'Configuration',
    'description' => 'Define roles and assign permissions to them',
    'URLList' => 'cs_role_manage.php, cs_role_edit.php, cs_role_process.php, cs_delete.php, cs_delete_process.php',
    'entryURL' => 'cs_role_manage.php', 'entrySidebar' => 'Y', 'menuShow' => 'Y',
]);

// Not enabled for Teacher/Support by default: grant it in Manage Permissions to space admins.
// They then only see and manage the spaces where their role has the matching permission.
$actionRows[] = array_merge($csAdmin, [
    'name' => 'Manage Spaces', 'precedence' => '13', 'category' => 'Configuration',
    'description' => 'Define spaces, their members (with roles) and their boards',
    'URLList' => 'cs_space_manage.php, cs_space_edit.php, cs_space_process.php, cs_board_edit.php, cs_board_process.php, cs_delete.php, cs_delete_process.php',
    'entryURL' => 'cs_space_manage.php', 'entrySidebar' => 'Y', 'menuShow' => 'Y',
]);

// ---------------------------------------------------------------------------
// Hooks: show "My requests" on the Staff / Student / Parent dashboards
// ---------------------------------------------------------------------------
$csHook = serialize([
    'sourceModuleName' => $name,
    'sourceModuleAction' => 'My Dashboard',
    'sourceModuleInclude' => 'hook_dashboard.php',
]);
foreach (['Staff Dashboard', 'Student Dashboard', 'Parental Dashboard'] as $csHookType) {
    $hooks[] = "INSERT INTO `gibbonHook` (`name`, `type`, `options`, `gibbonModuleID`) VALUES ('Customer Requests', '".$csHookType."', '".$csHook."', (SELECT gibbonModuleID FROM gibbonModule WHERE name='".$name."'));";
}
