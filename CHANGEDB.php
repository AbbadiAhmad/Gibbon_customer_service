<?php
// USE ;end TO SEPARATE SQL STATEMENTS. DON'T USE ;end IN ANY OTHER PLACES!

$sql = [];
$count = 0;

// v0.0.00
$sql[$count][0] = "0.0.00";
$sql[$count][1] = "-- First version, nothing to update";

// v0.1.0 - initial release, tables are created by manifest.php
$count++;
$sql[$count][0] = "0.1.0";
$sql[$count][1] = "-- Initial release, nothing to update";

// v0.2.0 - notifications, attachments, internal notes, workflow, resolution / reopen, ticket search
$count++;
$sql[$count][0] = "0.2.0";
$sql[$count][1] = "ALTER TABLE `csTicketType` ADD COLUMN `workflow` text NULL COMMENT 'JSON: {transitions: {from: [to, ...]}, commentRequired: [status, ...]}' AFTER `csTeamID`;end
ALTER TABLE `csTicket` ADD COLUMN `resolution` text NULL COMMENT 'Resolution note given when the ticket was moved to Resolved / Closed' AFTER `customData`, ADD COLUMN `timestampResolved` timestamp NULL DEFAULT NULL AFTER `resolution`;end
ALTER TABLE `csTicketActivity` ADD COLUMN `visibility` enum('Public','Internal') NOT NULL DEFAULT 'Public' COMMENT 'Internal = only visible to the people working on the ticket' AFTER `kind`;end
CREATE TABLE `csTicketAttachment` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;end
CREATE TABLE `csSavedFilter` (
  `csSavedFilterID` int(8) unsigned NOT NULL AUTO_INCREMENT,
  `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
  `name` varchar(100) NOT NULL,
  `params` text NOT NULL COMMENT 'JSON of the ticket search form values',
  PRIMARY KEY (`csSavedFilterID`),
  KEY `gibbonPersonID` (`gibbonPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;end
INSERT INTO gibbonAction (gibbonModuleID, name, precedence, category, description, URLList, entryURL, entrySidebar, menuShow, defaultPermissionAdmin, defaultPermissionTeacher, defaultPermissionStudent, defaultPermissionParent, defaultPermissionSupport, categoryPermissionStaff, categoryPermissionStudent, categoryPermissionParent, categoryPermissionOther) SELECT gibbonModuleID, 'Search Tickets', 2, 'Requests', 'Search the tickets you can see, with quick views and saved filters', 'cs_ticket_search.php, cs_search_process.php', 'cs_ticket_search.php', entrySidebar, menuShow, defaultPermissionAdmin, defaultPermissionTeacher, defaultPermissionStudent, defaultPermissionParent, defaultPermissionSupport, categoryPermissionStaff, categoryPermissionStudent, categoryPermissionParent, categoryPermissionOther FROM gibbonAction WHERE name='My Dashboard' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='administration');end
INSERT INTO gibbonPermission (gibbonRoleID, gibbonActionID) SELECT p.gibbonRoleID, n.gibbonActionID FROM gibbonPermission AS p JOIN gibbonAction AS o ON o.gibbonActionID=p.gibbonActionID JOIN gibbonAction AS n ON n.name='Search Tickets' AND n.gibbonModuleID=o.gibbonModuleID WHERE o.name='My Dashboard' AND o.gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='administration');end
UPDATE gibbonAction SET URLList='cs_ticket_view.php, cs_ticket_process.php, cs_attachment.php' WHERE name='View Ticket' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='administration');";

// v0.3.0 - dates, subtasks, checklists, links, tags, watchers, automation rules
$count++;
$sql[$count][0] = "0.3.0";
$sql[$count][1] = "ALTER TABLE `csTicket` ADD COLUMN `csTicketIDParent` int(10) unsigned NULL COMMENT 'Set on subtasks: the ticket this one is a part of' AFTER `timestampResolved`, ADD COLUMN `dateStart` date NULL AFTER `csTicketIDParent`, ADD COLUMN `dateDue` date NULL AFTER `dateStart`, ADD COLUMN `dueReminderSent` datetime NULL AFTER `dateDue`, ADD COLUMN `overdueNoticeSent` datetime NULL AFTER `dueReminderSent`, ADD KEY `csTicketIDParent` (`csTicketIDParent`), ADD KEY `dateDue` (`dateDue`);end
CREATE TABLE `csTag` (
  `csTagID` int(8) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`csTagID`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;end
CREATE TABLE `csTicketTag` (
  `csTicketTagID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `csTicketID` int(10) unsigned NOT NULL,
  `csTagID` int(8) unsigned NOT NULL,
  PRIMARY KEY (`csTicketTagID`),
  UNIQUE KEY `ticket_tag` (`csTicketID`, `csTagID`),
  KEY `csTagID` (`csTagID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;end
CREATE TABLE `csTicketWatcher` (
  `csTicketWatcherID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `csTicketID` int(10) unsigned NOT NULL,
  `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
  PRIMARY KEY (`csTicketWatcherID`),
  UNIQUE KEY `ticket_person` (`csTicketID`, `gibbonPersonID`),
  KEY `gibbonPersonID` (`gibbonPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;end
CREATE TABLE `csTicketLink` (
  `csTicketLinkID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `csTicketIDFrom` int(10) unsigned NOT NULL COMMENT 'FROM blocks / duplicates / relates to TO',
  `csTicketIDTo` int(10) unsigned NOT NULL,
  `linkType` enum('blocks','duplicates','relates') NOT NULL,
  `gibbonPersonID` int(10) unsigned zerofill NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`csTicketLinkID`),
  UNIQUE KEY `link` (`csTicketIDFrom`, `csTicketIDTo`, `linkType`),
  KEY `csTicketIDTo` (`csTicketIDTo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;end
CREATE TABLE `csChecklist` (
  `csChecklistID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `csTicketID` int(10) unsigned NOT NULL,
  `title` varchar(100) NOT NULL,
  `sequenceNumber` int(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`csChecklistID`),
  KEY `csTicketID` (`csTicketID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;end
CREATE TABLE `csChecklistItem` (
  `csChecklistItemID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `csChecklistID` int(10) unsigned NOT NULL,
  `text` varchar(255) NOT NULL,
  `done` enum('N','Y') NOT NULL DEFAULT 'N',
  `sequenceNumber` int(4) NOT NULL DEFAULT 0,
  `gibbonPersonIDDone` int(10) unsigned zerofill NULL,
  `timestampDone` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`csChecklistItemID`),
  KEY `csChecklistID` (`csChecklistID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;end
CREATE TABLE `csAutomationRule` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;end
INSERT INTO gibbonAction (gibbonModuleID, name, precedence, category, description, URLList, entryURL, entrySidebar, menuShow, defaultPermissionAdmin, defaultPermissionTeacher, defaultPermissionStudent, defaultPermissionParent, defaultPermissionSupport, categoryPermissionStaff, categoryPermissionStudent, categoryPermissionParent, categoryPermissionOther) SELECT gibbonModuleID, 'Manage Automation', 14, 'Configuration', 'Rules that run actions when something happens to a ticket (conditions + actions)', 'cs_rule_manage.php, cs_rule_edit.php, cs_rule_process.php, cs_delete.php, cs_delete_process.php', 'cs_rule_manage.php', entrySidebar, menuShow, defaultPermissionAdmin, defaultPermissionTeacher, defaultPermissionStudent, defaultPermissionParent, defaultPermissionSupport, categoryPermissionStaff, categoryPermissionStudent, categoryPermissionParent, categoryPermissionOther FROM gibbonAction WHERE name='Manage Roles' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='administration');end
INSERT INTO gibbonPermission (gibbonRoleID, gibbonActionID) SELECT p.gibbonRoleID, n.gibbonActionID FROM gibbonPermission AS p JOIN gibbonAction AS o ON o.gibbonActionID=p.gibbonActionID JOIN gibbonAction AS n ON n.name='Manage Automation' AND n.gibbonModuleID=o.gibbonModuleID WHERE o.name='Manage Roles' AND o.gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='administration');end
UPDATE gibbonAction SET URLList='cs_ticket_view.php, cs_ticket_process.php, cs_ticket_extrasProcess.php, cs_attachment.php' WHERE name='View Ticket' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='administration');";

// v0.4.0 - customer portal, request form builder, request type audience, staff-only boards
$count++;
$sql[$count][0] = "0.4.0";
$sql[$count][1] = "ALTER TABLE `csTicketType` ADD COLUMN `groupName` varchar(60) NULL COMMENT 'Heading the type is listed under on the customer portal' AFTER `workflow`, ADD COLUMN `icon` varchar(16) NULL COMMENT 'Emoji shown on the portal card' AFTER `groupName`, ADD COLUMN `audience` varchar(100) NULL COMMENT 'Comma separated Gibbon role categories allowed to raise it (Staff, Student, Parent, Other). Empty = everybody' AFTER `icon`, ADD COLUMN `formConfig` text NULL COMMENT 'JSON: settings of the basic request form fields (show, required, label, help, order)' AFTER `audience`;end
ALTER TABLE `csField` ADD COLUMN `showOnForm` enum('Y','N') NOT NULL DEFAULT 'Y' COMMENT 'N = only the agents fill it in, the portal form does not show it' AFTER `required`;end
INSERT INTO gibbonAction (gibbonModuleID, name, precedence, category, description, URLList, entryURL, entrySidebar, menuShow, defaultPermissionAdmin, defaultPermissionTeacher, defaultPermissionStudent, defaultPermissionParent, defaultPermissionSupport, categoryPermissionStaff, categoryPermissionStudent, categoryPermissionParent, categoryPermissionOther) SELECT gibbonModuleID, 'Customer Portal', 0, 'Requests', 'Choose a request type and fill in its form', 'cs_portal.php', 'cs_portal.php', entrySidebar, menuShow, defaultPermissionAdmin, defaultPermissionTeacher, defaultPermissionStudent, defaultPermissionParent, defaultPermissionSupport, categoryPermissionStaff, categoryPermissionStudent, categoryPermissionParent, categoryPermissionOther FROM gibbonAction WHERE name='My Dashboard' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='administration');end
INSERT INTO gibbonPermission (gibbonRoleID, gibbonActionID) SELECT p.gibbonRoleID, n.gibbonActionID FROM gibbonPermission AS p JOIN gibbonAction AS o ON o.gibbonActionID=p.gibbonActionID JOIN gibbonAction AS n ON n.name='Customer Portal' AND n.gibbonModuleID=o.gibbonModuleID WHERE o.name='My Dashboard' AND o.gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='administration');end
UPDATE gibbonModule SET entryURL='cs_portal.php' WHERE name='administration';end
UPDATE gibbonAction SET URLList='cs_type_manage.php, cs_type_edit.php, cs_type_process.php, cs_field_edit.php, cs_field_process.php, cs_form_edit.php, cs_form_process.php, cs_delete.php, cs_delete_process.php' WHERE name='Manage Ticket Types' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='administration');end
UPDATE gibbonAction SET defaultPermissionStudent='N', defaultPermissionParent='N', categoryPermissionStudent='N', categoryPermissionParent='N', categoryPermissionOther='N' WHERE name='Boards' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='administration');end
DELETE gibbonPermission FROM gibbonPermission JOIN gibbonAction ON (gibbonAction.gibbonActionID=gibbonPermission.gibbonActionID) JOIN gibbonRole ON (gibbonRole.gibbonRoleID=gibbonPermission.gibbonRoleID) WHERE gibbonAction.name='Boards' AND gibbonAction.gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='administration') AND gibbonRole.category IN ('Student','Parent','Other');";

// v0.5.0 - tables follow the Gibbon naming convention: csTeam -> gibbonCsTeam (all 21 tables, data is kept)
$count++;
$sql[$count][0] = "0.5.0";
$sql[$count][1] = "RENAME TABLE `csTeam` TO `gibbonCsTeam`, `csTeamMember` TO `gibbonCsTeamMember`, `csTicketType` TO `gibbonCsTicketType`, `csField` TO `gibbonCsField`, `csRole` TO `gibbonCsRole`, `csRolePermission` TO `gibbonCsRolePermission`, `csSpace` TO `gibbonCsSpace`, `csSpaceMember` TO `gibbonCsSpaceMember`, `csBoard` TO `gibbonCsBoard`, `csBoardColumn` TO `gibbonCsBoardColumn`, `csTicket` TO `gibbonCsTicket`, `csTicketActivity` TO `gibbonCsTicketActivity`, `csTicketAttachment` TO `gibbonCsTicketAttachment`, `csSavedFilter` TO `gibbonCsSavedFilter`, `csTag` TO `gibbonCsTag`, `csTicketTag` TO `gibbonCsTicketTag`, `csTicketWatcher` TO `gibbonCsTicketWatcher`, `csTicketLink` TO `gibbonCsTicketLink`, `csChecklist` TO `gibbonCsChecklist`, `csChecklistItem` TO `gibbonCsChecklistItem`, `csAutomationRule` TO `gibbonCsAutomationRule`;";

// v0.6.0 - status categories (To Do / In Progress / Done): registry of statuses; existing statuses are classified, custom ones as In Progress
$count++;
$sql[$count][0] = "0.6.0";
$sql[$count][1] = "CREATE TABLE `gibbonCsStatus` (
  `csStatusID` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(60) NOT NULL COMMENT 'Ticket status, also the name of the board column that shows it',
  `category` enum('To Do','In Progress','Done') NOT NULL DEFAULT 'In Progress' COMMENT 'Every status belongs to one of the three base categories',
  `sequenceNumber` int(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`csStatusID`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;end
INSERT IGNORE INTO `gibbonCsStatus` (`name`, `category`, `sequenceNumber`) VALUES ('New', 'To Do', 1), ('In Progress', 'In Progress', 2), ('Waiting', 'In Progress', 3), ('Resolved', 'Done', 4), ('Closed', 'Done', 5);end
INSERT IGNORE INTO `gibbonCsStatus` (`name`, `category`, `sequenceNumber`) SELECT DISTINCT `name`, 'In Progress', 0 FROM `gibbonCsBoardColumn`;end
INSERT IGNORE INTO `gibbonCsStatus` (`name`, `category`, `sequenceNumber`) SELECT DISTINCT `status`, 'In Progress', 0 FROM `gibbonCsTicket`;";

// v0.7.0 - Quick Create action (staff only, same role permissions as Boards)
$count++;
$sql[$count][0] = "0.7.0";
$sql[$count][1] = "INSERT INTO gibbonAction (gibbonModuleID, name, precedence, category, description, URLList, entryURL, entrySidebar, menuShow, defaultPermissionAdmin, defaultPermissionTeacher, defaultPermissionStudent, defaultPermissionParent, defaultPermissionSupport, categoryPermissionStaff, categoryPermissionStudent, categoryPermissionParent, categoryPermissionOther) SELECT gibbonModuleID, 'Quick Create', 4, 'Service Desk', 'Create a ticket in one step: type, title, summary and assignee', 'cs_ticket_quick.php, cs_ticket_quickProcess.php', 'cs_ticket_quick.php', entrySidebar, menuShow, defaultPermissionAdmin, defaultPermissionTeacher, defaultPermissionStudent, defaultPermissionParent, defaultPermissionSupport, categoryPermissionStaff, categoryPermissionStudent, categoryPermissionParent, categoryPermissionOther FROM gibbonAction WHERE name='Boards' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='administration');end
INSERT INTO gibbonPermission (gibbonRoleID, gibbonActionID) SELECT p.gibbonRoleID, n.gibbonActionID FROM gibbonPermission AS p JOIN gibbonAction AS o ON o.gibbonActionID=p.gibbonActionID JOIN gibbonAction AS n ON n.name='Quick Create' AND n.gibbonModuleID=o.gibbonModuleID WHERE o.name='Boards' AND o.gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='administration');";

// v0.8.0 - request forms are separate from ticket types: forms + questions with a mapping to the ticket fields. Every ticket type gets a form
// with the questions its old request form had; portal group / icon / audience move to the form; "not on the form" fields become internal fields.
$count++;
$sql[$count][0] = "0.8.0";
$sql[$count][1] = "ALTER TABLE `gibbonCsTicket` ADD COLUMN `csFormID` int(6) unsigned NULL COMMENT 'The request form the ticket was raised with (NULL: quick create, subtask)' AFTER `csTeamID`, ADD KEY `csFormID` (`csFormID`);end
CREATE TABLE `gibbonCsForm` (
  `csFormID` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `csTicketTypeID` int(6) unsigned NOT NULL COMMENT 'The ticket type of the tickets this form creates',
  `name` varchar(100) NOT NULL,
  `slug` varchar(80) NOT NULL COMMENT 'Readable part of the shareable link',
  `description` text NULL,
  `groupName` varchar(60) NULL COMMENT 'Heading the form is listed under on the customer portal',
  `icon` varchar(16) NULL COMMENT 'Symbol shown on the portal card',
  `audience` varchar(100) NULL COMMENT 'Comma separated Gibbon role categories that may use the form (Staff, Student, Parent, Other)',
  `roles` varchar(255) NULL COMMENT 'Comma separated gibbonRoleIDs that may use the form. Audience and roles empty = every registered user',
  `titleTemplate` varchar(200) NULL COMMENT 'Title of the ticket when no question is mapped to it, e.g. Absence {child_name}',
  `confirmationText` text NULL COMMENT 'Shown to the requester after sending',
  `sequenceNumber` int(4) NOT NULL DEFAULT 0,
  `active` enum('Y','N') NOT NULL DEFAULT 'Y',
  PRIMARY KEY (`csFormID`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `slug` (`slug`),
  KEY `csTicketTypeID` (`csTicketTypeID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;end
CREATE TABLE `gibbonCsFormField` (
  `csFormFieldID` int(8) unsigned NOT NULL AUTO_INCREMENT,
  `csFormID` int(6) unsigned NOT NULL,
  `fieldKey` varchar(50) NOT NULL COMMENT 'Unique in the form; the name of the input and the placeholder of the title template',
  `label` varchar(150) NOT NULL,
  `helpText` varchar(255) NULL,
  `inputType` enum('varchar','text','number','date','select','radio','checkboxes','yesno','file','info') NOT NULL DEFAULT 'varchar',
  `options` text NULL COMMENT 'Max length for short text, comma separated choices for select / radio / checkboxes',
  `required` enum('N','Y') NOT NULL DEFAULT 'N',
  `target` varchar(80) NOT NULL DEFAULT '' COMMENT 'The ticket field the answer goes to: title, description, priority, dateStart, dateDue, attachments, custom:<fieldKey of the ticket type>. Empty for information text',
  `sequenceNumber` int(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`csFormFieldID`),
  UNIQUE KEY `form_key` (`csFormID`, `fieldKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;end
INSERT INTO `gibbonCsForm` (`csFormID`, `csTicketTypeID`, `name`, `slug`, `description`, `groupName`, `icon`, `audience`, `sequenceNumber`, `active`) SELECT `csTicketTypeID`, `csTicketTypeID`, `name`, CONCAT('form-', `csTicketTypeID`), `description`, `groupName`, `icon`, `audience`, `csTicketTypeID` * 10, `active` FROM `gibbonCsTicketType`;end
INSERT INTO `gibbonCsFormField` (`csFormID`, `fieldKey`, `label`, `helpText`, `inputType`, `options`, `required`, `target`, `sequenceNumber`) SELECT f.`csFormID`, 'ticket_title', COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(t.formConfig, '\$.title.label')), ''), 'Summary'), COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(t.formConfig, '\$.title.help')), ''), NULL), 'varchar', '200', 'Y', 'title', COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(t.formConfig, '\$.title.order')) AS SIGNED), 1) FROM `gibbonCsForm` f JOIN `gibbonCsTicketType` t ON (t.`csTicketTypeID`=f.`csTicketTypeID`) WHERE 1=1;end
INSERT INTO `gibbonCsFormField` (`csFormID`, `fieldKey`, `label`, `helpText`, `inputType`, `options`, `required`, `target`, `sequenceNumber`) SELECT f.`csFormID`, 'ticket_description', COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(t.formConfig, '\$.description.label')), ''), 'Description'), COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(t.formConfig, '\$.description.help')), ''), NULL), 'text', NULL, IF(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(t.formConfig, '\$.description.required')), 'false')='true', 'Y', 'N'), 'description', COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(t.formConfig, '\$.description.order')) AS SIGNED), 2) FROM `gibbonCsForm` f JOIN `gibbonCsTicketType` t ON (t.`csTicketTypeID`=f.`csTicketTypeID`) WHERE 1=1 AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(t.formConfig, '\$.description.show')), 'true') <> 'false';end
INSERT INTO `gibbonCsFormField` (`csFormID`, `fieldKey`, `label`, `helpText`, `inputType`, `options`, `required`, `target`, `sequenceNumber`) SELECT f.`csFormID`, 'ticket_priority', COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(t.formConfig, '\$.priority.label')), ''), 'Priority'), COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(t.formConfig, '\$.priority.help')), ''), NULL), 'select', 'Low,Medium,High,Urgent', IF(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(t.formConfig, '\$.priority.required')), 'false')='true', 'Y', 'N'), 'priority', COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(t.formConfig, '\$.priority.order')) AS SIGNED), 3) FROM `gibbonCsForm` f JOIN `gibbonCsTicketType` t ON (t.`csTicketTypeID`=f.`csTicketTypeID`) WHERE 1=1 AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(t.formConfig, '\$.priority.show')), 'true') <> 'false';end
INSERT INTO `gibbonCsFormField` (`csFormID`, `fieldKey`, `label`, `helpText`, `inputType`, `options`, `required`, `target`, `sequenceNumber`) SELECT f.`csFormID`, 'ticket_start', COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(t.formConfig, '\$.dateStart.label')), ''), 'Start Date'), COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(t.formConfig, '\$.dateStart.help')), ''), 'Optional. When the work should begin.'), 'date', NULL, IF(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(t.formConfig, '\$.dateStart.required')), 'false')='true', 'Y', 'N'), 'dateStart', COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(t.formConfig, '\$.dateStart.order')) AS SIGNED), 4) FROM `gibbonCsForm` f JOIN `gibbonCsTicketType` t ON (t.`csTicketTypeID`=f.`csTicketTypeID`) WHERE 1=1 AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(t.formConfig, '\$.dateStart.show')), 'true') <> 'false';end
INSERT INTO `gibbonCsFormField` (`csFormID`, `fieldKey`, `label`, `helpText`, `inputType`, `options`, `required`, `target`, `sequenceNumber`) SELECT f.`csFormID`, 'ticket_due', COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(t.formConfig, '\$.dateDue.label')), ''), 'Due Date'), COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(t.formConfig, '\$.dateDue.help')), ''), 'Optional. When you need it done.'), 'date', NULL, IF(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(t.formConfig, '\$.dateDue.required')), 'false')='true', 'Y', 'N'), 'dateDue', COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(t.formConfig, '\$.dateDue.order')) AS SIGNED), 5) FROM `gibbonCsForm` f JOIN `gibbonCsTicketType` t ON (t.`csTicketTypeID`=f.`csTicketTypeID`) WHERE 1=1 AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(t.formConfig, '\$.dateDue.show')), 'true') <> 'false';end
INSERT INTO `gibbonCsFormField` (`csFormID`, `fieldKey`, `label`, `helpText`, `inputType`, `options`, `required`, `target`, `sequenceNumber`) SELECT f.`csFormID`, 'ticket_files', COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(t.formConfig, '\$.attachments.label')), ''), 'Attachments'), COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(t.formConfig, '\$.attachments.help')), ''), NULL), 'file', NULL, IF(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(t.formConfig, '\$.attachments.required')), 'false')='true', 'Y', 'N'), 'attachments', COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(t.formConfig, '\$.attachments.order')) AS SIGNED), 1000) FROM `gibbonCsForm` f JOIN `gibbonCsTicketType` t ON (t.`csTicketTypeID`=f.`csTicketTypeID`) WHERE 1=1 AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(t.formConfig, '\$.attachments.show')), 'true') <> 'false';end
INSERT INTO `gibbonCsFormField` (`csFormID`, `fieldKey`, `label`, `helpText`, `inputType`, `options`, `required`, `target`, `sequenceNumber`) SELECT f.`csFormID`, c.`fieldKey`, c.`label`, c.`description`, c.`fieldType`, c.`options`, c.`required`, CONCAT('custom:', c.`fieldKey`), c.`sequenceNumber` FROM `gibbonCsField` c JOIN `gibbonCsForm` f ON (f.`csTicketTypeID`=c.`csTicketTypeID`) WHERE c.`showOnForm`='Y';end
ALTER TABLE `gibbonCsField` ADD COLUMN `internal` enum('N','Y') NOT NULL DEFAULT 'N' COMMENT 'Y = only the agents see and fill it in; the requester does not see it on the ticket and forms cannot map to it' AFTER `required`;end
UPDATE `gibbonCsField` SET `internal`=IF(`showOnForm`='N', 'Y', 'N');end
ALTER TABLE `gibbonCsField` DROP COLUMN `showOnForm`;end
ALTER TABLE `gibbonCsTicketType` DROP COLUMN `groupName`, DROP COLUMN `icon`, DROP COLUMN `audience`, DROP COLUMN `formConfig`;end
INSERT INTO gibbonAction (gibbonModuleID, name, precedence, category, description, URLList, entryURL, entrySidebar, menuShow, defaultPermissionAdmin, defaultPermissionTeacher, defaultPermissionStudent, defaultPermissionParent, defaultPermissionSupport, categoryPermissionStaff, categoryPermissionStudent, categoryPermissionParent, categoryPermissionOther) SELECT gibbonModuleID, 'Manage Forms', 11, 'Configuration', 'Build the request forms: questions, what each question fills in on the ticket, and who can use the form', 'cs_form_manage.php, cs_form_edit.php, cs_form_process.php, cs_formfield_edit.php, cs_formfield_process.php, cs_delete.php, cs_delete_process.php', 'cs_form_manage.php', entrySidebar, menuShow, defaultPermissionAdmin, defaultPermissionTeacher, defaultPermissionStudent, defaultPermissionParent, defaultPermissionSupport, categoryPermissionStaff, categoryPermissionStudent, categoryPermissionParent, categoryPermissionOther FROM gibbonAction WHERE name='Manage Ticket Types' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='administration');end
INSERT INTO gibbonPermission (gibbonRoleID, gibbonActionID) SELECT p.gibbonRoleID, n.gibbonActionID FROM gibbonPermission AS p JOIN gibbonAction AS o ON o.gibbonActionID=p.gibbonActionID JOIN gibbonAction AS n ON n.name='Manage Forms' AND n.gibbonModuleID=o.gibbonModuleID WHERE o.name='Manage Ticket Types' AND o.gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='administration');end
UPDATE gibbonAction SET URLList='cs_type_manage.php, cs_type_edit.php, cs_type_process.php, cs_field_edit.php, cs_field_process.php, cs_delete.php, cs_delete_process.php' WHERE name='Manage Ticket Types' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='administration');";
