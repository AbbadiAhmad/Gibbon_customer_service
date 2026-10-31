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
