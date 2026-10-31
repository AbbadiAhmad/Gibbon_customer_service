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
INSERT INTO gibbonAction (gibbonModuleID, name, precedence, category, description, URLList, entryURL, entrySidebar, menuShow, defaultPermissionAdmin, defaultPermissionTeacher, defaultPermissionStudent, defaultPermissionParent, defaultPermissionSupport, categoryPermissionStaff, categoryPermissionStudent, categoryPermissionParent, categoryPermissionOther) SELECT gibbonModuleID, 'Search Tickets', 2, 'Requests', 'Search the tickets you can see, with quick views and saved filters', 'cs_ticket_search.php, cs_search_process.php', 'cs_ticket_search.php', entrySidebar, menuShow, defaultPermissionAdmin, defaultPermissionTeacher, defaultPermissionStudent, defaultPermissionParent, defaultPermissionSupport, categoryPermissionStaff, categoryPermissionStudent, categoryPermissionParent, categoryPermissionOther FROM gibbonAction WHERE name='My Dashboard' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='CustomerService');end
INSERT INTO gibbonPermission (gibbonRoleID, gibbonActionID) SELECT p.gibbonRoleID, n.gibbonActionID FROM gibbonPermission AS p JOIN gibbonAction AS o ON o.gibbonActionID=p.gibbonActionID JOIN gibbonAction AS n ON n.name='Search Tickets' AND n.gibbonModuleID=o.gibbonModuleID WHERE o.name='My Dashboard' AND o.gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='CustomerService');end
UPDATE gibbonAction SET URLList='cs_ticket_view.php, cs_ticket_process.php, cs_attachment.php' WHERE name='View Ticket' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='CustomerService');";

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
INSERT INTO gibbonAction (gibbonModuleID, name, precedence, category, description, URLList, entryURL, entrySidebar, menuShow, defaultPermissionAdmin, defaultPermissionTeacher, defaultPermissionStudent, defaultPermissionParent, defaultPermissionSupport, categoryPermissionStaff, categoryPermissionStudent, categoryPermissionParent, categoryPermissionOther) SELECT gibbonModuleID, 'Manage Automation', 14, 'Configuration', 'Rules that run actions when something happens to a ticket (conditions + actions)', 'cs_rule_manage.php, cs_rule_edit.php, cs_rule_process.php, cs_delete.php, cs_delete_process.php', 'cs_rule_manage.php', entrySidebar, menuShow, defaultPermissionAdmin, defaultPermissionTeacher, defaultPermissionStudent, defaultPermissionParent, defaultPermissionSupport, categoryPermissionStaff, categoryPermissionStudent, categoryPermissionParent, categoryPermissionOther FROM gibbonAction WHERE name='Manage Roles' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='CustomerService');end
INSERT INTO gibbonPermission (gibbonRoleID, gibbonActionID) SELECT p.gibbonRoleID, n.gibbonActionID FROM gibbonPermission AS p JOIN gibbonAction AS o ON o.gibbonActionID=p.gibbonActionID JOIN gibbonAction AS n ON n.name='Manage Automation' AND n.gibbonModuleID=o.gibbonModuleID WHERE o.name='Manage Roles' AND o.gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='CustomerService');end
UPDATE gibbonAction SET URLList='cs_ticket_view.php, cs_ticket_process.php, cs_ticket_extrasProcess.php, cs_attachment.php' WHERE name='View Ticket' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='CustomerService');";
